<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test des Graphiques - ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center mb-4">Test des Graphiques</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5>Graphique Test 1 - Doughnut</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="testChart1"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5>Graphique Test 2 - Bar</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="testChart2"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <h5>Instructions de test :</h5>
                    <ol>
                        <li>Vérifiez que les deux graphiques s'affichent correctement</li>
                        <li>Si vous ne voyez que les titres, il y a un problème de chargement</li>
                        <li>Ouvrez la console du navigateur (F12) pour voir les erreurs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔧 Test des graphiques - Début');
            
            // Vérifier si Chart.js est chargé
            if (typeof Chart === 'undefined') {
                console.error('❌ Chart.js n\'est pas chargé !');
                alert('Erreur: Chart.js n\'est pas chargé');
                return;
            }
            
            console.log('✅ Chart.js est chargé');
            
            // Test Graphique 1 - Doughnut
            const ctx1 = document.getElementById('testChart1');
            if (ctx1) {
                console.log('📊 Création du graphique doughnut...');
                try {
                    new Chart(ctx1, {
                        type: 'doughnut',
                        data: {
                            labels: ['Effectuées', 'Non Effectuées', 'À Renouveler', 'Planifiées'],
                            datasets: [{
                                data: [15, 8, 3, 5],
                                backgroundColor: ['#198754', '#dc3545', '#ffc107', '#0dcaf0'],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                    console.log('✅ Graphique doughnut créé avec succès');
                } catch (error) {
                    console.error('❌ Erreur création graphique doughnut:', error);
                }
            } else {
                console.error('❌ Canvas testChart1 non trouvé');
            }
            
            // Test Graphique 2 - Bar
            const ctx2 = document.getElementById('testChart2');
            if (ctx2) {
                console.log('📊 Création du graphique bar...');
                try {
                    new Chart(ctx2, {
                        type: 'bar',
                        data: {
                            labels: ['Familiarisation', 'Formation Initiale', 'Cours d\'Emploi', 'Technique'],
                            datasets: [{
                                label: 'Formations',
                                data: [3, 8, 2, 2],
                                backgroundColor: ['#0dcaf0', '#0d6efd', '#ffc107', '#198754'],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                    console.log('✅ Graphique bar créé avec succès');
                } catch (error) {
                    console.error('❌ Erreur création graphique bar:', error);
                }
            } else {
                console.error('❌ Canvas testChart2 non trouvé');
            }
            
            console.log('🔧 Test des graphiques - Fin');
        });
    </script>
</body>
</html>
