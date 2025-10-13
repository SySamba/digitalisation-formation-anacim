<?php
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

$agent = new Agent($db);
$formation = new Formation($db);

// Récupérer tous les agents inscrits
$agents = $agent->read();
$formations_a_renouveler = $formation->getFormationsExpireesOuAExpirer(90);

$total_agents = count($agents);
$formations_urgentes = count(array_filter($formations_a_renouveler, function($f) {
    return $f['jours_restants'] <= 30;
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Système de Gestion des Formations ANACIM</title>
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
        
        .logo-header {
            max-height: 40px;
            margin-right: 10px;
            background-color: white;
            padding: 5px;
            border-radius: 5px;
        }
        
        .stat-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger-color);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="admin.php">
                <img src="logo-anacim.png" alt="ANACIM" class="logo-header">
                <span>Administration - Gestion des Formations</span>
            </a>
            <div class="navbar-nav ms-auto">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="register.php"><i class="fas fa-home"></i> Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Total Agents Inscrits</h5>
                                <h2 class="text-primary"><?= $total_agents ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
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

        <!-- Agents List -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-users"></i> Liste des Agents Inscrits</h4>
            </div>
            <div class="card-body">
                <?php if (empty($agents)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Aucun agent inscrit pour le moment.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Photo</th>
                                    <th>Matricule</th>
                                    <th>Nom & Prénom</th>
                                    <th>Grade</th>
                                    <th>Structure</th>
                                    <th>Date Inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents as $ag): ?>
                                    <tr>
                                        <td>
                                            <?php if ($ag['photo']): ?>
                                                <img src="uploads/photos/<?= htmlspecialchars($ag['photo']) ?>" 
                                                     class="rounded-circle" width="40" height="40" alt="Photo">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($ag['matricule']) ?></strong></td>
                                        <td><?= htmlspecialchars($ag['prenom'] . ' ' . $ag['nom']) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= getGradeLabel($ag['grade']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($ag['structure_attache'] ?? 'Non spécifiée') ?></td>
                                        <td><?= formatDate($ag['created_at']) ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="viewAgent(<?= $ag['id'] ?>)">
                                                <i class="fas fa-eye"></i> Voir Plus
                                            </button>
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

    <!-- Modal for Agent Details -->
    <div class="modal fade" id="agentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
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
        function viewAgent(agentId) {
            // Load agent details via AJAX
            fetch(`ajax/get_agent_details.php?id=${agentId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('agentModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('agentModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors du chargement des détails de l\'agent.');
                });
        }

        // Global function for agent section navigation
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
    </script>
</body>
</html>
