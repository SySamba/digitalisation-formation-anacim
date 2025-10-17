<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Récupérer les données pour le rapport
$stmt = $db->prepare("
    SELECT 
        a.id as agent_id,
        a.matricule,
        a.prenom,
        a.nom,
        a.grade,
        a.structure_attache,
        COUNT(DISTINCT fa.formation_id) as formations_effectuees,
        COUNT(DISTINCT pf.formation_id) as formations_planifiees
    FROM agents a
    LEFT JOIN formations_agents fa ON a.id = fa.agent_id
    LEFT JOIN planning_formations pf ON a.id = pf.agent_id AND pf.statut IN ('planifie', 'confirme')
    GROUP BY a.id
    ORDER BY a.nom, a.prenom
");
$stmt->execute();
$agents_stats = $stmt->fetchAll();

// Récupérer les formations non effectuées par agent
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
        'non_effectuee' as type_besoin
    FROM agents a
    CROSS JOIN formations f
    WHERE NOT EXISTS (
        SELECT 1 FROM formations_agents fa 
        WHERE fa.agent_id = a.id AND fa.formation_id = f.id
    )
    ORDER BY a.nom, a.prenom, f.code
");
$stmt->execute();
$formations_non_effectuees = $stmt->fetchAll();

// Récupérer les formations à renouveler
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
    ORDER BY jours_restants ASC, a.nom, a.prenom
");
$stmt->execute();
$formations_a_renouveler = $stmt->fetchAll();

// Organiser les données par agent
$rapport_par_agent = [];
foreach ($agents_stats as $agent) {
    $agent_id = $agent['agent_id'];
    $rapport_par_agent[$agent_id] = [
        'agent' => $agent,
        'non_effectuees' => [],
        'a_renouveler' => []
    ];
}

foreach ($formations_non_effectuees as $formation) {
    $agent_id = $formation['agent_id'];
    if (isset($rapport_par_agent[$agent_id])) {
        $rapport_par_agent[$agent_id]['non_effectuees'][] = $formation;
    }
}

foreach ($formations_a_renouveler as $formation) {
    $agent_id = $formation['agent_id'];
    if (isset($rapport_par_agent[$agent_id])) {
        $rapport_par_agent[$agent_id]['a_renouveler'][] = $formation;
    }
}

// Générer le HTML du rapport
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport des Formations - ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
        
        :root {
            --primary-color: #124c97;
        }
        
        .header-logo {
            max-height: 80px;
        }
        
        .rapport-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .agent-section {
            margin-bottom: 40px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .agent-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .formation-urgent {
            background-color: #ffe6e6;
        }
        
        .formation-important {
            background-color: #fff3cd;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- En-tête du rapport -->
        <div class="rapport-header">
            <img src="../logo-anacim.png" alt="ANACIM" class="header-logo mb-3">
            <h1>Rapport des Formations par Agent</h1>
            <h4>Formations Non Effectuées et à Renouveler</h4>
            <p class="mb-0">Généré le <?= date('d/m/Y à H:i') ?></p>
        </div>

        <!-- Boutons d'action -->
        <div class="text-center mb-4 no-print">
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="fas fa-print"></i> Imprimer
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>

        <!-- Résumé général -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Résumé Général</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-primary"><?= count($agents_stats) ?></h3>
                            <p>Agents Total</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-warning"><?= count($formations_non_effectuees) ?></h3>
                            <p>Formations Non Effectuées</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-danger"><?= count($formations_a_renouveler) ?></h3>
                            <p>Formations à Renouveler</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Détail par agent -->
        <?php foreach ($rapport_par_agent as $agent_id => $data): ?>
            <?php 
            $agent = $data['agent'];
            $non_effectuees = $data['non_effectuees'];
            $a_renouveler = $data['a_renouveler'];
            $total_besoins = count($non_effectuees) + count($a_renouveler);
            ?>
            
            <?php if ($total_besoins > 0): ?>
                <div class="agent-section">
                    <div class="agent-header">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-1">
                                    <?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?>
                                    <small class="text-muted">(<?= htmlspecialchars($agent['matricule']) ?>)</small>
                                </h5>
                                <p class="mb-0">
                                    <strong>Grade:</strong> <?= getGradeLabel($agent['grade']) ?> |
                                    <strong>Structure:</strong> <?= htmlspecialchars($agent['structure_attache'] ?? 'Non spécifiée') ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="badge bg-primary fs-6">
                                    <?= $total_besoins ?> formation(s) requise(s)
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($a_renouveler)): ?>
                            <h6 class="text-danger">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Formations à Renouveler (<?= count($a_renouveler) ?>)
                            </h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Formation</th>
                                            <th>Dernière Formation</th>
                                            <th>Échéance</th>
                                            <th>Priorité</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($a_renouveler as $formation): ?>
                                            <tr class="<?= $formation['jours_restants'] <= 0 ? 'formation-urgent' : ($formation['jours_restants'] <= 30 ? 'formation-important' : '') ?>">
                                                <td><strong><?= htmlspecialchars($formation['code']) ?></strong></td>
                                                <td><?= htmlspecialchars($formation['intitule']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($formation['derniere_formation'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($formation['echeance_prevue'])) ?></td>
                                                <td>
                                                    <span class="badge <?= $formation['jours_restants'] <= 0 ? 'bg-danger' : ($formation['jours_restants'] <= 30 ? 'bg-warning' : 'bg-success') ?>">
                                                        <?= $formation['jours_restants'] <= 0 ? 'URGENT' : ($formation['jours_restants'] <= 30 ? 'Important' : 'Normal') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($non_effectuees)): ?>
                            <h6 class="text-warning">
                                <i class="fas fa-clock"></i> 
                                Formations Non Effectuées (<?= count($non_effectuees) ?>)
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Formation</th>
                                            <th>Périodicité</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($non_effectuees as $formation): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($formation['code']) ?></strong></td>
                                                <td><?= htmlspecialchars($formation['intitule']) ?></td>
                                                <td><?= $formation['periodicite_mois'] ?> mois</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Pied de page -->
        <div class="text-center mt-5 text-muted">
            <p>Rapport généré automatiquement par le Système de Gestion des Formations ANACIM</p>
            <p>Date de génération: <?= date('d/m/Y à H:i:s') ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>
