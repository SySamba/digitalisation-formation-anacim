<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Agent.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">ID agent manquant.</div>';
    exit;
}

$database = new Database();
$db = $database->getConnection();
$agent = new Agent($db);

$agent_data = $agent->readOne($_GET['id']);
if (!$agent_data) {
    echo '<div class="alert alert-danger">Agent non trouvé.</div>';
    exit;
}

// Récupérer les formations effectuées - VERSION AMÉLIORÉE (UNION des deux tables)
$pdo = $database->getConnection();
$stmt_formations = $pdo->prepare("
    (SELECT fe.id, fe.agent_id, fe.formation_id, fe.centre_formation, 
            fe.date_debut, fe.date_fin, fe.fichier_joint, fe.statut, 
            fe.prochaine_echeance, fe.created_at,
            f.intitule, f.code, f.categorie, f.periodicite_mois,
            'formations_effectuees' as source_table
     FROM formations_effectuees fe
     JOIN formations f ON fe.formation_id = f.id
     WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide'))
    
    UNION ALL
    
    (SELECT fa.id, fa.agent_id, fa.formation_id, fa.centre_formation,
            fa.date_debut, fa.date_fin, fa.fichier_joint, fa.statut,
            fa.prochaine_echeance, fa.created_at,
            f.intitule, f.code, f.categorie, f.periodicite_mois,
            'formations_agents' as source_table
     FROM formations_agents fa
     JOIN formations f ON fa.formation_id = f.id
     WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide'))
    
    ORDER BY date_fin DESC
");
$stmt_formations->execute([$_GET['id'], $_GET['id']]);
$formations_effectuees_raw = $stmt_formations->fetchAll();

// Éliminer les doublons basés sur formation_id (garder la plus récente)
$formations_effectuees = [];
$formations_vues = [];

foreach ($formations_effectuees_raw as $formation) {
    $formation_id = $formation['formation_id'];
    
    if (!isset($formations_vues[$formation_id])) {
        $formations_effectuees[] = $formation;
        $formations_vues[$formation_id] = true;
    } else {
        // Si on a déjà cette formation, garder la plus récente
        $index_existant = -1;
        foreach ($formations_effectuees as $i => $existing) {
            if ($existing['formation_id'] == $formation_id) {
                $index_existant = $i;
                break;
            }
        }
        
        if ($index_existant >= 0 && strtotime($formation['date_fin']) > strtotime($formations_effectuees[$index_existant]['date_fin'])) {
            $formations_effectuees[$index_existant] = $formation;
        }
    }
}

// Récupérer les formations planifiées - VERSION AMÉLIORÉE
$stmt_pf = $pdo->prepare("SELECT pf.*, f.code, f.intitule, f.categorie, f.periodicite_mois FROM planning_formations pf JOIN formations f ON pf.formation_id = f.id WHERE pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme') ORDER BY pf.date_prevue_debut ASC");
$stmt_pf->execute([$_GET['id']]);
$formations_planifiees = $stmt_pf->fetchAll();

// Récupérer les formations non effectuées - VERSION AMÉLIORÉE
$stmt_nf = $pdo->prepare("
    SELECT DISTINCT f.id, f.intitule, f.code, f.categorie, f.periodicite_mois
    FROM formations f
    WHERE f.id NOT IN (
        -- Exclure les formations déjà effectuées
        SELECT DISTINCT fe.formation_id 
        FROM formations_effectuees fe 
        WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide')
        
        UNION
        
        SELECT DISTINCT fa.formation_id 
        FROM formations_agents fa 
        WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide')
    )
    AND f.id NOT IN (
        -- Exclure les formations déjà planifiées
        SELECT DISTINCT pf.formation_id 
        FROM planning_formations pf 
        WHERE pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme')
    )
    ORDER BY f.categorie, f.code
");
$stmt_nf->execute([$_GET['id'], $_GET['id'], $_GET['id']]);
$formations_non_effectuees = $stmt_nf->fetchAll();

// Récupérer les diplômes depuis la nouvelle table
$stmt_diplomes = $pdo->prepare("SELECT * FROM diplomes WHERE agent_id = ? ORDER BY created_at DESC");
$stmt_diplomes->execute([$_GET['id']]);
$diplomes = $stmt_diplomes->fetchAll();

// Récupérer les formations à mettre à jour (à renouveler) - VERSION SIMPLIFIÉE
// Approche en deux étapes pour éviter les problèmes de sous-requêtes complexes
// Pour les formations techniques (SUR-FTS), on regarde dans les 3 ans (36 mois)
// Pour les autres formations, on regarde dans les 6 mois (180 jours)

// Étape 1: Récupérer toutes les formations effectuées avec périodicité
$stmt_all_formations = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_effectuees' as source_table
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide') AND f.periodicite_mois > 0
    
    UNION ALL
    
    SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_agents' as source_table
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide') AND f.periodicite_mois > 0
    
    ORDER BY formation_id, date_fin DESC
");
$stmt_all_formations->execute([$_GET['id'], $_GET['id']]);
$all_formations_temp = $stmt_all_formations->fetchAll();

// Étape 2: Traitement PHP pour garder seulement la formation la plus récente par formation_id
$formations_par_id = [];
foreach ($all_formations_temp as $formation) {
    $formation_id = $formation['formation_id'];
    if (!isset($formations_par_id[$formation_id]) || 
        strtotime($formation['date_fin']) > strtotime($formations_par_id[$formation_id]['date_fin'])) {
        $formations_par_id[$formation_id] = $formation;
    }
}

// Étape 3: Filtrer les formations à renouveler selon les critères d'échéance
$formations_a_renouveler = [];
foreach ($formations_par_id as $formation) {
    $a_renouveler = false;
    
    if (strpos($formation['code'], 'SUR-FTS') !== false) {
        // Formations techniques SUR-FTS : échéance dans les 3 ans (1095 jours)
        $a_renouveler = $formation['jours_restants'] <= 1095;
    } else {
        // Autres formations : échéance dans les 6 mois (180 jours)
        $a_renouveler = $formation['jours_restants'] <= 180;
    }
    
    if ($a_renouveler) {
        $formations_a_renouveler[] = $formation;
    }
}

// Trier par jours restants
usort($formations_a_renouveler, function($a, $b) {
    return $a['jours_restants'] - $b['jours_restants'];
});

$fichiers = $agent->getFichiersAgent($_GET['id']);
?>

<div class="container-fluid">
    <!-- Navigation buttons at the top -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group w-100" role="group" aria-label="Navigation sections">
                <button class="btn btn-primary active" onclick="showAgentSection('info')" id="btn-info">
                    <i class="fas fa-user"></i> Fiche Inspecteur
                </button>
                <button class="btn btn-outline-primary" onclick="showAgentSection('diplomes')" id="btn-diplomes">
                    <i class="fas fa-graduation-cap"></i> Diplômes
                </button>
                <button class="btn btn-outline-primary" onclick="showAgentSection('formations-effectuees')" id="btn-formations-effectuees">
                    <i class="fas fa-check-circle"></i> Effectuées
                </button>
                <button class="btn btn-outline-primary" onclick="showAgentSection('formations-non-effectuees')" id="btn-formations-non-effectuees">
                    <i class="fas fa-times-circle"></i> Non Effectuées
                </button>
                <button class="btn btn-outline-primary" onclick="showAgentSection('formations-periodiques')" id="btn-formations-periodiques">
                    <i class="fas fa-clock"></i> À Renouveler
                </button>
                <button class="btn btn-outline-primary" onclick="showAgentSection('planning')" id="btn-planning">
                    <i class="fas fa-calendar"></i> Planning
                </button>
                <button class="btn btn-outline-success" onclick="showAgentSection('rapports')" id="btn-rapports">
                    <i class="fas fa-chart-bar"></i> Rapports
                </button>
            </div>
        </div>
    </div>

    <!-- Content sections -->
    <div id="agentContent">
        <!-- Fiche Inspecteur -->
        <div class="agent-section" id="info" style="display: block;">
            <div class="row">
                <div class="col-md-8">
                    <table class="table table-bordered">
                        <tr>
                            <th>Matricule</th>
                            <td><?= htmlspecialchars($agent_data['matricule']) ?></td>
                        </tr>
                        <tr>
                            <th>Prénom</th>
                            <td><?= htmlspecialchars($agent_data['prenom']) ?></td>
                        </tr>
                        <tr>
                            <th>Nom</th>
                            <td><?= htmlspecialchars($agent_data['nom']) ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?= htmlspecialchars($agent_data['email'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th>Date de Recrutement</th>
                            <td><?= formatDate($agent_data['date_recrutement']) ?></td>
                        </tr>
                        <tr>
                            <th>Structure Attachée</th>
                            <td><?= htmlspecialchars($agent_data['structure_attache'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th>Domaine d'Activités</th>
                            <td><?= htmlspecialchars($agent_data['domaine_activites'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th>Spécialité</th>
                            <td><?= htmlspecialchars($agent_data['specialite'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th>Grade</th>
                            <td><?= getGradeLabel($agent_data['grade']) ?></td>
                        </tr>
                        
                        <?php if (isInspecteurTitulaire($agent_data['grade'])): ?>
                            <tr>
                                <th>Date de Nomination</th>
                                <td><?= formatDate($agent_data['date_nomination']) ?></td>
                            </tr>
                            <tr>
                                <th>Numéro de Badge</th>
                                <td><?= htmlspecialchars($agent_data['numero_badge'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <th>Date de Validité du Badge</th>
                                <td><?= formatDate($agent_data['date_validite_badge']) ?></td>
                            </tr>
                            <tr>
                                <th>Date de Prestation de Serment</th>
                                <td><?= formatDate($agent_data['date_prestation_serment']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-4">
                    <?php if ($agent_data['photo']): ?>
                        <img src="uploads/photos/<?= htmlspecialchars($agent_data['photo']) ?>" 
                             class="img-fluid rounded" alt="Photo de l'agent" style="max-height: 200px;">
                    <?php else: ?>
                        <div class="text-center p-4 border rounded bg-light">
                            <i class="fas fa-user fa-4x text-muted"></i>
                            <p class="mt-2 text-muted">Aucune photo</p>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>

        <!-- Diplômes Académiques -->
        <div class="agent-section" id="diplomes" style="display: none;">
            <h5>Diplômes et Attestations</h5>
            
            <?php if (empty($diplomes)): ?>
                <div class="alert alert-info">Aucun diplôme enregistré.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Titre</th>
                                <th>Fichier</th>
                                <th>Date d'ajout</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diplomes as $diplome): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?= strtoupper(htmlspecialchars($diplome['type_diplome'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($diplome['titre'])): ?>
                                            <strong><?= htmlspecialchars($diplome['titre']) ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($diplome['fichier_path']): ?>
                                            <a href="uploads/diplomes/<?= htmlspecialchars($diplome['fichier_path']) ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i> Voir fichier
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Aucun fichier</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($diplome['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteDiplome(<?= $diplome['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Formations Effectuées -->
        <div class="agent-section" id="formations-effectuees" style="display: none;">
            <h5 class="mb-3">Formations Effectuées</h5>
            
            <?php 
            // Grouper les formations par catégorie
            $formations_par_categorie = [
                'FAMILIARISATION (SUR-FAM)' => [],
                'FORMATION INITIALE (SUR-INI)' => [],
                'FORMATION EN COURS D\'EMPLOI (SUR-FCE)' => [],
                'FORMATION TECHNIQUE/SPÉCIALISÉE (SUR-FTS)' => []
            ];
            
            foreach ($formations_effectuees as $formation) {
                if (strpos($formation['code'], 'SUR-FAM') !== false) {
                    $formations_par_categorie['FAMILIARISATION (SUR-FAM)'][] = $formation;
                } elseif (strpos($formation['code'], 'SUR-INI') !== false) {
                    $formations_par_categorie['FORMATION INITIALE (SUR-INI)'][] = $formation;
                } elseif (strpos($formation['code'], 'SUR-FCE') !== false) {
                    $formations_par_categorie['FORMATION EN COURS D\'EMPLOI (SUR-FCE)'][] = $formation;
                } elseif (strpos($formation['code'], 'SUR-FTS') !== false) {
                    $formations_par_categorie['FORMATION TECHNIQUE/SPÉCIALISÉE (SUR-FTS)'][] = $formation;
                }
            }
            ?>
            
            <?php if (empty($formations_effectuees)): ?>
                <div class="alert alert-info">Aucune formation effectuée enregistrée.</div>
            <?php else: ?>
                <?php foreach ($formations_par_categorie as $categorie => $formations_cat): ?>
                    <?php if (!empty($formations_cat)): ?>
                        <div class="mb-4">
                            <h6 class="text-primary border-bottom pb-2 mb-3"><?= $categorie ?></h6>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Formation</th>
                                            <th>Centre de Formation</th>
                                            <th>Date Début</th>
                                            <th>Date Fin</th>
                                            <th>Statut</th>
                                            <th>Fichier</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($formations_cat as $formation): ?>
                                            <tr class="formation-row" data-category="<?= htmlspecialchars($formation['code']) ?>">
                                                <td><?= htmlspecialchars($formation['intitule']) ?></td>
                                                <td><?= htmlspecialchars($formation['centre_formation'] ?? '') ?></td>
                                                <td><?= date('d/m/Y', strtotime($formation['date_debut'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($formation['date_fin'])) ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Effectuée
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($formation['fichier_joint']): ?>
                                                        <a href="uploads/formations/<?= htmlspecialchars($formation['fichier_joint']) ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-download"></i> Voir
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucun fichier</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formations Non Effectuées -->
        <div class="agent-section" id="formations-non-effectuees" style="display: none;">
            <h5 class="mb-3">Formations Non Effectuées</h5>
            
            <?php 
            // Ajouter le statut de planification aux formations non effectuées déjà récupérées
            $formations_non_effectuees_avec_planning = [];
            foreach ($formations_non_effectuees as $formation) {
                // Récupérer le statut de planification pour cette formation
                $stmt_planning = $pdo->prepare("
                    SELECT COUNT(*) as est_planifie 
                    FROM planning_formations pf 
                    WHERE pf.agent_id = ? AND pf.formation_id = ? 
                    AND pf.statut IN ('planifie', 'confirme')
                ");
                $stmt_planning->execute([$_GET['id'], $formation['id']]);
                $planning_info = $stmt_planning->fetch();
                
                $formation['est_planifie'] = $planning_info['est_planifie'];
                $formations_non_effectuees_avec_planning[] = $formation;
            }
            
            // Utiliser les formations avec info de planning
            $formations_non_effectuees = $formations_non_effectuees_avec_planning;
            
            // Grouper les formations non effectuées par catégorie
            $formations_non_effectuees_par_categorie = [
                'FAMILIARISATION (SUR-FAM)' => [],
                'FORMATION INITIALE (SUR-INI)' => [],
                'FORMATION EN COURS D\'EMPLOI (SUR-FCE)' => [],
                'FORMATION TECHNIQUE/SPÉCIALISÉE (SUR-FTS)' => []
            ];
            
            foreach ($formations_non_effectuees as $formation) {
                if (strpos($formation['code'], 'SUR-FAM') !== false) {
                    $formations_non_effectuees_par_categorie['FAMILIARISATION (SUR-FAM)'][] = $formation;
                } elseif (strpos($formation['code'], 'SUR-INI') !== false) {
                    $formations_non_effectuees_par_categorie['FORMATION INITIALE (SUR-INI)'][] = $formation;
                } elseif (strpos($formation['code'], 'SUR-FCE') !== false) {
                    $formations_non_effectuees_par_categorie['FORMATION EN COURS D\'EMPLOI (SUR-FCE)'][] = $formation;
                } elseif (strpos($formation['code'], 'SUR-FTS') !== false) {
                    $formations_non_effectuees_par_categorie['FORMATION TECHNIQUE/SPÉCIALISÉE (SUR-FTS)'][] = $formation;
                }
            }
            ?>
            
            <?php if (empty($formations_non_effectuees)): ?>
                <div class="alert alert-success">Toutes les formations ont été effectuées par cet agent.</div>
            <?php else: ?>
                <?php foreach ($formations_non_effectuees_par_categorie as $categorie => $formations_cat): ?>
                    <?php if (!empty($formations_cat)): ?>
                        <div class="mb-4">
                            <h6 class="text-warning border-bottom pb-2 mb-3"><?= $categorie ?></h6>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Formation</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($formations_cat as $formation): ?>
                                            <?php 
                                            // Vérifier si c'est une formation SUR-FTS (peut être planifiée plusieurs fois)
                                            $is_fts = strpos($formation['code'], 'SUR-FTS') !== false;
                                            $est_planifie = isset($formation['est_planifie']) && $formation['est_planifie'] > 0;
                                            ?>
                                            <tr class="formation-non-effectuee-row" data-category="<?= htmlspecialchars($formation['code']) ?>">
                                                <td>
                                                    <?= htmlspecialchars($formation['intitule']) ?>
                                                    <?php if ($est_planifie): ?>
                                                        <br><span class="badge bg-info mt-1">
                                                            <i class="fas fa-calendar-check"></i> 
                                                            <?php if ($is_fts): ?>
                                                                Planifiée (<?= $formation['est_planifie'] ?> fois)
                                                            <?php else: ?>
                                                                Déjà planifiée
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle"></i> Non effectuée
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($est_planifie && !$is_fts): ?>
                                                        <button class="btn btn-sm btn-secondary" disabled title="Formation déjà planifiée - ne peut pas être re-planifiée">
                                                            <i class="fas fa-ban"></i> Déjà planifié
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm <?= $est_planifie ? 'btn-warning' : 'btn-primary' ?>" 
                                                                onclick="planifierFormationAgent(<?= $_GET['id'] ?>, <?= $formation['id'] ?>);"
                                                                title="<?= $est_planifie ? 'Formation SUR-FTS - peut être re-planifiée' : 'Planifier cette formation' ?>">
                                                            <i class="fas fa-calendar-plus"></i> 
                                                            <?php if ($est_planifie && $is_fts): ?>
                                                                Re-planifier
                                                            <?php else: ?>
                                                                Planifier
                                                            <?php endif; ?>
                                                        </button>
                                                        <?php if ($est_planifie && $is_fts): ?>
                                                            <br><small class="text-warning mt-1">Formation technique - re-planifiable</small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formations à Renouveler -->
        <div class="agent-section" id="formations-periodiques" style="display: none;">
            <h5>Formations à Renouveler (Périodiques)</h5>
            
            <?php 
            // Calculer les formations techniques à renouveler
            $formations_techniques_a_renouveler = [];
            foreach ($formations_effectuees as $formation) {
                if (strpos($formation['code'], 'SUR-FTS') !== false) {
                    $validite_annees = intval($formation['validite_mois'] ?? 60) / 12;
                    $prochaine_echeance = date('Y-m-d', strtotime($formation['date_fin'] . ' + ' . $validite_annees . ' years'));
                    $jours_restants = (strtotime($prochaine_echeance) - time()) / (60*60*24);
                    
                    $formations_techniques_a_renouveler[] = [
                        'intitule' => $formation['intitule'],
                        'code' => $formation['code'],
                        'date_fin' => $formation['date_fin'],
                        'prochaine_echeance' => $prochaine_echeance,
                        'jours_restants' => round($jours_restants),
                        'validite_annees' => $validite_annees
                    ];
                }
            }
            ?>
            
            <?php if (empty($formations_techniques_a_renouveler)): ?>
                <div class="alert alert-success">Aucune formation technique à renouveler.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Formation</th>
                                <th>Dernière Formation</th>
                                <th>Prochaine Échéance</th>
                                <th>Jours Restants</th>
                                <th>Priorité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($formations_techniques_a_renouveler as $formation): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($formation['intitule']) ?>
                                        <br><small class="text-muted">FORMATION TECHNIQUE/SPÉCIALISÉE (SUR-FTS)</small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($formation['date_fin'])) ?></td>
                                    <td>
                                        <strong class="text-<?= $formation['jours_restants'] <= 365 ? 'danger' : 'info' ?>">
                                            <?= date('d/m/Y', strtotime($formation['prochaine_echeance'])) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge <?= $formation['jours_restants'] <= 0 ? 'bg-danger' : ($formation['jours_restants'] <= 365 ? 'bg-warning' : 'bg-info') ?>">
                                            <?= $formation['jours_restants'] <= 0 ? 'Expiré' : $formation['jours_restants'] . ' jours' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($formation['jours_restants'] <= 0): ?>
                                            <span class="badge bg-danger">Urgent</span>
                                        <?php elseif ($formation['jours_restants'] <= 365): ?>
                                            <span class="badge bg-warning">Haute</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Normale</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Planning Prévu -->
        <div class="agent-section" id="planning" style="display: none;">
            <?php 
            // Récupérer les données de planning pour cet agent spécifique
            $agent_id = $_GET['id'];
            
            // Récupérer les formations disponibles
            $stmt_formations = $pdo->prepare("SELECT id, code, intitule, categorie, periodicite_mois FROM formations ORDER BY categorie, intitule");
            $stmt_formations->execute();
            $formations = $stmt_formations->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les centres de formation
            try {
                $stmt_centres = $pdo->prepare("SELECT * FROM centres_formation ORDER BY nom");
                $stmt_centres->execute();
                $centres = $stmt_centres->fetchAll();
            } catch (PDOException $e) {
                $centres = [
                    ['id' => 1, 'nom' => 'ENAC'],
                    ['id' => 2, 'nom' => 'ERNAM'],
                    ['id' => 3, 'nom' => 'ITAerea'],
                    ['id' => 4, 'nom' => 'IFURTA'],
                    ['id' => 5, 'nom' => 'EPT'],
                    ['id' => 6, 'nom' => 'IFNPC'],
                    ['id' => 7, 'nom' => 'EMAERO services']
                ];
            }
            
            // Récupérer les besoins de formation pour cet agent
            $stmt_besoins = $pdo->prepare("
                SELECT 
                    a.id as agent_id,
                    a.matricule,
                    a.prenom,
                    a.nom,
                    f.id as formation_id,
                    f.code,
                    f.intitule,
                    f.periodicite_mois,
                    'renouveler' as type_besoin,
                    fa.date_fin as derniere_formation,
                    DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as echeance_prevue,
                    DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
                    (SELECT COUNT(*) FROM planning_formations pf 
                     WHERE pf.agent_id = a.id AND pf.formation_id = f.id 
                     AND pf.statut IN ('planifie', 'confirme')) as est_planifie
                FROM agents a
                JOIN formations_agents fa ON a.id = fa.agent_id
                JOIN formations f ON fa.formation_id = f.id
                WHERE a.id = ? 
                AND f.periodicite_mois IS NOT NULL
                AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 12 MONTH)
                AND fa.id = (
                    SELECT MAX(fa2.id) 
                    FROM formations_agents fa2 
                    WHERE fa2.agent_id = a.id AND fa2.formation_id = f.id
                )
                
                UNION ALL
                
                SELECT 
                    a.id as agent_id,
                    a.matricule,
                    a.prenom,
                    a.nom,
                    f.id as formation_id,
                    f.code,
                    f.intitule,
                    f.periodicite_mois,
                    'non_effectuee' as type_besoin,
                    NULL as derniere_formation,
                    DATE_ADD(CURDATE(), INTERVAL 3 MONTH) as echeance_prevue,
                    90 as jours_restants,
                    (SELECT COUNT(*) FROM planning_formations pf 
                     WHERE pf.agent_id = a.id AND pf.formation_id = f.id 
                     AND pf.statut IN ('planifie', 'confirme')) as est_planifie
                FROM agents a
                CROSS JOIN formations f
                WHERE a.id = ? 
                AND NOT EXISTS (
                    SELECT 1 FROM formations_agents fa 
                    WHERE fa.agent_id = a.id AND fa.formation_id = f.id
                )
                -- Inclure toutes les formations non effectuées, même si planifiées
                -- pour permettre l'affichage du statut de planification
                
                ORDER BY jours_restants ASC, type_besoin DESC
            ");
            $stmt_besoins->execute([$agent_id, $agent_id]);
            $besoins_formation = $stmt_besoins->fetchAll();
            
            // Debug: Afficher le nombre de besoins trouvés
            error_log("Besoins formation trouvés pour agent $agent_id: " . count($besoins_formation));
            if (!empty($besoins_formation)) {
                foreach ($besoins_formation as $besoin) {
                    error_log("Besoin: " . $besoin['code'] . " - " . $besoin['type_besoin']);
                }
            }
            
            // Fonction pour déterminer la catégorie de formation
            function getCategorieFormationAgent($code) {
                if (empty($code)) {
                    return 'AUTRE';
                }
                if (strpos($code, 'SUR-FAM') !== false) {
                    return 'FAMILIARISATION (SUR-FAM)';
                } elseif (strpos($code, 'SUR-INI') !== false) {
                    return 'FORMATION INITIALE (SUR-INI)';
                } elseif (strpos($code, 'SUR-FCE') !== false) {
                    return 'FORMATION EN COURS D\'EMPLOI (SUR-FCE)';
                } elseif (strpos($code, 'SUR-FTS') !== false) {
                    return 'FORMATION TECHNIQUE/SPÉCIALISÉE (SUR-FTS)';
                }
                return 'AUTRE';
            }
            ?>
            
            <!-- Navigation buttons for planning sections -->
            <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
                <button class="btn btn-primary active" onclick="showPlanningSection('besoins-agent')" id="btn-besoins-agent">
                    <i class="fas fa-exclamation-triangle"></i> Besoins de Formation
                </button>
                <button class="btn btn-outline-primary" onclick="showPlanningSection('planifier-agent')" id="btn-planifier-agent">
                    <i class="fas fa-calendar-plus"></i> Planifier Formation
                </button>
                <button class="btn btn-outline-primary" onclick="showPlanningSection('planning-agent')" id="btn-planning-agent">
                    <i class="fas fa-calendar"></i> Planning Existant
                </button>
            </div>
            
            <!-- Section Besoins de Formation pour cet agent -->
            <div class="planning-subsection" id="besoins-agent" style="display: block;">
                <h5><i class="fas fa-exclamation-triangle"></i> Besoins de Formation</h5>
                
                <?php 
            // Debug: Vérifier s'il y a des formations dans la base
            $stmt_debug = $pdo->prepare("SELECT COUNT(*) as total FROM formations");
            $stmt_debug->execute();
            $total_formations = $stmt_debug->fetch()['total'];
            
            // Debug: Vérifier s'il y a des formations_agents pour cet agent
            $stmt_debug2 = $pdo->prepare("SELECT COUNT(*) as total FROM formations_agents WHERE agent_id = ?");
            $stmt_debug2->execute([$agent_id]);
            $total_formations_agent = $stmt_debug2->fetch()['total'];
            ?>
            
            <!-- Debug info (temporaire) -->
            <div class="alert alert-warning">
                <strong>Debug:</strong> 
                Total formations: <?= $total_formations ?> | 
                Formations de cet agent: <?= $total_formations_agent ?> | 
                Besoins trouvés: <?= count($besoins_formation) ?>
            </div>
            
            <?php if (empty($besoins_formation)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i> 
                    <?php if ($total_formations == 0): ?>
                        Aucune formation disponible dans le système.
                    <?php elseif ($total_formations_agent == 0): ?>
                        Cet agent n'a effectué aucune formation. Toutes les formations sont des besoins potentiels.
                        <br><small>Vérifiez la requête SQL pour les formations non effectuées.</small>
                    <?php else: ?>
                        Aucun besoin de formation urgent identifié pour cet agent.
                        <br><small>Toutes les formations sont soit effectuées récemment, soit déjà planifiées.</small>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                    <?php 
                    // Grouper les besoins par catégorie
                    $besoins_par_categorie = [];
                    foreach ($besoins_formation as $besoin) {
                        $categorie = getCategorieFormationAgent($besoin['code']);
                        $besoins_par_categorie[$categorie][] = $besoin;
                    }
                    ?>
                    
                    <?php foreach ($besoins_par_categorie as $categorie => $besoins): ?>
                        <div class="mb-3">
                            <h6 class="text-secondary mb-2"><?= $categorie ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Formation</th>
                                            <th>Échéance</th>
                                            <th>Priorité</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($besoins as $besoin): ?>
                                            <?php 
                                            $priorite_class = '';
                                            $priorite_label = '';
                                            if ($besoin['jours_restants'] <= 0) {
                                                $priorite_class = 'table-danger';
                                                $priorite_label = 'URGENT';
                                            } elseif ($besoin['jours_restants'] <= 30) {
                                                $priorite_class = 'table-warning';
                                                $priorite_label = 'Important';
                                            } else {
                                                $priorite_class = 'table-info';
                                                $priorite_label = 'Normal';
                                            }
                                            
                                            // Vérifier si c'est une formation périodique
                                            $is_periodic = strpos($besoin['code'], 'SUR-FTS') !== false;
                                            $est_planifie = isset($besoin['est_planifie']) && $besoin['est_planifie'] > 0;
                                            ?>
                                            <tr class="<?= $priorite_class ?>">
                                                <td>
                                                    <strong><?= htmlspecialchars($besoin['code']) ?></strong><br>
                                                    <small><?= htmlspecialchars($besoin['intitule']) ?></small>
                                                    <?php if ($est_planifie): ?>
                                                        <br><span class="badge bg-info mt-1">
                                                            <i class="fas fa-calendar-check"></i> 
                                                            <?php if ($is_periodic): ?>
                                                                Planifiée (<?= $besoin['est_planifie'] ?> fois)
                                                            <?php else: ?>
                                                                Déjà planifiée
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= date('d/m/Y', strtotime($besoin['echeance_prevue'])) ?><br>
                                                    <small class="text-muted">
                                                        <?= $besoin['jours_restants'] <= 0 ? 'Échue' : $besoin['jours_restants'] . ' jours' ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $besoin['jours_restants'] <= 0 ? 'bg-danger' : ($besoin['jours_restants'] <= 30 ? 'bg-warning' : 'bg-success') ?>">
                                                        <?= $priorite_label ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($est_planifie && !$is_periodic): ?>
                                                        <button class="btn btn-sm btn-secondary" disabled title="Formation non-périodique déjà planifiée">
                                                            <i class="fas fa-ban"></i> Non re-planifiable
                                                        </button>
                                                        <br><small class="text-muted mt-1">Formation déjà planifiée</small>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm <?= $est_planifie ? 'btn-warning' : 'btn-primary' ?>" 
                                                                onclick="console.log('Button clicked for agent:', <?= $besoin['agent_id'] ?>, 'formation:', <?= $besoin['formation_id'] ?>); planifierFormationAgent(<?= $besoin['agent_id'] ?>, <?= $besoin['formation_id'] ?>);"
                                                                title="<?= $est_planifie ? 'Formation périodique - peut être re-planifiée' : 'Planifier cette formation' ?>">
                                                            <i class="fas fa-calendar-plus"></i> 
                                                            <?php if ($est_planifie): ?>
                                                                Re-planifier
                                                            <?php else: ?>
                                                                Planifier
                                                            <?php endif; ?>
                                                        </button>
                                                        <?php if ($est_planifie && $is_periodic): ?>
                                                            <br><small class="text-warning mt-1">Formation périodique</small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Section Planifier Formation pour cet agent -->
            <div class="planning-subsection" id="planifier-agent" style="display: none;">
                <h5><i class="fas fa-calendar-plus"></i> Planifier une Formation</h5>
                
                <form id="planificationFormAgent" method="POST">
                    <input type="hidden" name="agent_id" value="<?= $agent_id ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Formation <span class="text-danger">*</span></label>
                            <select name="formation_id" class="form-select" required>
                                <option value="">Sélectionner une formation</option>
                                <?php foreach ($formations as $formation): ?>
                                    <option value="<?= $formation['id'] ?>">
                                        <?= htmlspecialchars($formation['code']) ?> - <?= htmlspecialchars($formation['intitule']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Centre de Formation <span class="text-danger">*</span></label>
                            <select name="centre_formation_prevu" class="form-select" required>
                                <option value="">Sélectionner un centre</option>
                                <option value="ANACIM">ANACIM</option>
                                <?php foreach ($centres as $centre): ?>
                                    <option value="<?= htmlspecialchars($centre['nom']) ?>">
                                        <?= htmlspecialchars($centre['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date de Début <span class="text-danger">*</span></label>
                            <input type="date" name="date_prevue_debut" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date de Fin <span class="text-danger">*</span></label>
                            <input type="date" name="date_prevue_fin" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Ville <span class="text-danger">*</span></label>
                            <input type="text" name="ville" class="form-control" required placeholder="Ex: Dakar">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Pays <span class="text-danger">*</span></label>
                            <input type="text" name="pays" class="form-control" required placeholder="Ex: Sénégal">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Durée (jours) <span class="text-danger">*</span></label>
                            <input type="number" name="duree" class="form-control" required min="1" placeholder="Ex: 5">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Perdiem (FCFA)</label>
                            <input type="number" name="perdiem" class="form-control" step="0.01" min="0" placeholder="Ex: 50000">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Priorité <span class="text-danger">*</span></label>
                            <select name="priorite" class="form-select" required>
                                <option value="1">1 - Très élevé</option>
                                <option value="2">2 - Moyen</option>
                                <option value="3" selected>3 - Moins élevé</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <select name="statut" class="form-select">
                                <option value="planifie">Planifié</option>
                                <option value="confirme">Confirmé</option>
                                <option value="reporte">Reporté</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Commentaires</label>
                            <textarea name="commentaires" class="form-control" rows="2" placeholder="Commentaires optionnels..."></textarea>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Planifier la Formation
                            </button>
                            <button type="reset" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Section Planning Existant - Tous les agents -->
            <div class="planning-subsection" id="planning-agent" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-calendar"></i> Planning des Formations - Tous les Agents</h5>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="yearFilterAgent" onchange="filterByYearAgent()" style="width: auto;">
                            <option value="">Toutes les années</option>
                            <?php 
                            $current_year = date('Y');
                            for ($year = $current_year - 1; $year <= $current_year + 2; $year++): 
                            ?>
                                <option value="<?= $year ?>" <?= $year == $current_year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-success" onclick="downloadPlanningAgent()">
                            <i class="fas fa-download"></i> Télécharger
                        </button>
                    </div>
                </div>
                
                <?php 
                // Récupérer le planning de tous les agents
                $stmt_all_planning = $pdo->prepare("
                    SELECT 
                        pf.*,
                        a.matricule,
                        a.prenom,
                        a.nom,
                        f.code,
                        f.intitule,
                        cf.nom as centre_nom,
                        YEAR(pf.date_prevue_debut) as annee_formation
                    FROM planning_formations pf
                    JOIN agents a ON pf.agent_id = a.id
                    JOIN formations f ON pf.formation_id = f.id
                    LEFT JOIN centres_formation cf ON pf.centre_formation_prevu = cf.nom
                    WHERE pf.statut != 'annule'
                    ORDER BY a.nom, a.prenom, pf.date_prevue_debut ASC
                ");
                $stmt_all_planning->execute();
                $all_planning = $stmt_all_planning->fetchAll();
                ?>
                
                <?php if (empty($all_planning)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Aucune formation planifiée.
                    </div>
                <?php else: ?>
                    <?php 
                    // Grouper les plannings par agent
                    $planning_par_agent_global = [];
                    foreach ($all_planning as $planning) {
                        $agent_key = $planning['agent_id'];
                        if (!isset($planning_par_agent_global[$agent_key])) {
                            $planning_par_agent_global[$agent_key] = [
                                'agent' => $planning,
                                'formations' => []
                            ];
                        }
                        $planning_par_agent_global[$agent_key]['formations'][] = $planning;
                    }
                    ?>
                    
                    <div id="planningGlobalContent">
                        <?php foreach ($planning_par_agent_global as $agent_data): ?>
                            <div class="mb-4 agent-planning-section" data-agent-id="<?= $agent_data['agent']['agent_id'] ?>">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-user"></i> 
                                    <?= htmlspecialchars($agent_data['agent']['matricule']) ?> - 
                                    <?= htmlspecialchars($agent_data['agent']['prenom'] . ' ' . $agent_data['agent']['nom']) ?>
                                    <span class="badge bg-secondary ms-2"><?= count($agent_data['formations']) ?> formation(s)</span>
                                </h6>
                                
                                <?php 
                                // Grouper les formations par catégorie
                                $formations_par_categorie_global = [];
                                foreach ($agent_data['formations'] as $formation) {
                                    $categorie = getCategorieFormationAgent($formation['code']);
                                    $formations_par_categorie_global[$categorie][] = $formation;
                                }
                                ?>
                                
                                <?php foreach ($formations_par_categorie_global as $categorie => $formations): ?>
                                    <div class="mb-3 category-section">
                                        <h6 class="text-secondary mb-2"><?= $categorie ?></h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Formation</th>
                                                        <th>Centre</th>
                                                        <th>Lieu</th>
                                                        <th>Dates</th>
                                                        <th>Durée</th>
                                                        <th>Perdiem</th>
                                                        <th>Priorité</th>
                                                        <th>Statut</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($formations as $planning): ?>
                                                        <tr class="planning-row" data-year="<?= $planning['annee_formation'] ?>">
                                                            <td>
                                                                <strong><?= htmlspecialchars($planning['code']) ?></strong><br>
                                                                <small><?= htmlspecialchars($planning['intitule']) ?></small>
                                                            </td>
                                                            <td><?= htmlspecialchars($planning['centre_nom'] ?? $planning['centre_formation_prevu']) ?></td>
                                                            <td>
                                                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($planning['ville']) ?><br>
                                                                <small class="text-muted"><?= htmlspecialchars($planning['pays']) ?></small>
                                                            </td>
                                                            <td>
                                                                <small>
                                                                    Du <?= date('d/m/Y', strtotime($planning['date_prevue_debut'])) ?><br>
                                                                    au <?= date('d/m/Y', strtotime($planning['date_prevue_fin'])) ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-info"><?= $planning['duree'] ?> j</span>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($planning['perdiem'])): ?>
                                                                    <small><?= number_format($planning['perdiem'], 0, ',', ' ') ?> FCFA</small>
                                                                <?php else: ?>
                                                                    <small class="text-muted">-</small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?= 
                                                                    $planning['priorite'] == '1' ? 'bg-danger' : 
                                                                    ($planning['priorite'] == '2' ? 'bg-warning' : 'bg-secondary') 
                                                                ?>">
                                                                    <?= $planning['priorite'] == '1' ? 'Très élevé' : ($planning['priorite'] == '2' ? 'Moyen' : 'Moins élevé') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?= 
                                                                    $planning['statut'] == 'confirme' ? 'bg-success' : 
                                                                    ($planning['statut'] == 'reporte' ? 'bg-warning' : 'bg-primary') 
                                                                ?>">
                                                                    <?= ucfirst($planning['statut']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button class="btn btn-outline-primary" 
                                                                            onclick="modifierPlanningAgent(<?= $planning['id'] ?>)">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button class="btn btn-outline-danger" 
                                                                            onclick="supprimerPlanningAgent(<?= $planning['id'] ?>)">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section Rapports -->
        <div class="agent-section" id="rapports" style="display: none;">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Rapports de Formations
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-4">Générez et téléchargez les rapports de formations pour cet agent.</p>
                    
                    <!-- Statistiques rapides -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <h4 class="text-success"><?= count($formations_effectuees) ?></h4>
                                    <small>Formations Effectuées</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                                    <h4 class="text-danger"><?= count($formations_non_effectuees) ?></h4>
                                    <small>Non Effectuées</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                    <h4 class="text-warning"><?= count($formations_a_renouveler) ?></h4>
                                    <small>À Renouveler</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar fa-2x text-info mb-2"></i>
                                    <h4 class="text-info"><?= count($formations_planifiees) ?></h4>
                                    <small>Planifiées</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bouton de test des graphiques -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <button class="btn btn-success btn-sm" onclick="forceInitCharts()">
                                <i class="fas fa-refresh me-1"></i>
                                Actualiser les Graphiques
                            </button>
                            <small class="text-muted ms-2">Cliquez si les graphiques ne s'affichent pas</small>
                        </div>
                    </div>

                    <!-- Graphiques de l'agent -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Répartition Globale
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div style="position: relative; height: 250px;">
                                        <canvas id="agentFormationsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-warning text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-bar me-2"></i>
                                        Effectuées vs Non Effectuées
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div style="position: relative; height: 250px;">
                                        <canvas id="agentTypesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-table me-2"></i>
                                        Détail par Type de Formation
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th style="font-size: 11px;">Type</th>
                                                    <th class="text-center" style="font-size: 11px;">Eff.</th>
                                                    <th class="text-center" style="font-size: 11px;">Non Eff.</th>
                                                    <th style="font-size: 11px;">Taux</th>
                                                </tr>
                                            </thead>
                                            <tbody id="agentRealizationTable">
                                                <!-- Contenu généré par JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons de téléchargement -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-word fa-3x text-primary mb-3"></i>
                                    <h6>Document Word</h6>
                                    <p class="text-muted small">Rapport complet au format Microsoft Word (.doc)</p>
                                    <a href="ajax/generate_rapport_agent.php?agent_id=<?= $_GET['id'] ?>&format=word" 
                                       class="btn btn-primary btn-sm" target="_blank">
                                        <i class="fas fa-download me-1"></i>
                                        Télécharger Word
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                    <h6>Document PDF</h6>
                                    <p class="text-muted small">Rapport complet au format PDF (.pdf)</p>
                                    <a href="ajax/generate_rapport_agent.php?agent_id=<?= $_GET['id'] ?>&format=pdf" 
                                       class="btn btn-danger btn-sm" target="_blank">
                                        <i class="fas fa-download me-1"></i>
                                        Télécharger PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Information:</strong> Les rapports incluent toutes les formations effectuées, planifiées et non effectuées pour cet agent.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showAgentSection(sectionId) {
    console.log('Switching to section:', sectionId);
    
    // Masquer toutes les sections dans la modal
    const modal = document.querySelector('.modal-body') || document.querySelector('#agentModal .modal-body') || document;
    
    modal.querySelectorAll('.agent-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Retirer la classe active de tous les boutons de navigation
    modal.querySelectorAll('.btn-group button, [id^="btn-"]').forEach(btn => {
        btn.classList.remove('btn-primary', 'active');
        btn.classList.add('btn-outline-primary');
    });
    
    // Afficher la section sélectionnée
    const targetSection = modal.querySelector('#' + sectionId);
    if (targetSection) {
        targetSection.style.display = 'block';
        console.log('Section found and displayed:', sectionId);
    } else {
        console.error('Section not found:', sectionId);
    }
    
    // Activer le bouton correspondant
    const activeBtn = modal.querySelector('#btn-' + sectionId);
    if (activeBtn) {
        activeBtn.classList.remove('btn-outline-primary');
        activeBtn.classList.add('btn-primary', 'active');
        console.log('Button activated:', 'btn-' + sectionId);
    } else {
        console.error('Button not found:', 'btn-' + sectionId);
    }
}

// Fonctions de filtrage pour les formations
function filterFormations(category) {
    const rows = document.querySelectorAll('.formation-row');
    const buttons = document.querySelectorAll('#formations-effectuees .btn-group button');
    
    // Mettre à jour les boutons
    buttons.forEach(btn => {
        btn.classList.remove('active', 'btn-primary');
        btn.classList.add('btn-outline-primary');
    });
    event.target.classList.remove('btn-outline-primary');
    event.target.classList.add('btn-primary', 'active');
    
    // Filtrer les lignes
    rows.forEach(row => {
        if (category === 'all') {
            row.style.display = '';
        } else {
            const rowCategory = row.getAttribute('data-category');
            row.style.display = rowCategory.includes(category) ? '' : 'none';
        }
    });
}

function filterNonEffectuees(category) {
    const rows = document.querySelectorAll('.formation-non-effectuee-row');
    const buttons = document.querySelectorAll('#formations-non-effectuees .btn-group button');
    
    // Mettre à jour les boutons
    buttons.forEach(btn => {
        btn.classList.remove('active', 'btn-secondary');
        btn.classList.add('btn-outline-secondary');
    });
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('btn-secondary', 'active');
    
    // Filtrer les lignes
    rows.forEach(row => {
        if (category === 'all') {
            row.style.display = '';
        } else {
            const rowCategory = row.getAttribute('data-category');
            row.style.display = rowCategory.includes(category) ? '' : 'none';
        }
    });
}

function addDiplome(agentId) {
    console.log('Ajouter diplôme pour agent:', agentId);
}

function addFormationEffectuee(agentId) {
    console.log('Ajouter formation pour agent:', agentId);
}

function editFormationEffectuee(formationId) {
    console.log('Modifier formation:', formationId);
}

// Fonctions pour la gestion du planning dans la modal agent
function showPlanningSection(sectionId) {
    console.log('Switching to planning section:', sectionId);
    
    // Chercher dans la modal spécifiquement
    const modal = document.querySelector('#agentModal .modal-body') || document.querySelector('.modal-body') || document;
    
    // Masquer toutes les sous-sections de planning
    const planningSections = modal.querySelectorAll('.planning-subsection');
    console.log('Found planning subsections:', planningSections.length);
    
    planningSections.forEach(section => {
        section.style.display = 'none';
        section.classList.remove('active');
        console.log('Hiding section:', section.id);
    });
    
    // Retirer la classe active de tous les boutons de navigation du planning
    const planningButtons = modal.querySelectorAll('[id^="btn-"][id$="-agent"]');
    console.log('Found planning buttons:', planningButtons.length);
    
    planningButtons.forEach(btn => {
        btn.classList.remove('btn-primary', 'active');
        btn.classList.add('btn-outline-primary');
        console.log('Deactivating button:', btn.id);
    });
    
    // Afficher la sous-section sélectionnée
    const targetSection = modal.querySelector('#' + sectionId);
    console.log('Looking for section:', sectionId, 'Found:', !!targetSection);
    
    if (targetSection) {
        targetSection.style.display = 'block';
        targetSection.classList.add('active');
        console.log('Planning section displayed:', sectionId);
    } else {
        console.error('Planning section not found:', sectionId);
        // Debug: lister toutes les sections disponibles
        const allSections = modal.querySelectorAll('[id]');
        console.log('Available sections with IDs:');
        allSections.forEach(section => {
            console.log(' - ', section.id, section.className);
        });
    }
    
    // Activer le bouton correspondant
    const activeBtn = modal.querySelector('#btn-' + sectionId);
    console.log('Looking for button:', 'btn-' + sectionId, 'Found:', !!activeBtn);
    
    if (activeBtn) {
        activeBtn.classList.remove('btn-outline-primary');
        activeBtn.classList.add('btn-primary', 'active');
        console.log('Planning button activated:', 'btn-' + sectionId);
    } else {
        console.error('Planning button not found:', 'btn-' + sectionId);
        // Debug: lister tous les boutons disponibles
        const allButtons = modal.querySelectorAll('button[id]');
        console.log('Available buttons with IDs:');
        allButtons.forEach(btn => {
            console.log(' - ', btn.id, btn.className);
        });
    }
}

// Cette fonction est définie dans le fichier get_agent_details.php mais doit être accessible globalement
// Elle sera redéfinie dans admin.php pour être dans le scope global

function modifierPlanningAgent(planningId) {
    console.log('Modifier planning:', planningId);
    
    // Récupérer les détails de la planification
    fetch('ajax/get_planning_details.php?id=' + planningId)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                alert('Erreur: ' + result.message);
                return;
            }
            
            const planning = result.data;
            
            // Créer le modal de modification
            const modalHtml = `
                <div class="modal fade" id="modificationPlanningModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Modifier la Planification</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="modificationPlanningForm">
                                    <input type="hidden" name="planning_id" value="${planning.id}">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Agent</label>
                                        <input type="text" class="form-control" value="${planning.matricule} - ${planning.prenom} ${planning.nom}" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Formation</label>
                                        <input type="text" class="form-control" value="${planning.code} - ${planning.intitule}" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Centre de formation *</label>
                                        <select class="form-select" name="centre_formation_prevu" required>
                                            <option value="">Sélectionner...</option>
                                            <option value="ANACIM" ${planning.centre_formation_prevu === 'ANACIM' ? 'selected' : ''}>ANACIM</option>
                                            <option value="ENAC" ${planning.centre_formation_prevu === 'ENAC' ? 'selected' : ''}>ENAC</option>
                                            <option value="ERNAM" ${planning.centre_formation_prevu === 'ERNAM' ? 'selected' : ''}>ERNAM</option>
                                            <option value="ITAerea" ${planning.centre_formation_prevu === 'ITAerea' ? 'selected' : ''}>ITAerea</option>
                                            <option value="IFURTA" ${planning.centre_formation_prevu === 'IFURTA' ? 'selected' : ''}>IFURTA</option>
                                            <option value="EPT" ${planning.centre_formation_prevu === 'EPT' ? 'selected' : ''}>EPT</option>
                                            <option value="IFNPC" ${planning.centre_formation_prevu === 'IFNPC' ? 'selected' : ''}>IFNPC</option>
                                            <option value="EMAERO services" ${planning.centre_formation_prevu === 'EMAERO services' ? 'selected' : ''}>EMAERO services</option>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Date de début *</label>
                                                <input type="date" class="form-control" name="date_prevue_debut" value="${planning.date_prevue_debut}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Date de fin *</label>
                                                <input type="date" class="form-control" name="date_prevue_fin" value="${planning.date_prevue_fin}" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Statut</label>
                                        <select class="form-select" name="statut">
                                            <option value="planifie" ${planning.statut === 'planifie' ? 'selected' : ''}>Planifié</option>
                                            <option value="confirme" ${planning.statut === 'confirme' ? 'selected' : ''}>Confirmé</option>
                                            <option value="reporte" ${planning.statut === 'reporte' ? 'selected' : ''}>Reporté</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Commentaires</label>
                                        <textarea class="form-control" name="commentaires" rows="3">${planning.commentaires || ''}</textarea>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="button" class="btn btn-primary" onclick="sauvegarderModificationPlanning()">Enregistrer</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Ajouter le modal au DOM s'il n'existe pas
            if (!document.getElementById('modificationPlanningModal')) {
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            } else {
                document.getElementById('modificationPlanningModal').outerHTML = modalHtml;
            }
            
            // Afficher le modal
            new bootstrap.Modal(document.getElementById('modificationPlanningModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors du chargement des détails de la planification.');
        });
}

// Fonction pour sauvegarder la modification de planning
function sauvegarderModificationPlanning() {
    const form = document.getElementById('modificationPlanningForm');
    const formData = new FormData(form);
    
    fetch('ajax/update_planning.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Planification modifiée avec succès!');
            bootstrap.Modal.getInstance(document.getElementById('modificationPlanningModal')).hide();
            
            // Recharger les détails de l'agent
            const agentIdInput = document.querySelector('input[name="agent_id"]');
            if (agentIdInput && agentIdInput.value) {
                const agentId = agentIdInput.value;
                if (typeof loadAgentDetails === 'function') {
                    loadAgentDetails(agentId);
                } else if (typeof viewAgent === 'function') {
                    viewAgent(agentId);
                } else {
                    location.reload();
                }
            } else {
                location.reload();
            }
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Une erreur est survenue lors de la modification.');
    });
}

function supprimerPlanningAgent(planningId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette planification ?')) {
        fetch('ajax/delete_planning.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                planning_id: planningId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Planification supprimée avec succès !');
                // Recharger les détails de l'agent pour mettre à jour l'affichage
                const agentIdInput = document.querySelector('input[name="agent_id"]');
                if (agentIdInput && agentIdInput.value) {
                    const agentId = agentIdInput.value;
                    // Appeler la fonction globale loadAgentDetails si elle existe
                    if (typeof loadAgentDetails === 'function') {
                        loadAgentDetails(agentId);
                    } else if (typeof viewAgent === 'function') {
                        viewAgent(agentId);
                    } else {
                        // Recharger la page en dernier recours
                        location.reload();
                    }
                } else {
                    // Recharger juste cette section du planning
                    location.reload();
                }
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue lors de la suppression.');
        });
    }
}

// Fonction globale pour initialiser les event listeners du planning
function initializePlanningEventListeners() {
    console.log('🔧 Initializing planning event listeners...');
    
    // Attendre un peu pour s'assurer que les éléments sont dans le DOM
    setTimeout(() => {
        const planificationFormAgent = document.getElementById('planificationFormAgent');
        console.log('📝 Planning form found:', !!planificationFormAgent);
        
        if (planificationFormAgent) {
            // Supprimer l'ancien listener s'il existe
            const oldListener = planificationFormAgent.getAttribute('data-listener-added');
            if (oldListener) {
                console.log('🔄 Removing old listener...');
                planificationFormAgent.removeAttribute('data-listener-added');
            }
            
            planificationFormAgent.setAttribute('data-listener-added', 'true');
            planificationFormAgent.addEventListener('submit', function(e) {
                console.log('📤 Form submission started...');
                e.preventDefault();
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                // Validation des champs obligatoires
                const requiredFields = ['formation_id', 'centre_formation_prevu', 'date_prevue_debut', 'date_prevue_fin', 'ville', 'pays', 'duree', 'priorite'];
                let missingFields = [];
                
                requiredFields.forEach(field => {
                    const input = this.querySelector(`[name="${field}"]`);
                    if (!input || !input.value.trim()) {
                        missingFields.push(field);
                    }
                });
                
                if (missingFields.length > 0) {
                    alert('❌ Champs obligatoires manquants: ' + missingFields.join(', '));
                    return;
                }
                
                // Validation des dates
                const dateDebut = new Date(this.querySelector('input[name="date_prevue_debut"]').value);
                const dateFin = new Date(this.querySelector('input[name="date_prevue_fin"]').value);
                
                if (dateFin <= dateDebut) {
                    alert('❌ La date de fin doit être postérieure à la date de début.');
                    return;
                }
                
                console.log('✅ Validation passed, submitting...');
                
                // Désactiver le bouton
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Planification...';
                
                const formData = new FormData(this);
                
                // Debug: afficher les données envoyées
                console.log('📋 Form data:');
                for (let [key, value] of formData.entries()) {
                    console.log(`  ${key}: ${value}`);
                }
                
                fetch('ajax/save_planning.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Récupérer les informations pour le message
                        const formationSelect = document.querySelector('#planifier-agent select[name="formation_id"]');
                        const formationText = formationSelect ? formationSelect.options[formationSelect.selectedIndex].text : 'la formation';
                        const centreSelect = document.querySelector('#planifier-agent select[name="centre_formation_prevu"]');
                        const centreText = centreSelect ? centreSelect.options[centreSelect.selectedIndex].text : '';
                        const dateDebut = document.querySelector('#planifier-agent input[name="date_prevue_debut"]').value;
                        const dateFin = document.querySelector('#planifier-agent input[name="date_prevue_fin"]').value;
                        
                        // Message de succès
                        alert(`✅ PLANNING ENREGISTRÉ AVEC SUCCÈS !

📚 Formation : ${formationText}
🏢 Centre : ${centreText}
📅 Dates : Du ${new Date(dateDebut).toLocaleDateString('fr-FR')} au ${new Date(dateFin).toLocaleDateString('fr-FR')}

La formation a été ajoutée au planning de l'agent.`);
                        
                        // Réinitialiser le formulaire (this au lieu de form)
                        planificationFormAgent.reset();
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        submitBtn.className = 'btn btn-primary';
                        
                        // Récupérer l'ID de l'agent
                        const agentId = formData.get('agent_id');
                        
                        // Recharger complètement les détails de l'agent
                        if (agentId) {
                            // Fermer et rouvrir la modal pour forcer le rechargement
                            const modal = bootstrap.Modal.getInstance(document.getElementById('agentModal'));
                            if (modal) {
                                modal.hide();
                            }
                            
                            // Attendre que la modal soit fermée, puis la rouvrir
                            setTimeout(() => {
                                // Recharger les détails de l'agent
                                if (typeof window.parent.viewAgent === 'function') {
                                    window.parent.viewAgent(agentId);
                                } else if (typeof viewAgent === 'function') {
                                    viewAgent(agentId);
                                } else {
                                    // En dernier recours, recharger via AJAX
                                    fetch('ajax/get_agent_details.php?id=' + agentId)
                                        .then(response => response.text())
                                        .then(html => {
                                            document.querySelector('#agentModal .modal-body').innerHTML = html;
                                            // Aller directement à la section Planning Existant
                                            setTimeout(() => {
                                                showAgentSection('planning');
                                                setTimeout(() => showPlanningSection('planning-agent'), 200);
                                            }, 300);
                                        });
                                }
                            }, 500);
                        }
                    } else {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        submitBtn.className = 'btn btn-primary';
                        
                        // Message d'erreur simple
                        alert(`❌ ERREUR DE PLANIFICATION

${data.message}

Veuillez vérifier les informations et réessayer.`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    submitBtn.className = 'btn btn-primary d-block w-100';
                    alert('Une erreur est survenue: ' + error.message);
                });
            });
        }
    }, 500);
}

// Appeler l'initialisation quand le contenu de la modal est chargé
document.addEventListener('DOMContentLoaded', function() {
    initializePlanningEventListeners();
});

// Aussi appeler après le chargement du contenu de l'agent
window.addEventListener('agentContentLoaded', function() {
    initializePlanningEventListeners();
});

// Fonction pour filtrer par année dans la section planning global
function filterByYearAgent() {
    console.log('=== DEBUT FILTRAGE PAR ANNEE ===');
    
    // Toujours chercher dans la modal car c'est là que se trouve le planning
    const modalBody = document.querySelector('#agentModal .modal-body');
    if (!modalBody) {
        console.error('Modal body non trouvé');
        return;
    }
    
    const yearFilter = modalBody.querySelector('#yearFilterAgent');
    if (!yearFilter) {
        console.error('Filtre année non trouvé dans la modal');
        return;
    }
    
    const selectedYear = yearFilter.value;
    console.log('Année sélectionnée:', selectedYear);
    
    // Chercher les sections d'agents dans la modal
    const agentSections = modalBody.querySelectorAll('.agent-planning-section');
    console.log('Sections d\'agents trouvées:', agentSections.length);
    
    agentSections.forEach((section, index) => {
        console.log(`Traitement section ${index + 1}:`, section);
        
        if (!selectedYear) {
            // Afficher toutes les sections si aucune année n'est sélectionnée
            section.style.display = '';
            const rows = section.querySelectorAll('tr[data-year]');
            console.log(`Section ${index + 1} - Lignes trouvées:`, rows.length);
            rows.forEach(row => {
                row.style.display = '';
                console.log('Ligne affichée:', row.getAttribute('data-year'));
            });
            
            // Afficher toutes les catégories
            const categories = section.querySelectorAll('.category-section');
            categories.forEach(cat => cat.style.display = '');
        } else {
            // Vérifier si cette section d'agent a des formations pour l'année sélectionnée
            const rows = section.querySelectorAll('tr[data-year]');
            let hasFormationsInYear = false;
            
            console.log(`Section ${index + 1} - Filtrage pour année ${selectedYear}, lignes:`, rows.length);
            
            rows.forEach(row => {
                const rowYear = row.getAttribute('data-year');
                console.log(`Ligne année: ${rowYear}, recherchée: ${selectedYear}`);
                if (rowYear === selectedYear) {
                    row.style.display = '';
                    hasFormationsInYear = true;
                    console.log('Ligne affichée pour année correspondante');
                } else {
                    row.style.display = 'none';
                    console.log('Ligne masquée');
                }
            });
            
            // Masquer toute la section de l'agent s'il n'a pas de formations cette année
            if (hasFormationsInYear) {
                section.style.display = '';
                console.log(`Section ${index + 1} affichée (a des formations)`);
                
                // Afficher/masquer les catégories selon qu'elles ont des formations visibles
                const categoryDivs = section.querySelectorAll('.category-section');
                categoryDivs.forEach(categoryDiv => {
                    const visibleRows = categoryDiv.querySelectorAll('tr[data-year]:not([style*="none"])');
                    if (visibleRows.length > 0) {
                        categoryDiv.style.display = '';
                    } else {
                        categoryDiv.style.display = 'none';
                    }
                });
            } else {
                section.style.display = 'none';
                console.log(`Section ${index + 1} masquée (pas de formations)`);
            }
        }
    });
    
    console.log('=== FIN FILTRAGE PAR ANNEE ===');
}

// Fonction pour télécharger le planning
function downloadPlanningAgent() {
    console.log('=== DEBUT TELECHARGEMENT ===');
    
    // Chercher dans la modal
    const modalBody = document.querySelector('#agentModal .modal-body');
    if (!modalBody) {
        alert('Erreur: Modal non trouvée');
        return;
    }
    
    const yearFilter = modalBody.querySelector('#yearFilterAgent');
    const planningContent = modalBody.querySelector('#planningGlobalContent');
    
    console.log('Filtre année trouvé:', !!yearFilter);
    console.log('Contenu planning trouvé:', !!planningContent);
    
    if (!planningContent) {
        alert('Aucun contenu de planning à télécharger.');
        return;
    }
    
    const selectedYear = yearFilter ? yearFilter.value : '';
    const yearText = selectedYear ? ` - Année ${selectedYear}` : '';
    
    console.log('Téléchargement pour l\'année:', selectedYear || 'toutes');
    
    // Cloner le contenu pour le manipuler sans affecter l'affichage
    const contentClone = planningContent.cloneNode(true);
    
    // Si un filtre d'année est actif, supprimer les éléments masqués
    if (selectedYear) {
        // Supprimer les sections d'agents masquées
        contentClone.querySelectorAll('.agent-planning-section').forEach(section => {
            if (section.style.display === 'none') {
                section.remove();
            } else {
                // Supprimer les catégories masquées
                section.querySelectorAll('.category-section').forEach(cat => {
                    if (cat.style.display === 'none') {
                        cat.remove();
                    } else {
                        // Supprimer les lignes masquées
                        cat.querySelectorAll('.planning-row').forEach(row => {
                            if (row.style.display === 'none') {
                                row.remove();
                            }
                        });
                    }
                });
            }
        });
    }
    
    // Supprimer les boutons d'action dans le clone
    contentClone.querySelectorAll('.btn-group').forEach(btnGroup => btnGroup.remove());
    
    const printContent = `
        <html>
        <head>
            <title>Planning des Formations${yearText}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { max-height: 60px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #124c97; color: white; font-weight: bold; }
                .text-primary { color: #124c97; font-weight: bold; }
                .text-secondary { color: #6c757d; font-style: italic; }
                .border-bottom { border-bottom: 2px solid #124c97; padding-bottom: 5px; margin-bottom: 10px; }
                .mb-3, .mb-4 { margin-bottom: 15px; }
                .badge { 
                    display: inline-block; 
                    padding: 3px 8px; 
                    border-radius: 3px; 
                    font-size: 11px;
                    font-weight: bold;
                }
                .bg-success { background-color: #28a745; color: white; }
                .bg-primary { background-color: #124c97; color: white; }
                .bg-warning { background-color: #ffc107; color: black; }
                .bg-secondary { background-color: #6c757d; color: white; }
                h6 { margin-top: 15px; margin-bottom: 8px; }
                @media print { 
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <img src="logo-anacim.png" alt="ANACIM" class="logo">
                <h1>PLANNING DES FORMATIONS${yearText}</h1>
                <p>Généré le ${new Date().toLocaleDateString('fr-FR')}</p>
            </div>
            ${contentClone.innerHTML}
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Attendre que le document soit chargé avant d'imprimer
    printWindow.onload = function() {
        console.log('Document prêt pour l\'impression');
        printWindow.print();
    };
    
    // Fallback si onload ne fonctionne pas
    setTimeout(() => {
        console.log('Tentative d\'impression via fallback');
        printWindow.print();
    }, 1000);
    
    console.log('=== FIN TELECHARGEMENT ===');
}

function deleteDiplome(diplomeId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce diplôme ?')) {
        console.log('Supprimer diplôme:', diplomeId);
    }
}

// Fonction pour initialiser les graphiques de l'agent
function initAgentCharts() {
    console.log('🔧 Initialisation des graphiques agent...');
    
    // Vérifier si Chart.js est disponible
    if (typeof Chart === 'undefined') {
        console.error('❌ Chart.js n\'est pas disponible');
        return;
    }
    
    // Données pour les graphiques de cet agent spécifique
    const agentData = {
        effectuees: <?= count($formations_effectuees) ?>,
        non_effectuees: <?= count($formations_non_effectuees) ?>,
        a_renouveler: <?= count($formations_a_renouveler) ?>,
        planifiees: <?= count($formations_planifiees) ?>
    };
    
    console.log('📊 Données agent:', agentData);

    // Données par type de formation pour cet agent (effectuées et non effectuées)
    const typesData = {
        <?php
        // Fonction pour mapper les catégories de la base de données vers les codes
        function mapCategorieToCode($categorie, $code) {
            // Priorité au code si disponible (plus précis)
            if (!empty($code)) {
                if (strpos($code, 'SUR-FAM') !== false) return 'FAMILIARISATION';
                if (strpos($code, 'SUR-INI') !== false) return 'FORMATION_INITIALE';
                if (strpos($code, 'SUR-FCE') !== false) return 'FORMATION_COURS_EMPLOI';
                if (strpos($code, 'SUR-FTS') !== false) return 'FORMATION_TECHNIQUE';
            }
            
            // Fallback sur la catégorie de la base de données
            switch ($categorie) {
                case 'FAMILIARISATION': return 'FAMILIARISATION';
                case 'FORMATION_INITIALE': return 'FORMATION_INITIALE';
                case 'FORMATION_COURS_EMPLOI': return 'FORMATION_COURS_EMPLOI';
                case 'FORMATION_TECHNIQUE': return 'FORMATION_TECHNIQUE';
                default: return 'AUTRE';
            }
        }
        
        // Compter les formations effectuées par type
        $types_effectuees = [
            'FAMILIARISATION' => 0,
            'FORMATION_INITIALE' => 0,
            'FORMATION_COURS_EMPLOI' => 0,
            'FORMATION_TECHNIQUE' => 0
        ];
        
        // Éviter les doublons en gardant trace des formations déjà comptées
        $formations_comptees = [];
        
        foreach ($formations_effectuees as $formation) {
            $formation_id = $formation['formation_id'];
            
            // Éviter les doublons entre formations_effectuees et formations_agents
            if (isset($formations_comptees[$formation_id])) {
                continue;
            }
            $formations_comptees[$formation_id] = true;
            
            $type = mapCategorieToCode($formation['categorie'], $formation['code']);
            if (isset($types_effectuees[$type])) {
                $types_effectuees[$type]++;
            }
        }
        
        // Compter les formations non effectuées par type
        $types_non_effectuees = [
            'FAMILIARISATION' => 0,
            'FORMATION_INITIALE' => 0,
            'FORMATION_COURS_EMPLOI' => 0,
            'FORMATION_TECHNIQUE' => 0
        ];
        
        foreach ($formations_non_effectuees as $formation) {
            $type = mapCategorieToCode($formation['categorie'], $formation['code']);
            if (isset($types_non_effectuees[$type])) {
                $types_non_effectuees[$type]++;
            }
        }
        ?>
        effectuees: {
            familiarisation: <?= $types_effectuees['FAMILIARISATION'] ?>,
            initiale: <?= $types_effectuees['FORMATION_INITIALE'] ?>,
            cours_emploi: <?= $types_effectuees['FORMATION_COURS_EMPLOI'] ?>,
            technique: <?= $types_effectuees['FORMATION_TECHNIQUE'] ?>
        },
        non_effectuees: {
            familiarisation: <?= $types_non_effectuees['FAMILIARISATION'] ?>,
            initiale: <?= $types_non_effectuees['FORMATION_INITIALE'] ?>,
            cours_emploi: <?= $types_non_effectuees['FORMATION_COURS_EMPLOI'] ?>,
            technique: <?= $types_non_effectuees['FORMATION_TECHNIQUE'] ?>
        }
    };
    
    console.log('📊 Données types détaillées:', typesData);

    // Couleurs pour les graphiques
    const colors = {
        primary: '#0d6efd',
        success: '#198754',
        danger: '#dc3545',
        warning: '#ffc107',
        info: '#0dcaf0',
        secondary: '#6c757d'
    };

    // Graphique 1: Répartition globale des formations de l'agent
    const ctx1 = document.getElementById('agentFormationsChart');
    console.log('🔍 Recherche canvas agentFormationsChart:', !!ctx1);
    if (ctx1) {
        console.log('✅ Canvas trouvé, création du graphique formations...');
        try {
            agentFormationsChartInstance = new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Effectuées', 'Non Effectuées', 'À Renouveler', 'Planifiées'],
                datasets: [{
                    data: [agentData.effectuees, agentData.non_effectuees, agentData.a_renouveler, agentData.planifiees],
                    backgroundColor: [colors.success, colors.danger, colors.warning, colors.info],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return {
                                            text: `${label}: ${value} (${percentage}%)`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: data.datasets[0].borderColor,
                                            lineWidth: data.datasets[0].borderWidth,
                                            hidden: false,
                                            index: i,
                                            pointStyle: 'circle'
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed * 100) / total).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
            console.log('✅ Graphique formations créé avec succès');
        } catch (error) {
            console.error('❌ Erreur création graphique formations:', error);
        }
    } else {
        console.error('❌ Canvas agentFormationsChart non trouvé');
    }

    // Graphique 2: Formations effectuées vs non effectuées par type
    const ctx2 = document.getElementById('agentTypesChart');
    console.log('🔍 Recherche canvas agentTypesChart:', !!ctx2);
    if (ctx2) {
        console.log('✅ Canvas trouvé, création du graphique types...');
        try {
            agentTypesChartInstance = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Familiarisation', 'Formation Initiale', 'Cours d\'Emploi', 'Technique'],
                    datasets: [
                        {
                            label: 'Effectuées',
                            data: [
                                typesData.effectuees.familiarisation, 
                                typesData.effectuees.initiale, 
                                typesData.effectuees.cours_emploi, 
                                typesData.effectuees.technique
                            ],
                            backgroundColor: colors.success,
                            borderColor: colors.success,
                            borderWidth: 1
                        },
                        {
                            label: 'Non Effectuées',
                            data: [
                                typesData.non_effectuees.familiarisation, 
                                typesData.non_effectuees.initiale, 
                                typesData.non_effectuees.cours_emploi, 
                                typesData.non_effectuees.technique
                            ],
                            backgroundColor: colors.danger,
                            borderColor: colors.danger,
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return 'Type: ' + context[0].label;
                                },
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y;
                                    return `${label}: ${value} formations`;
                                },
                                afterLabel: function(context) {
                                    // Calculer le pourcentage pour ce type de formation
                                    const dataIndex = context.dataIndex;
                                    const chart = context.chart;
                                    const effectuees = chart.data.datasets[0].data[dataIndex];
                                    const nonEffectuees = chart.data.datasets[1].data[dataIndex];
                                    const total = effectuees + nonEffectuees;
                                    
                                    if (total > 0) {
                                        const percentage = ((context.parsed.y / total) * 100).toFixed(1);
                                        return `${percentage}% du total (${total})`;
                                    }
                                    return '';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            title: {
                                display: true,
                                text: 'Nombre de formations'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Types de formation'
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'barValues',
                    afterDatasetsDraw: function(chart) {
                        const ctx = chart.ctx;
                        ctx.font = '10px Arial';
                        ctx.fillStyle = '#000';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';

                        chart.data.datasets.forEach((dataset, datasetIndex) => {
                            const meta = chart.getDatasetMeta(datasetIndex);
                            meta.data.forEach((bar, index) => {
                                const value = dataset.data[index];
                                if (value > 0) {
                                    ctx.fillText(value, bar.x, bar.y - 5);
                                }
                            });
                        });
                    }
                }]
            });
            console.log('✅ Graphique types créé avec succès');
        } catch (error) {
            console.error('❌ Erreur création graphique types:', error);
        }
    } else {
        console.error('❌ Canvas agentTypesChart non trouvé');
    }

    // Tableau 3: Détail par type de formation avec barres de progression
    const tableBody = document.getElementById('agentRealizationTable');
    console.log('🔍 Recherche tableau agentRealizationTable:', !!tableBody);
    if (tableBody) {
        console.log('✅ Tableau trouvé, génération du contenu...');
        try {
            const labels = ['Familiarisation', 'Formation Initiale', 'Cours d\'Emploi', 'Technique'];
            const types = ['familiarisation', 'initiale', 'cours_emploi', 'technique'];
            
            let tableHTML = '';
            
            types.forEach((type, index) => {
                const effectuees = typesData.effectuees[type];
                const non_effectuees = typesData.non_effectuees[type];
                const total = effectuees + non_effectuees;
                const taux = total > 0 ? ((effectuees / total) * 100).toFixed(1) : 0;
                
                // Nom court pour le type
                let shortLabel = labels[index];
                if (shortLabel === 'Formation Initiale') shortLabel = 'F. Initiale';
                if (shortLabel === 'Cours d\'Emploi') shortLabel = 'C. Emploi';
                if (shortLabel === 'Formation Technique') shortLabel = 'F. Technique';
                
                tableHTML += `
                    <tr>
                        <td style="font-size: 10px;"><strong>${shortLabel}</strong></td>
                        <td class="text-center">
                            <span class="badge bg-success" style="font-size: 9px;">${effectuees}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-danger" style="font-size: 9px;">${non_effectuees}</span>
                        </td>
                        <td style="width: 100px;">
                            <div class="progress" style="height: 15px; font-size: 9px;">
                                <div class="progress-bar bg-success" style="width: ${taux}%" 
                                     data-bs-toggle="tooltip" 
                                     title="Effectuées: ${effectuees}, Non effectuées: ${non_effectuees}, Total: ${total}">
                                    ${taux}%
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tableBody.innerHTML = tableHTML;
            
            // Initialiser les tooltips Bootstrap si disponible
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = tableBody.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipTriggerList.forEach(tooltipTriggerEl => {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
            
            console.log('✅ Tableau détail par type créé avec succès');
        } catch (error) {
            console.error('❌ Erreur création tableau détail par type:', error);
        }
    } else {
        console.error('❌ Tableau agentRealizationTable non trouvé');
    }
}

// Variables globales pour les graphiques
let agentFormationsChartInstance = null;
let agentTypesChartInstance = null;

// Fonction pour forcer l'initialisation des graphiques (bouton de test)
function forceInitCharts() {
    console.log('🔄 Force initialisation des graphiques...');
    destroyAgentCharts();
    setTimeout(initAgentCharts, 100);
}

// Fonction pour détruire les graphiques existants
function destroyAgentCharts() {
    if (agentFormationsChartInstance) {
        agentFormationsChartInstance.destroy();
        agentFormationsChartInstance = null;
    }
    if (agentTypesChartInstance) {
        agentTypesChartInstance.destroy();
        agentTypesChartInstance = null;
    }
}

// Initialiser les graphiques quand la section rapports est affichée
function initChartsWhenReady() {
    // Vérifier si Chart.js est disponible et si les éléments canvas existent
    const canvas1 = document.getElementById('agentFormationsChart');
    const canvas2 = document.getElementById('agentTypesChart');
    
    if (typeof Chart !== 'undefined' && canvas1 && canvas2) {
        // Détruire les graphiques existants
        destroyAgentCharts();
        
        // Attendre un peu pour s'assurer que les éléments sont visibles
        setTimeout(() => {
            initAgentCharts();
        }, 200);
    } else if (typeof Chart === 'undefined') {
        // Charger Chart.js si pas encore chargé
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = function() {
            setTimeout(() => {
                initAgentCharts();
            }, 300);
        };
        document.head.appendChild(script);
    }
}

// Initialiser au chargement du document
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 Document chargé dans la modal');
    setTimeout(initChartsWhenReady, 1000);
});

// Écouter l'événement personnalisé de chargement du contenu agent
window.addEventListener('agentContentLoaded', function() {
    console.log('👤 Contenu agent chargé, initialisation des graphiques...');
    setTimeout(initChartsWhenReady, 1000);
});

// Initialisation immédiate si le DOM est déjà prêt
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    console.log('📄 DOM déjà prêt, initialisation immédiate des graphiques');
    setTimeout(initChartsWhenReady, 500);
}

// Réinitialiser les graphiques quand on change de section
if (typeof showAgentSection === 'function') {
    const originalShowAgentSection = showAgentSection;
    showAgentSection = function(sectionId) {
        originalShowAgentSection(sectionId);
        if (sectionId === 'rapports') {
            setTimeout(initChartsWhenReady, 300);
        }
    };
} else {
    // Définir la fonction si elle n'existe pas encore
    window.showAgentSection = function(sectionId) {
        console.log('Switching to section:', sectionId);
        
        // Masquer toutes les sections
        document.querySelectorAll('.agent-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Retirer la classe active de tous les boutons
        document.querySelectorAll('.btn-group button, [id^="btn-"]').forEach(btn => {
            btn.classList.remove('btn-primary', 'active');
            btn.classList.add('btn-outline-primary');
        });
        
        // Afficher la section sélectionnée
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.style.display = 'block';
        }
        
        // Activer le bouton correspondant
        const activeBtn = document.getElementById('btn-' + sectionId);
        if (activeBtn) {
            activeBtn.classList.remove('btn-outline-primary');
            activeBtn.classList.add('btn-primary', 'active');
        }
        
        // Initialiser les graphiques si c'est la section rapports
        if (sectionId === 'rapports') {
            console.log('🎯 Section rapports activée, initialisation des graphiques...');
            setTimeout(initChartsWhenReady, 500);
        }
    };
}

// Le gestionnaire de planification est maintenant dans admin.php
// pour éviter les problèmes de chargement dynamique via AJAX
</script>
