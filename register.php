<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Agent.php';
require_once 'classes/Formation.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$formation = new Formation($db);
$formations = $formation->read();

// Grouper les formations par catégorie
$formations_by_category = [];
foreach ($formations as $f) {
    $formations_by_category[$f['categorie']][] = $f;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Agent - ANACIM</title>
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
        }
        
        .formation-category {
            border-left: 4px solid var(--primary-color);
            margin-bottom: 20px;
        }
        
        .formation-item {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            padding: 10px;
        }
        
        .formation-item:hover {
            background-color: #f8f9fa;
        }
        
        .formation-item.selected {
            background-color: rgba(18, 76, 151, 0.1);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="register.php">
                <img src="logo-anacim.png" alt="ANACIM" class="logo-header">
                <span>Inscription Agent</span>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="agent_login.php"><i class="fas fa-user"></i> Mon Profil</a>
                <a class="nav-link" href="admin.php"><i class="fas fa-cog"></i> Administration</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-user-plus"></i> Inscription d'un Nouvel Agent</h4>
                    </div>
                    <div class="card-body">
                        <form id="registrationForm">
                            <!-- Informations de base pour inscription -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">Informations de Base</h5>
                                    <p class="text-muted">Après inscription, vous pourrez compléter votre profil avec vos formations et documents.</p>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="matricule" class="form-label">Matricule *</label>
                                        <input type="text" class="form-control" id="matricule" name="matricule" 
                                               value="<?= generateMatricule() ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="prenom" class="form-label">Prénom *</label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nom" class="form-label">Nom *</label>
                                        <input type="text" class="form-control" id="nom" name="nom" required>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> S'inscrire
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/register_agent_simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Inscription réussie ! Vous pouvez maintenant vous connecter avec votre matricule et email.');
                    window.location.href = 'agent_login.php';
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Une erreur est survenue lors de l\'inscription.');
            });
        });
    </script>
</body>
</html>
