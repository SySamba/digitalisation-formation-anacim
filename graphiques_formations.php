<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

// Récupération des données pour les graphiques

// 1. Données pour le graphique de répartition des formations par agent
$query_agents_stats = "
    SELECT 
        a.id,
        CONCAT(a.prenom, ' ', a.nom) as nom_complet,
        a.matricule,
        
        -- Formations effectuées
        (SELECT COUNT(*) FROM formations_effectuees fe WHERE fe.agent_id = a.id AND fe.statut IN ('termine', 'valide')) +
        (SELECT COUNT(*) FROM formations_agents fa WHERE fa.agent_id = a.id AND fa.statut IN ('termine', 'valide')) as formations_effectuees,
        
        -- Formations non effectuées
        (SELECT COUNT(*) FROM formations f WHERE f.id NOT IN (
            SELECT DISTINCT fe.formation_id FROM formations_effectuees fe WHERE fe.agent_id = a.id AND fe.statut IN ('termine', 'valide')
            UNION
            SELECT DISTINCT fa.formation_id FROM formations_agents fa WHERE fa.agent_id = a.id AND fa.statut IN ('termine', 'valide')
        )) as formations_non_effectuees,
        
        -- Formations à renouveler
        (SELECT COUNT(*) FROM formations_effectuees fe 
         JOIN formations f ON fe.formation_id = f.id 
         WHERE fe.agent_id = a.id AND fe.statut IN ('termine', 'valide') 
         AND f.periodicite_mois > 0 
         AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)) +
        (SELECT COUNT(*) FROM formations_agents fa 
         JOIN formations f ON fa.formation_id = f.id 
         WHERE fa.agent_id = a.id AND fa.statut IN ('termine', 'valide') 
         AND f.periodicite_mois > 0 
         AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)) as formations_a_renouveler
         
    FROM agents a
    ORDER BY a.nom, a.prenom
";

$stmt_agents = $pdo->prepare($query_agents_stats);
$stmt_agents->execute();
$agents_stats = $stmt_agents->fetchAll(PDO::FETCH_ASSOC);

// 2. Données pour le graphique de répartition par type de formation
$query_types_stats = "
    SELECT 
        f.categorie,
        COUNT(DISTINCT f.id) as total_formations,
        
        -- Formations effectuées par catégorie
        (SELECT COUNT(*) FROM formations_effectuees fe 
         JOIN formations f2 ON fe.formation_id = f2.id 
         WHERE f2.categorie = f.categorie AND fe.statut IN ('termine', 'valide')) +
        (SELECT COUNT(*) FROM formations_agents fa 
         JOIN formations f3 ON fa.formation_id = f3.id 
         WHERE f3.categorie = f.categorie AND fa.statut IN ('termine', 'valide')) as effectuees,
        
        -- Formations non effectuées par catégorie
        (SELECT COUNT(DISTINCT f4.id) FROM formations f4 
         WHERE f4.categorie = f.categorie 
         AND f4.id NOT IN (
            SELECT DISTINCT fe.formation_id FROM formations_effectuees fe WHERE fe.statut IN ('termine', 'valide')
            UNION
            SELECT DISTINCT fa.formation_id FROM formations_agents fa WHERE fa.statut IN ('termine', 'valide')
         )) as non_effectuees
         
    FROM formations f
    GROUP BY f.categorie
    ORDER BY f.categorie
";

$stmt_types = $pdo->prepare($query_types_stats);
$stmt_types->execute();
$types_stats = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

// Préparation des données pour Chart.js
$agents_labels = [];
$agents_effectuees = [];
$agents_non_effectuees = [];
$agents_a_renouveler = [];

foreach ($agents_stats as $agent) {
    $agents_labels[] = $agent['nom_complet'] . ' (' . $agent['matricule'] . ')';
    $agents_effectuees[] = (int)$agent['formations_effectuees'];
    $agents_non_effectuees[] = (int)$agent['formations_non_effectuees'];
    $agents_a_renouveler[] = (int)$agent['formations_a_renouveler'];
}

$types_labels = [];
$types_effectuees = [];
$types_non_effectuees = [];

