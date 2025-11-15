<?php
session_start();
require_once 'config/database.php';

// Simuler un agent pour le test
$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 1;

$database = new Database();
$pdo = $database->getConnection();

// Récupérer les données de l'agent
$stmt = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
$stmt->execute([$agent_id]);
$agent_data = $stmt->fetch();

if (!$agent_data) {
    die("Agent non trouvé");
}

// Simuler des données de formation pour le test
$formations_effectuees = [
    ['code' => 'SUR-INI-01', 'intitule' => 'Formation Test 1'],
    ['code' => 'SUR-FCE-01', 'intitule' => 'Formation Test 2'],
    ['code' => 'SUR-FTS-01', 'intitule' => 'Formation Test 3']
];
$formations_non_effectuees = [
    ['code' => 'SUR-INI-02', 'intitule' => 'Formation Non Effectuée 1'],
    ['code' => 'SUR-FAM-01', 'intitule' => 'Formation Non Effectuée 2']
];
$formations_a_renouveler = [
    ['code' => 'SUR-FTS-02', 'intitule' => 'Formation À Renouveler']
];
$formations_planifiees = [
    ['code' => 'SUR-INI-03', 'intitule' => 'Formation Planifiée 1'],
    ['code' => 'SUR-FCE-02', 'intitule' => 'Formation Planifiée 2']
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Graphiques Agent - <?= htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <h4><i class="fas fa-user me-2"></i>Test Graphiques pour : <?= htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) ?></h4>
                    <p>Matricule : <?= htmlspecialchars($agent_data['matricule']) ?></p>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4 class="text-success"><?= count($formations_effectuees) ?></h4>
                        <small>Effectuées</small>
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

        <!-- Graphiques -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            Répartition des Formations
                        </h6>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="agentFormationsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Formations par Type
                        </h6>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="agentTypesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="alert alert-success">
                    <h5>Instructions :</h5>
                    <ol>
                        <li>Vérifiez que les deux graphiques s'affichent correctement</li>
                        <li>Ouvrez la console (F12) pour voir les logs de debug</li>
                        <li>Si les graphiques ne s'affichent pas, vérifiez les erreurs dans la console</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <script>
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

            // Données par type de formation pour cet agent
            const typesData = {
                <?php
                $types_agent = [
                    'FAMILIARISATION' => 0,
                    'FORMATION_INITIALE' => 0,
                    'FORMATION_COURS_EMPLOI' => 0,
                    'FORMATION_TECHNIQUE' => 0
                ];
                
                foreach ($formations_effectuees as $formation) {
                    if (strpos($formation['code'], 'SUR-FAM') !== false) {
                        $types_agent['FAMILIARISATION']++;
                    } elseif (strpos($formation['code'], 'SUR-INI') !== false) {
                        $types_agent['FORMATION_INITIALE']++;
                    } elseif (strpos($formation['code'], 'SUR-FCE') !== false) {
                        $types_agent['FORMATION_COURS_EMPLOI']++;
                    } elseif (strpos($formation['code'], 'SUR-FTS') !== false) {
                        $types_agent['FORMATION_TECHNIQUE']++;
                    }
                }
                ?>
                familiarisation: <?= $types_agent['FAMILIARISATION'] ?>,
                initiale: <?= $types_agent['FORMATION_INITIALE'] ?>,
                cours_emploi: <?= $types_agent['FORMATION_COURS_EMPLOI'] ?>,
                technique: <?= $types_agent['FORMATION_TECHNIQUE'] ?>
            };

            console.log('📊 Données types:', typesData);

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
                    new Chart(ctx1, {
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
                                        usePointStyle: true
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

            // Graphique 2: Formations par type pour cet agent
            const ctx2 = document.getElementById('agentTypesChart');
            console.log('🔍 Recherche canvas agentTypesChart:', !!ctx2);
            if (ctx2) {
                console.log('✅ Canvas trouvé, création du graphique types...');
                try {
                    new Chart(ctx2, {
                        type: 'bar',
                        data: {
                            labels: ['Familiarisation', 'Formation Initiale', 'Cours d\'Emploi', 'Technique'],
                            datasets: [{
                                label: 'Formations Effectuées',
                                data: [typesData.familiarisation, typesData.initiale, typesData.cours_emploi, typesData.technique],
                                backgroundColor: [colors.info, colors.primary, colors.warning, colors.success],
                                borderColor: [colors.info, colors.primary, colors.warning, colors.success],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            return 'Type: ' + context[0].label;
                                        },
                                        label: function(context) {
                                            return 'Formations: ' + context.parsed.y;
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
                        }
                    });
                    console.log('✅ Graphique types créé avec succès');
                } catch (error) {
                    console.error('❌ Erreur création graphique types:', error);
                }
            } else {
                console.error('❌ Canvas agentTypesChart non trouvé');
            }
        }

        // Initialiser les graphiques au chargement
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Page chargée, initialisation des graphiques...');
            setTimeout(initAgentCharts, 100);
        });
    </script>
</body>
</html>
