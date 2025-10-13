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

// Récupérer les formations depuis la nouvelle table
$pdo = $database->getConnection();
$stmt_formations = $pdo->prepare("SELECT fa.*, f.code, f.intitule FROM formations_agents fa JOIN formations f ON fa.formation_id = f.id WHERE fa.agent_id = ? ORDER BY fa.created_at DESC");
$stmt_formations->execute([$_GET['id']]);
$formations_effectuees = $stmt_formations->fetchAll();

// Récupérer les diplômes depuis la nouvelle table
$stmt_diplomes = $pdo->prepare("SELECT * FROM diplomes WHERE agent_id = ? ORDER BY created_at DESC");
$stmt_diplomes->execute([$_GET['id']]);
$diplomes = $stmt_diplomes->fetchAll();

$formations_a_renouveler = $agent->getFormationsARenouveler($_GET['id']);
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
                            <th>Spécialiste</th>
                            <td><?= htmlspecialchars($agent_data['specialiste'] ?? '') ?></td>
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
                        <img src="<?= htmlspecialchars($agent_data['photo']) ?>" 
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
            // Récupérer toutes les formations disponibles
            $stmt_all_formations = $pdo->prepare("SELECT * FROM formations ORDER BY code");
            $stmt_all_formations->execute();
            $all_formations = $stmt_all_formations->fetchAll();
            
            // Récupérer les IDs des formations déjà effectuées par cet agent
            $formations_effectuees_ids = array_column($formations_effectuees, 'formation_id');
            
            // Filtrer les formations non effectuées
            $formations_non_effectuees = array_filter($all_formations, function($formation) use ($formations_effectuees_ids) {
                return !in_array($formation['id'], $formations_effectuees_ids);
            });
            
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($formations_cat as $formation): ?>
                                            <tr class="formation-non-effectuee-row" data-category="<?= htmlspecialchars($formation['code']) ?>">
                                                <td><?= htmlspecialchars($formation['intitule']) ?></td>
                                                <td>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle"></i> Non effectuée
                                                    </span>
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
            <h5>Planning des Formations</h5>
            
            <?php 
            // Récupérer les formations techniques qui nécessitent un renouvellement
            $formations_techniques_renouvellement = [];
            foreach ($formations_effectuees as $formation) {
                if (strpos($formation['code'], 'SUR-FTS') !== false) {
                    $validite_annees = intval($formation['validite_mois'] ?? 60) / 12;
                    $prochaine_echeance = date('Y-m-d', strtotime($formation['date_fin'] . ' + ' . $validite_annees . ' years'));
                    $jours_restants = (strtotime($prochaine_echeance) - time()) / (60*60*24);
                    
                    if ($jours_restants <= 730) { // 2 ans avant expiration
                        $formations_techniques_renouvellement[] = [
                            'formation' => $formation,
                            'prochaine_echeance' => $prochaine_echeance,
                            'jours_restants' => $jours_restants,
                            'type' => 'renouvellement'
                        ];
                    }
                }
            }
            
            // Récupérer les formations non effectuées prioritaires
            $formations_prioritaires = array_slice($formations_non_effectuees, 0, 5);
            ?>
            
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">Formations à Renouveler (Techniques)</h6>
                    <?php if (empty($formations_techniques_renouvellement)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Aucun renouvellement prévu dans les 2 prochaines années.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Formation</th>
                                        <th>Échéance</th>
                                        <th>Priorité</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($formations_techniques_renouvellement as $item): ?>
                                        <tr>
                                            <td>
                                                <small><?= htmlspecialchars($item['formation']['intitule']) ?></small>
                                                <br><code class="small"><?= htmlspecialchars($item['formation']['code']) ?></code>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($item['prochaine_echeance'])) ?></td>
                                            <td>
                                                <?php if ($item['jours_restants'] <= 365): ?>
                                                    <span class="badge bg-danger">Urgent</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Programmée</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <h6 class="text-warning">Formations Non Effectuées (Prioritaires)</h6>
                    <?php if (empty($formations_prioritaires)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Toutes les formations prioritaires sont effectuées.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Formation</th>
                                        <th>Catégorie</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($formations_prioritaires as $formation): ?>
                                        <tr>
                                            <td>
                                                <small><?= htmlspecialchars($formation['intitule']) ?></small>
                                                <br><code class="small"><?= htmlspecialchars($formation['code']) ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary small">
                                                    <?php 
                                                    if (strpos($formation['code'], 'SUR-FAM') !== false) echo 'FAM';
                                                    elseif (strpos($formation['code'], 'SUR-INI') !== false) echo 'INI';
                                                    elseif (strpos($formation['code'], 'SUR-FCE') !== false) echo 'FCE';
                                                    elseif (strpos($formation['code'], 'SUR-FTS') !== false) echo 'FTS';
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning small">
                                                    <i class="fas fa-clock"></i> À planifier
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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

function deleteDiplome(diplomeId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce diplôme ?')) {
        console.log('Supprimer diplôme:', diplomeId);
    }
}
</script>
