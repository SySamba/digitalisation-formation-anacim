<?php
// Rediriger vers la page d'inscription (homepage pour les agents)
header('Location: register.php');
exit;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système de Gestion des Formations - ANACIM</title>
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
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: #000;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(18, 76, 151, 0.1);
        }
        
        .sidebar {
            background-color: #f8f9fa;
            min-height: calc(100vh - 56px);
            border-right: 1px solid #dee2e6;
        }
        
        .sidebar .nav-link {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(18, 76, 151, 0.1);
        }
        
        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .stat-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .logo-header {
            max-height: 40px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="logo-anacim.png" alt="ANACIM" class="logo-header">
                <span>Gestion des Formations</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-nav ms-auto">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="register.php"><i class="fas fa-user-plus"></i> Inscription Agent</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-user"></i> Administrateur</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" onclick="showSection('dashboard')">
                            <i class="fas fa-tachometer-alt"></i> Tableau de Bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showSection('agents')">
                            <i class="fas fa-users"></i> Gestion des Agents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showSection('formations')">
                            <i class="fas fa-graduation-cap"></i> Formations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showSection('planning')">
                            <i class="fas fa-calendar-alt"></i> Planning
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showSection('alertes')">
                            <i class="fas fa-exclamation-triangle"></i> Alertes
                            <?php if ($formations_urgentes > 0): ?>
                                <span class="badge bg-danger"><?= $formations_urgentes ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showSection('rapports')">
                            <i class="fas fa-chart-bar"></i> Rapports
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <!-- Dashboard Section -->
                <div id="dashboard" class="content-section">
                    <h2 class="mb-4">Tableau de Bord</h2>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title text-muted">Total Agents</h5>
                                            <h2 class="text-primary"><?= $total_agents ?></h2>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-users fa-2x text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title text-muted">Formations Disponibles</h5>
                                            <h2 class="text-success"><?= $total_formations ?></h2>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-graduation-cap fa-2x text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card danger">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title text-muted">Formations Urgentes</h5>
                                            <h2 class="text-danger"><?= $formations_urgentes ?></h2>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-clock"></i> Formations à Renouveler</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($formations_a_renouveler)): ?>
                                        <p class="text-muted">Aucune formation à renouveler dans les 90 prochains jours.</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach (array_slice($formations_a_renouveler, 0, 5) as $formation_exp): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?= htmlspecialchars($formation_exp['prenom'] . ' ' . $formation_exp['nom']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($formation_exp['intitule']) ?></small>
                                                    </div>
                                                    <span class="badge <?= $formation_exp['jours_restants'] <= 0 ? 'bg-danger' : ($formation_exp['jours_restants'] <= 30 ? 'bg-warning' : 'bg-info') ?>">
                                                        <?= $formation_exp['jours_restants'] <= 0 ? 'Expiré' : $formation_exp['jours_restants'] . ' jours' ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-pie"></i> Répartition par Grade</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $grades_count = [];
                                    foreach ($agents as $ag) {
                                        $grade_label = getGradeLabel($ag['grade']);
                                        $grades_count[$grade_label] = ($grades_count[$grade_label] ?? 0) + 1;
                                    }
                                    ?>
                                    <?php foreach ($grades_count as $grade => $count): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= $grade ?></span>
                                            <span class="badge bg-primary"><?= $count ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agents Section -->
                <div id="agents" class="content-section" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Gestion des Agents</h2>
                        <button class="btn btn-primary" onclick="showAgentModal()">
                            <i class="fas fa-plus"></i> Nouvel Agent
                        </button>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Nom & Prénom</th>
                                            <th>Grade</th>
                                            <th>Structure</th>
                                            <th>Date Recrutement</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agents as $ag): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($ag['matricule']) ?></td>
                                                <td><?= htmlspecialchars($ag['prenom'] . ' ' . $ag['nom']) ?></td>
                                                <td><?= getGradeLabel($ag['grade']) ?></td>
                                                <td><?= htmlspecialchars($ag['structure_attache'] ?? '') ?></td>
                                                <td><?= formatDate($ag['date_recrutement']) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewAgent(<?= $ag['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editAgent(<?= $ag['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other sections will be loaded dynamically -->
                <div id="formations" class="content-section" style="display: none;">
                    <h2>Gestion des Formations</h2>
                    <p>Section en cours de développement...</p>
                </div>

                <div id="planning" class="content-section" style="display: none;">
                    <h2>Planning des Formations</h2>
                    <p>Section en cours de développement...</p>
                </div>

                <div id="alertes" class="content-section" style="display: none;">
                    <h2>Alertes et Notifications</h2>
                    <p>Section en cours de développement...</p>
                </div>

                <div id="rapports" class="content-section" style="display: none;">
                    <h2>Rapports et Statistiques</h2>
                    <p>Section en cours de développement...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Agent Details -->
    <div class="modal fade" id="agentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de l'Agent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="agentModalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Remove active class from all nav links
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionId).style.display = 'block';
            
            // Add active class to clicked nav link
            event.target.classList.add('active');
        }

        function viewAgent(agentId) {
            // Load agent details via AJAX
            fetch(`ajax/get_agent_details.php?id=${agentId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('agentModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('agentModal')).show();
                });
        }

        function editAgent(agentId) {
            // Load agent edit form via AJAX
            fetch(`ajax/get_agent_form.php?id=${agentId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('agentModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('agentModal')).show();
                });
        }

        function showAgentModal() {
            // Load new agent form
            fetch('ajax/get_agent_form.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('agentModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('agentModal')).show();
                });
        }
    </script>
</body>
</html>
