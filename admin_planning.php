<?php
error_reporting(E_ERROR | E_PARSE);
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'classes/Agent.php';
require_once 'classes/Formation.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer les agents
$stmt = $db->prepare("SELECT * FROM agents ORDER BY nom, prenom");
$stmt->execute();
$agents = $stmt->fetchAll();

// Récupérer les formations
$stmt = $db->prepare("SELECT id, code, intitule, categorie, periodicite_mois FROM formations ORDER BY categorie, intitule");
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les centres de formation (avec fallback si la table n'existe pas)
try {
    $stmt = $db->prepare("SELECT * FROM centres_formation ORDER BY nom");
    $stmt->execute();
    $centres_formation = $stmt->fetchAll();
} catch (PDOException $e) {
    // Si la table n'existe pas, utiliser une liste par défaut
    $centres_formation = [
        ['id' => 1, 'nom' => 'ANACIM'],
        ['id' => 2, 'nom' => 'ENAC'],
        ['id' => 3, 'nom' => 'ERNAM'],
        ['id' => 4, 'nom' => 'ITAerea'],
        ['id' => 5, 'nom' => 'IFURTA'],
        ['id' => 6, 'nom' => 'EPT'],
        ['id' => 7, 'nom' => 'IFNPC'],
        ['id' => 8, 'nom' => 'EMAERO services']
    ];
}

// Récupérer les formations à renouveler et non effectuées
$stmt = $db->prepare("
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
        DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
    FROM agents a
    JOIN formations_agents fa ON a.id = fa.agent_id
    JOIN formations f ON fa.formation_id = f.id
    WHERE DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
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
        CURDATE() as echeance_prevue,
        0 as jours_restants
    FROM agents a
    CROSS JOIN formations f
    WHERE NOT EXISTS (
        SELECT 1 FROM formations_agents fa 
        WHERE fa.agent_id = a.id AND fa.formation_id = f.id
    )
    
    ORDER BY jours_restants ASC, agent_id, formation_id
");
$stmt->execute();
$besoins_formation = $stmt->fetchAll();

// Récupérer le planning existant
$stmt = $db->prepare("
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
    ORDER BY pf.date_prevue_debut ASC
");
$stmt->execute();
$planning_existant = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning des Formations - ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #124c97;
            --danger-color: #ff011e;
            --warning-color: #f5df35;
            --dark-primary: #0a3570;
        }
        
        .navbar-custom {
            background-color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-primary);
            border-color: var(--dark-primary);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
        }
        
        .logo-header {
            max-height: 40px;
            margin-right: 10px;
            background-color: white;
            padding: 5px;
            border-radius: 5px;
        }
        
        .besoin-urgent {
            background-color: #ffe6e6;
            border-left: 4px solid var(--danger-color);
        }
        
        .besoin-important {
            background-color: #fff3cd;
            border-left: 4px solid var(--warning-color);
        }
        
        .besoin-normal {
            background-color: #e7f3ff;
            border-left: 4px solid var(--primary-color);
        }
        
        .planning-section {
            display: none;
        }
        
        .planning-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="admin.php">
                <img src="logo-anacim.png" alt="ANACIM" class="logo-header">
                <span>Planning des Formations</span>
            </a>
            <div class="navbar-nav ms-auto">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php"><i class="fas fa-arrow-left"></i> Retour Admin</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Navigation buttons -->
        <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
            <button class="btn btn-primary active" onclick="showSection('besoins')" id="btn-besoins">
                <i class="fas fa-exclamation-triangle"></i> Besoins de Formation
            </button>
            <button class="btn btn-outline-primary" onclick="showSection('planifier')" id="btn-planifier">
                <i class="fas fa-calendar-plus"></i> Planifier Formation
            </button>
            <button class="btn btn-outline-primary" onclick="showSection('planning')" id="btn-planning">
                <i class="fas fa-calendar"></i> Planning Existant
            </button>
            <button class="btn btn-outline-primary" onclick="showSection('rapport')" id="btn-rapport">
                <i class="fas fa-chart-bar"></i> Rapport
            </button>
        </div>

        <?php
        // Gestion des paramètres URL pour la pré-sélection
        $preselect_agent = $_GET['agent_id'] ?? '';
        $preselect_formation = $_GET['formation_id'] ?? '';
        $preselect_section = $_GET['section'] ?? '';
        
        // Debug
        if (!empty($preselect_formation)) {
            echo "<!-- DEBUG: preselect_formation = $preselect_formation -->";
        }
        ?>

        <!-- Section Besoins de Formation -->
        <div class="planning-section <?= ($preselect_section == 'planifier') ? '' : 'active' ?>" id="besoins">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-triangle"></i> Besoins de Formation Identifiés</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($besoins_formation)): ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle"></i> Aucun besoin de formation urgent identifié.
                        </div>
                    <?php else: ?>
                        <?php 
                        // Grouper les besoins par agent
                        $besoins_par_agent = [];
                        foreach ($besoins_formation as $besoin) {
                            $agent_key = $besoin['agent_id'];
                            if (!isset($besoins_par_agent[$agent_key])) {
                                $besoins_par_agent[$agent_key] = [
                                    'agent' => $besoin,
                                    'formations' => []
                                ];
                            }
                            $besoins_par_agent[$agent_key]['formations'][] = $besoin;
                        }
                        
                        // Fonction pour déterminer la catégorie de formation
                        function getCategorieFormationBesoin($code) {
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
                        
                        <?php foreach ($besoins_par_agent as $agent_data): ?>
                            <div class="mb-4">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-user"></i> 
                                    <?= htmlspecialchars($agent_data['agent']['matricule']) ?> - 
                                    <?= htmlspecialchars($agent_data['agent']['prenom'] . ' ' . $agent_data['agent']['nom']) ?>
                                </h6>
                                
                                <?php 
                                // Grouper les formations par catégorie
                                $formations_par_categorie = [];
                                foreach ($agent_data['formations'] as $formation) {
                                    $categorie = getCategorieFormationBesoin($formation['code']);
                                    $formations_par_categorie[$categorie][] = $formation;
                                }
                                ?>
                                
                                <?php foreach ($formations_par_categorie as $categorie => $formations): ?>
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
                                                    <?php foreach ($formations as $besoin): ?>
                                                        <?php 
                                                        $priorite_class = '';
                                                        $priorite_label = '';
                                                        if ($besoin['jours_restants'] <= 0) {
                                                            $priorite_class = 'besoin-urgent';
                                                            $priorite_label = 'URGENT';
                                                        } elseif ($besoin['jours_restants'] <= 30) {
                                                            $priorite_class = 'besoin-important';
                                                            $priorite_label = 'Important';
                                                        } else {
                                                            $priorite_class = 'besoin-normal';
                                                            $priorite_label = 'Normal';
                                                        }
                                                        ?>
                                                        <tr class="<?= $priorite_class ?>">
                                                            <td>
                                                                <strong><?= htmlspecialchars($besoin['code']) ?></strong><br>
                                                                <small><?= htmlspecialchars($besoin['intitule']) ?></small>
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
                                                                <button class="btn btn-sm btn-primary" 
                                                                        onclick="planifierFormation(<?= $besoin['agent_id'] ?>, <?= $besoin['formation_id'] ?>)">
                                                                    <i class="fas fa-calendar-plus"></i> Planifier
                                                                </button>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section Planifier Formation -->
        <div class="planning-section <?= ($preselect_section == 'planifier') ? 'active' : '' ?>" id="planifier">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-plus"></i> Planifier une Formation</h5>
                </div>
                <div class="card-body">
                    <form id="planificationForm">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Agent *</label>
                                <select class="form-select" name="agent_id" required>
                                    <option value="">Sélectionner un agent...</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= $agent['id'] ?>" <?= ($preselect_agent == $agent['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($agent['matricule'] . ' - ' . $agent['prenom'] . ' ' . $agent['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Formation *</label>
                                <select class="form-select" name="formation_id" required>
                                    <option value="">Sélectionner une formation...</option>
                                    <?php 
                                    if (!empty($formations)) {
                                        foreach ($formations as $formation) {
                                            $id = htmlspecialchars($formation['id'] ?? '');
                                            $code = htmlspecialchars($formation['code'] ?? '');
                                            $intitule = htmlspecialchars($formation['intitule'] ?? '');
                                            $selected = (strval($preselect_formation) === strval($id)) ? 'selected' : '';
                                            echo "<!-- Formation ID: $id, Preselect: $preselect_formation, Selected: $selected -->\n";
                                            ?>
                                            <option value="<?= $id ?>" <?= $selected ?>><?= $code ?> - <?= $intitule ?></option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Centre de Formation *</label>
                                <select class="form-select" name="centre_formation_prevu" required>
                                    <option value="">Sélectionner un centre...</option>
                                    <?php foreach ($centres_formation as $centre): ?>
                                        <option value="<?= htmlspecialchars($centre['nom']) ?>">
                                            <?= htmlspecialchars($centre['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <label class="form-label">Date de début *</label>
                                <input type="date" class="form-control" name="date_prevue_debut" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date de fin *</label>
                                <input type="date" class="form-control" name="date_prevue_fin" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="statut">
                                    <option value="planifie">Planifié</option>
                                    <option value="confirme">Confirmé</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="fas fa-save"></i> Planifier
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <label class="form-label">Commentaires</label>
                                <textarea class="form-control" name="commentaires" rows="3" 
                                          placeholder="Commentaires optionnels sur cette planification..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Section Planning Existant -->
        <div class="planning-section" id="planning">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-calendar"></i> Planning des Formations</h5>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="yearFilter" onchange="filterByYear()" style="width: auto;">
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
                        <button class="btn btn-sm btn-outline-primary" onclick="printPlanning()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($planning_existant)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> Aucune formation planifiée pour le moment.
                        </div>
                    <?php else: ?>
                        <?php 
                        // Grouper les plannings par agent
                        $planning_par_agent = [];
                        foreach ($planning_existant as $planning) {
                            $agent_key = $planning['agent_id'];
                            if (!isset($planning_par_agent[$agent_key])) {
                                $planning_par_agent[$agent_key] = [
                                    'agent' => $planning,
                                    'formations' => []
                                ];
                            }
                            $planning_par_agent[$agent_key]['formations'][] = $planning;
                        }
                        
                        // Fonction pour déterminer la catégorie de formation
                        function getCategorieFormation($code) {
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
                        
                        <?php foreach ($planning_par_agent as $agent_data): ?>
                            <div class="mb-4">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-user"></i> 
                                    <?= htmlspecialchars($agent_data['agent']['matricule']) ?> - 
                                    <?= htmlspecialchars($agent_data['agent']['prenom'] . ' ' . $agent_data['agent']['nom']) ?>
                                </h6>
                                
                                <?php 
                                // Grouper les formations par catégorie
                                $formations_par_categorie = [];
                                foreach ($agent_data['formations'] as $formation) {
                                    $categorie = getCategorieFormation($formation['code']);
                                    $formations_par_categorie[$categorie][] = $formation;
                                }
                                ?>
                                
                                <?php foreach ($formations_par_categorie as $categorie => $formations): ?>
                                    <div class="mb-3">
                                        <h6 class="text-secondary mb-2"><?= $categorie ?></h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Formation</th>
                                                        <th>Centre</th>
                                                        <th>Dates</th>
                                                        <th>Statut</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($formations as $planning): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($planning['code']) ?></strong><br>
                                                                <small><?= htmlspecialchars($planning['intitule']) ?></small>
                                                            </td>
                                                            <td><?= htmlspecialchars($planning['centre_nom'] ?? $planning['centre_formation_prevu']) ?></td>
                                                            <td>
                                                                Du <?= date('d/m/Y', strtotime($planning['date_prevue_debut'])) ?><br>
                                                                au <?= date('d/m/Y', strtotime($planning['date_prevue_fin'])) ?>
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
                                                                            onclick="modifierPlanning(<?= $planning['id'] ?>)">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button class="btn btn-outline-danger" 
                                                                            onclick="supprimerPlanning(<?= $planning['id'] ?>)">
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section Rapport -->
        <div class="planning-section" id="rapport">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Rapport des Formations</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <h3 class="text-primary"><?= count($besoins_formation) ?></h3>
                                    <p class="mb-0">Besoins de Formation</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <h3 class="text-success"><?= count($planning_existant) ?></h3>
                                    <p class="mb-0">Formations Planifiées</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button class="btn btn-primary" onclick="genererRapport()">
                            <i class="fas fa-file-pdf"></i> Générer Rapport Détaillé
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId) {
            // Masquer toutes les sections
            document.querySelectorAll('.planning-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Retirer la classe active de tous les boutons
            document.querySelectorAll('[id^="btn-"]').forEach(btn => {
                btn.classList.remove('btn-primary', 'active');
                btn.classList.add('btn-outline-primary');
            });
            
            // Afficher la section sélectionnée
            document.getElementById(sectionId).classList.add('active');
            
            // Activer le bouton correspondant
            const activeBtn = document.getElementById('btn-' + sectionId);
            if (activeBtn) {
                activeBtn.classList.remove('btn-outline-primary');
                activeBtn.classList.add('btn-primary', 'active');
            }
        }

        function planifierFormation(agentId, formationId) {
            console.log('planifierFormation called with:', agentId, formationId);
            
            // Passer à la section planification et pré-remplir les champs
            showSection('planifier');
            
            setTimeout(() => {
                const agentSelect = document.querySelector('select[name="agent_id"]');
                const formationSelect = document.querySelector('select[name="formation_id"]');
                
                console.log('Elements found:', !!agentSelect, !!formationSelect);
                
                if (agentSelect) {
                    agentSelect.value = String(agentId);
                    console.log('Agent - Expected:', agentId, 'Set to:', agentSelect.value, 'Match:', agentSelect.value == agentId);
                }
                
                if (formationSelect) {
                    // Essayer différents formats de valeur
                    const formationIdStr = String(formationId);
                    
                    console.log('Formation - Trying to set:', formationIdStr);
                    console.log('Available options:');
                    Array.from(formationSelect.options).forEach((option, index) => {
                        console.log(`  [${index}] value: "${option.value}" (type: ${typeof option.value}), text: "${option.text}"`);
                    });
                    
                    // Essayer de définir la valeur
                    formationSelect.value = formationIdStr;
                    
                    // Vérifier si cela a fonctionné
                    if (formationSelect.value === formationIdStr) {
                        console.log('✓ Formation successfully set to:', formationSelect.value);
                    } else {
                        console.warn('✗ Formation not set. Trying alternative approach...');
                        
                        // Essayer de trouver l'option manuellement
                        let optionFound = false;
                        Array.from(formationSelect.options).forEach(option => {
                            if (option.value === formationIdStr || option.value == formationId) {
                                option.selected = true;
                                optionFound = true;
                                console.log('✓ Formation set manually to:', option.value);
                            }
                        });
                        
                        if (!optionFound) {
                            console.error('✗ Could not find matching option for formation ID:', formationId);
                        }
                    }
                }
            }, 200);
        }

        // Gestion du formulaire de planification
        document.getElementById('planificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Validation des dates
            const dateDebut = new Date(this.querySelector('input[name="date_prevue_debut"]').value);
            const dateFin = new Date(this.querySelector('input[name="date_prevue_fin"]').value);
            
            if (dateFin <= dateDebut) {
                alert('La date de fin doit être postérieure à la date de début.');
                return;
            }
            
            // Désactiver le bouton
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Planification...';
            
            const formData = new FormData(this);
            
            fetch('ajax/save_planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Afficher un message convivial
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                    alertDiv.style.zIndex = '9999';
                    alertDiv.innerHTML = `
                        <i class="fas fa-check-circle"></i> 
                        <strong>Planning enregistré avec succès !</strong>
                        <br>Redirection vers les plannings existants...
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alertDiv);
                    
                    // Naviguer vers la section planning existant après 1.5 secondes
                    setTimeout(() => {
                        // Masquer le formulaire de planification
                        document.getElementById('planifier').style.display = 'none';
                        document.getElementById('btn-planifier').classList.remove('active');
                        document.getElementById('btn-planifier').classList.add('btn-outline-primary');
                        
                        // Afficher la section planning existant
                        document.getElementById('planning').style.display = 'block';
                        document.getElementById('btn-planning').classList.add('active');
                        document.getElementById('btn-planning').classList.remove('btn-outline-primary');
                        
                        // Recharger la page après 2 secondes pour mettre à jour les données
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }, 1500);
                } else {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    submitBtn.className = 'btn btn-primary d-block w-100';
                    
                    // Message d'erreur convivial
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                    alertDiv.style.zIndex = '9999';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-circle"></i> 
                        <strong>Erreur lors de l'enregistrement</strong>
                        <br>${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alertDiv);
                    
                    setTimeout(() => alertDiv.remove(), 5000);
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

        // Fonction pour filtrer par année
        function filterByYear() {
            const selectedYear = document.getElementById('yearFilter').value;
            const agentSections = document.querySelectorAll('#planning .mb-4');
            
            agentSections.forEach(section => {
                if (!selectedYear) {
                    // Afficher toutes les sections si aucune année n'est sélectionnée
                    section.style.display = '';
                    const rows = section.querySelectorAll('.table tbody tr');
                    rows.forEach(row => row.style.display = '');
                } else {
                    // Vérifier si cette section d'agent a des formations pour l'année sélectionnée
                    const rows = section.querySelectorAll('.table tbody tr');
                    let hasFormationsInYear = false;
                    
                    rows.forEach(row => {
                        const dateCell = row.querySelector('td:nth-child(3)');
                        if (dateCell && dateCell.textContent.includes(selectedYear)) {
                            row.style.display = '';
                            hasFormationsInYear = true;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Masquer toute la section de l'agent s'il n'a pas de formations cette année
                    if (hasFormationsInYear) {
                        section.style.display = '';
                        // Afficher/masquer les catégories selon qu'elles ont des formations visibles
                        const categoryDivs = section.querySelectorAll('.mb-3');
                        categoryDivs.forEach(categoryDiv => {
                            const visibleRows = categoryDiv.querySelectorAll('.table tbody tr[style=""], .table tbody tr:not([style])');
                            if (visibleRows.length > 0) {
                                categoryDiv.style.display = '';
                            } else {
                                categoryDiv.style.display = 'none';
                            }
                        });
                    } else {
                        section.style.display = 'none';
                    }
                }
            });
        }

        // Fonction pour imprimer le planning
        function printPlanning() {
            const selectedYear = document.getElementById('yearFilter').value;
            const yearText = selectedYear ? ` - Année ${selectedYear}` : '';
            
            const printContent = `
                <html>
                <head>
                    <title>Planning des Formations${yearText}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .logo { max-height: 60px; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        .agent-header { background-color: #e3f2fd; font-weight: bold; }
                        .category-header { background-color: #f5f5f5; font-style: italic; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <img src="logo-anacim.png" alt="ANACIM" class="logo">
                        <h1>PLANNING DES FORMATIONS${yearText}</h1>
                        <p>Généré le ${new Date().toLocaleDateString('fr-FR')}</p>
                    </div>
                    ${document.querySelector('#planning .card-body').innerHTML}
                </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        function modifierPlanning(planningId) {
            // TODO: Implémenter la modification du planning
            alert('Fonction de modification en cours de développement.');
        }

        function supprimerPlanning(planningId) {
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
                        location.reload();
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

        function genererRapport() {
            window.open('ajax/generate_rapport.php', '_blank');
        }
    </script>
</body>
</html>