foreach ($types_stats as $type) {
    $type_label = '';
    switch ($type['categorie']) {
        case 'FAMILIARISATION':
            $type_label = 'Familiarisation';
            break;
        case 'FORMATION_INITIALE':
            $type_label = 'Formation Initiale';
            break;
        case 'FORMATION_COURS_EMPLOI':
            $type_label = 'Formation en Cours d\'Emploi';
            break;
        case 'FORMATION_TECHNIQUE':
            $type_label = 'Formation Technique';
            break;
        default:
            $type_label = $type['categorie'];
    }
    
    $types_labels[] = $type_label;
    $types_effectuees[] = (int)$type['effectuees'];
    $types_non_effectuees[] = (int)$type['non_effectuees'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graphiques des Formations - ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .navbar-brand {
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-chart-bar me-2"></i>
                ANACIM - Graphiques des Formations
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin.php">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour à l'administration
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- En-tête -->
        <div class="row">
            <div class="col-12">
                <div class="stats-card text-center">
                    <h1><i class="fas fa-chart-pie me-2"></i>Tableaux de Bord des Formations</h1>
                    <p class="mb-0">Visualisation professionnelle des données de formation ANACIM</p>
                </div>
            </div>
        </div>

        <!-- Statistiques globales -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-success mb-2"></i>
                        <h4 class="text-success"><?= count($agents_stats) ?></h4>
                        <small>Agents Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <i class="fas fa-graduation-cap fa-2x text-info mb-2"></i>
                        <h4 class="text-info"><?= array_sum($types_effectuees) ?></h4>
                        <small>Formations Effectuées</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <h4 class="text-warning"><?= array_sum($types_non_effectuees) ?></h4>
                        <small>Non Effectuées</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-primary">
                    <div class="card-body">
                        <i class="fas fa-list fa-2x text-primary mb-2"></i>
                        <h4 class="text-primary"><?= count($types_stats) ?></h4>
                        <small>Types de Formation</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique 1: Répartition des formations par agent -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-chart me-2"></i>
                            1. Répartition des Formations par Agent
                        </h5>
                        <small>Formations effectuées, non effectuées et à renouveler par agent</small>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartAgents"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique 2: Répartition par type de formation -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            2. Répartition par Type de Formation
                        </h5>
                        <small>Formations effectuées vs non effectuées</small>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartTypes"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-doughnut me-2"></i>
                            Répartition Globale
                        </h5>
                        <small>Vue d'ensemble des formations</small>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartGlobal"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau détaillé -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Détail par Type de Formation
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Type de Formation</th>
                                        <th class="text-center">Effectuées</th>
                                        <th class="text-center">Non Effectuées</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Taux de Réalisation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($types_stats as $type): 
                                        $total = $type['effectuees'] + $type['non_effectuees'];
                                        $taux = $total > 0 ? round(($type['effectuees'] / $total) * 100, 1) : 0;
                                        $type_label = '';
                                        switch ($type['categorie']) {
                                            case 'FAMILIARISATION': $type_label = 'Familiarisation'; break;
                                            case 'FORMATION_INITIALE': $type_label = 'Formation Initiale'; break;
                                            case 'FORMATION_COURS_EMPLOI': $type_label = 'Formation en Cours d\'Emploi'; break;
                                            case 'FORMATION_TECHNIQUE': $type_label = 'Formation Technique'; break;
                                            default: $type_label = $type['categorie'];
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?= $type_label ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?= $type['effectuees'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?= $type['non_effectuees'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= $total ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" style="width: <?= $taux ?>%">
                                                    <?= $taux ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration des couleurs
        const colors = {
            primary: '#0d6efd',
            success: '#198754',
            danger: '#dc3545',
            warning: '#ffc107',
            info: '#0dcaf0',
            secondary: '#6c757d'
        };

        // Graphique 1: Répartition des formations par agent
        const ctx1 = document.getElementById('chartAgents').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: <?= json_encode($agents_labels) ?>,
                datasets: [
                    {
                        label: 'Formations Effectuées',
                        data: <?= json_encode($agents_effectuees) ?>,
                        backgroundColor: colors.success,
                        borderColor: colors.success,
                        borderWidth: 1
                    },
                    {
                        label: 'Non Effectuées',
                        data: <?= json_encode($agents_non_effectuees) ?>,
                        backgroundColor: colors.danger,
                        borderColor: colors.danger,
                        borderWidth: 1
                    },
                    {
                        label: 'À Renouveler',
                        data: <?= json_encode($agents_a_renouveler) ?>,
                        backgroundColor: colors.warning,
                        borderColor: colors.warning,
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Répartition des formations par agent'
                    },
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre de formations'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Agents'
                        }
                    }
                }
            }
        });

        // Graphique 2: Répartition par type de formation
        const ctx2 = document.getElementById('chartTypes').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: <?= json_encode($types_labels) ?>,
                datasets: [
                    {
                        label: 'Effectuées',
                        data: <?= json_encode($types_effectuees) ?>,
                        backgroundColor: colors.success
                    },
                    {
                        label: 'Non Effectuées',
                        data: <?= json_encode($types_non_effectuees) ?>,
                        backgroundColor: colors.danger
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Formations par type'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Graphique 3: Vue globale (doughnut)
        const ctx3 = document.getElementById('chartGlobal').getContext('2d');
        const totalEffectuees = <?= array_sum($types_effectuees) ?>;
        const totalNonEffectuees = <?= array_sum($types_non_effectuees) ?>;
        
        new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: ['Formations Effectuées', 'Formations Non Effectuées'],
                datasets: [{
                    data: [totalEffectuees, totalNonEffectuees],
                    backgroundColor: [colors.success, colors.danger],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Répartition globale des formations'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
