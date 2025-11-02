<?php
session_start();

// Vérifier si l'agent est connecté
if (!isset($_SESSION['agent_logged_in']) || $_SESSION['agent_logged_in'] !== true) {
    header('Location: agent_login.php');
    exit;
}

require_once 'config/database.php';
require_once 'classes/Agent.php';
require_once 'classes/Formation.php';

$database = new Database();
$db = $database->getConnection();

$agent_id = $_SESSION['agent_id'];

// Fonctions pour récupérer les données (reprises du fichier generate_rapport_agent.php)
function getFormationsAMettreAJour($db, $agent_id) {
    $query = "
        SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
               DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
               DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
        FROM formations_effectuees fe
        JOIN formations f ON fe.formation_id = f.id
        WHERE fe.agent_id = ? 
        AND fe.statut IN ('termine', 'valide')
        AND f.periodicite_mois > 0
        AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        
        UNION ALL
        
        SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
               DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
               DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
        FROM formations_agents fa
        JOIN formations f ON fa.formation_id = f.id
        WHERE fa.agent_id = ? 
        AND fa.statut IN ('termine', 'valide')
        AND f.periodicite_mois > 0
        AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        
        ORDER BY jours_restants ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id, $agent_id]);
    return $stmt->fetchAll();
}

function getFormationsPlanifiees($db, $agent_id) {
    $query = "SELECT pf.*, f.intitule, f.code, f.categorie
              FROM planning_formations pf
              JOIN formations f ON pf.formation_id = f.id
              WHERE pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme')
              ORDER BY pf.date_prevue_debut ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id]);
    return $stmt->fetchAll();
}

$formations_a_mettre_a_jour = getFormationsAMettreAJour($db, $agent_id);
$formations_planifiees = getFormationsPlanifiees($db, $agent_id);

// Récupérer les informations de l'agent
$agent = new Agent($db);
$agent_data = $agent->readOne($agent_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Formations à Mettre à Jour - ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #124c97;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --success-color: #198754;
        }
        
        .navbar-custom {
            background-color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
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
        
        .urgent {
            background-color: #f8d7da;
            border-left: 4px solid var(--danger-color);
        }
        
        .high {
            background-color: #fff3cd;
            border-left: 4px solid var(--warning-color);
        }
        
        .medium {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }
        
        .formation-card {
            transition: all 0.3s ease;
        }
        
        .formation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="agent_profile.php">
                <img src="logo-anacim.png" alt="ANACIM" class="logo-header">
                <span>Mes Formations à Jour - <?= htmlspecialchars($_SESSION['agent_nom']) ?></span>
            </a>
            <div class="navbar-nav ms-auto">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="agent_profile.php"><i class="fas fa-user"></i> Mon Profil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php"><i class="fas fa-home"></i> Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agent_logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Formations à mettre à jour -->
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle"></i> Formations à Mettre à Jour (<?= count($formations_a_mettre_a_jour) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($formations_a_mettre_a_jour)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> 
                                Félicitations ! Toutes vos formations sont à jour.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Les formations suivantes nécessitent une mise à jour selon leur périodicité. 
                                Vous pouvez les planifier ou les marquer comme effectuées.
                            </div>
                            
                            <?php foreach ($formations_a_mettre_a_jour as $formation): ?>
                                <?php
                                $jours_restants = $formation['jours_restants'];
                                $priorite_class = '';
                                $priorite_text = '';
                                $priorite_icon = '';
                                
                                if ($jours_restants <= 0) {
                                    $priorite_class = 'urgent';
                                    $priorite_text = 'URGENT - Formation échue';
                                    $priorite_icon = 'fas fa-exclamation-circle text-danger';
                                } elseif ($jours_restants <= 30) {
                                    $priorite_class = 'high';
                                    $priorite_text = 'HAUTE PRIORITÉ - ' . $jours_restants . ' jours restants';
                                    $priorite_icon = 'fas fa-exclamation-triangle text-warning';
                                } else {
                                    $priorite_class = 'medium';
                                    $priorite_text = 'PRIORITÉ MOYENNE - ' . $jours_restants . ' jours restants';
                                    $priorite_icon = 'fas fa-info-circle text-info';
                                }
                                ?>
                                
                                <div class="card formation-card mb-3 <?= $priorite_class ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h6 class="card-title mb-1">
                                                    <i class="<?= $priorite_icon ?>"></i>
                                                    <strong><?= htmlspecialchars($formation['code']) ?></strong>
                                                </h6>
                                                <p class="card-text mb-1"><?= htmlspecialchars($formation['intitule']) ?></p>
                                                <small class="text-muted">
                                                    Dernière formation: <?= date('d/m/Y', strtotime($formation['date_fin'])) ?> |
                                                    Prochaine échéance: <?= date('d/m/Y', strtotime($formation['prochaine_echeance_calculee'])) ?>
                                                </small>
                                                <br>
                                                <span class="badge bg-secondary"><?= $priorite_text ?></span>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <button class="btn btn-outline-primary btn-sm me-2" 
                                                        onclick="planifierFormation(<?= $formation['formation_id'] ?>, '<?= htmlspecialchars($formation['code']) ?>')">
                                                    <i class="fas fa-calendar-plus"></i> Planifier
                                                </button>
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="marquerEffectuee(<?= $formation['formation_id'] ?>, '<?= htmlspecialchars($formation['code']) ?>')">
                                                    <i class="fas fa-check"></i> Marquer comme effectuée
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formations planifiées -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-alt"></i> Mes Formations Planifiées (<?= count($formations_planifiees) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($formations_planifiees)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Aucune formation planifiée pour le moment.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Formation</th>
                                            <th>Date Prévue</th>
                                            <th>Centre</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($formations_planifiees as $fp): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($fp['code']) ?></strong></td>
                                                <td><?= htmlspecialchars($fp['intitule']) ?></td>
                                                <td>
                                                    <?= date('d/m/Y', strtotime($fp['date_prevue_debut'])) ?>
                                                    <?php if ($fp['date_prevue_fin']): ?>
                                                        - <?= date('d/m/Y', strtotime($fp['date_prevue_fin'])) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($fp['centre_formation_prevu'] ?? 'N/A') ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= ucfirst($fp['statut']) ?></span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-success btn-sm" 
                                                            onclick="marquerEffectuee(<?= $fp['formation_id'] ?>, '<?= htmlspecialchars($fp['code']) ?>')">
                                                        <i class="fas fa-check"></i> Effectuée
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
        </div>

        <!-- Bouton pour générer le rapport -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="ajax/generate_rapport_agent.php?agent_id=<?= $agent_id ?>&format=pdf" 
                   target="_blank" class="btn btn-primary btn-lg">
                    <i class="fas fa-file-pdf"></i> Générer mon rapport de formations
                </a>
            </div>
        </div>
    </div>

    <!-- Modal pour planifier une formation -->
    <div class="modal fade" id="planifierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Planifier une formation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="planifierForm">
                    <div class="modal-body">
                        <input type="hidden" id="formation_id_planifier" name="formation_id">
                        <input type="hidden" name="action" value="planifier">
                        
                        <div class="mb-3">
                            <label class="form-label">Formation</label>
                            <input type="text" class="form-control" id="formation_nom_planifier" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_prevue_debut" class="form-label">Date de début prévue *</label>
                                    <input type="date" class="form-control" id="date_prevue_debut" name="date_prevue_debut" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_prevue_fin" class="form-label">Date de fin prévue</label>
                                    <input type="date" class="form-control" id="date_prevue_fin" name="date_prevue_fin">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="centre_formation_prevu" class="form-label">Centre de formation prévu</label>
                            <select class="form-select" id="centre_formation_prevu" name="centre_formation_prevu">
                                <option value="">Sélectionner...</option>
                                <option value="ANACIM">ANACIM</option>
                                <option value="ENAC">ENAC</option>
                                <option value="ERNAM">ERNAM</option>
                                <option value="ITAerea">ITAerea</option>
                                <option value="IFURTA">IFURTA</option>
                                <option value="EPT">EPT</option>
                                <option value="IFNPC">IFNPC</option>
                                <option value="EMAERO services">EMAERO services</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Planifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal pour marquer comme effectuée -->
    <div class="modal fade" id="effectueeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Marquer comme effectuée</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="effectueeForm">
                    <div class="modal-body">
                        <input type="hidden" id="formation_id_effectuee" name="formation_id">
                        <input type="hidden" name="action" value="effectuee">
                        
                        <div class="mb-3">
                            <label class="form-label">Formation</label>
                            <input type="text" class="form-control" id="formation_nom_effectuee" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_debut" class="form-label">Date de début *</label>
                                    <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_fin" class="form-label">Date de fin *</label>
                                    <input type="date" class="form-control" id="date_fin" name="date_fin" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="centre_formation" class="form-label">Centre de formation *</label>
                            <select class="form-select" id="centre_formation" name="centre_formation" required>
                                <option value="">Sélectionner...</option>
                                <option value="ANACIM">ANACIM</option>
                                <option value="ENAC">ENAC</option>
                                <option value="ERNAM">ERNAM</option>
                                <option value="ITAerea">ITAerea</option>
                                <option value="IFURTA">IFURTA</option>
                                <option value="EPT">EPT</option>
                                <option value="IFNPC">IFNPC</option>
                                <option value="EMAERO services">EMAERO services</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">Marquer comme effectuée</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function planifierFormation(formationId, formationNom) {
            document.getElementById('formation_id_planifier').value = formationId;
            document.getElementById('formation_nom_planifier').value = formationNom;
            new bootstrap.Modal(document.getElementById('planifierModal')).show();
        }

        function marquerEffectuee(formationId, formationNom) {
            document.getElementById('formation_id_effectuee').value = formationId;
            document.getElementById('formation_nom_effectuee').value = formationNom;
            new bootstrap.Modal(document.getElementById('effectueeModal')).show();
        }

        // Gestion du formulaire de planification
        document.getElementById('planifierForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/update_formation_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Formation planifiée avec succès !');
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            });
        });

        // Gestion du formulaire pour marquer comme effectuée
        document.getElementById('effectueeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/update_formation_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Formation marquée comme effectuée avec succès !');
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            });
        });
    </script>
</body>
</html>
