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
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$agent = new Agent($db);
$formation = new Formation($db);

$agent_id = $_SESSION['agent_id'];
$agent_data = $agent->readOne($agent_id);
// Récupérer les formations depuis la nouvelle table
$stmt_formations = $db->prepare("SELECT fa.*, f.code, f.intitule FROM formations_agents fa JOIN formations f ON fa.formation_id = f.id WHERE fa.agent_id = ? ORDER BY fa.created_at DESC");
$stmt_formations->execute([$agent_id]);
$formations_effectuees = $stmt_formations->fetchAll();
$formations_a_renouveler = $agent->getFormationsARenouveler($agent_id);
// Récupérer les diplômes depuis la nouvelle table
$stmt = $db->prepare("SELECT * FROM diplomes WHERE agent_id = ? ORDER BY created_at DESC");
$stmt->execute([$agent_id]);
$diplomes = $stmt->fetchAll();

// Récupérer toutes les formations pour permettre l'ajout
$formations = $formation->read();
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
    <title>Mon Profil - ANACIM</title>
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
        
        .agent-section {
            display: none;
        }
        
        .agent-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="agent_profile.php">
                <img src="logo-anacim.png" alt="ANACIM" class="logo-header">
                <span>Mon Profil - <?= htmlspecialchars($_SESSION['agent_nom']) ?></span>
            </a>
            <div class="navbar-nav ms-auto">
                <ul class="navbar-nav">
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
        <!-- Navigation buttons -->
        <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
            <button class="btn btn-primary active" onclick="showSection('info')" id="btn-info">
                <i class="fas fa-user"></i> Fiche Inspecteur
            </button>
            <button class="btn btn-outline-primary" onclick="showSection('diplomes')" id="btn-diplomes">
                <i class="fas fa-graduation-cap"></i> Diplômes & Attestations
            </button>
            <button class="btn btn-outline-primary" onclick="showSection('formations-non-effectuees')" id="btn-formations-non-effectuees">
                <i class="fas fa-check-circle"></i> Mes Formations
            </button>
        </div>

        <!-- Mes Informations -->
        <div class="agent-section active" id="info">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user"></i> Mes Informations Personnelles</h5>
                </div>
                <div class="card-body">
                    <form id="updateInfoForm" enctype="multipart/form-data">
                        <input type="hidden" name="agent_id" value="<?= $agent_id ?>">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Matricule</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($agent_data['matricule']) ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($agent_data['email'] ?? '') ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="grade" class="form-label">Grade</label>
                                    <select class="form-select" id="grade" name="grade" onchange="toggleInspecteurFields()">
                                        <option value="">Sélectionner un grade</option>
                                        <option value="cadre_technique" <?= ($agent_data['grade'] ?? '') === 'cadre_technique' ? 'selected' : '' ?>>Cadre Technique</option>
                                        <option value="agent_technique" <?= ($agent_data['grade'] ?? '') === 'agent_technique' ? 'selected' : '' ?>>Agent Technique</option>
                                        <option value="inspecteur_stagiaire" <?= ($agent_data['grade'] ?? '') === 'inspecteur_stagiaire' ? 'selected' : '' ?>>Inspecteur Stagiaire</option>
                                        <option value="inspecteur_titulaire" <?= ($agent_data['grade'] ?? '') === 'inspecteur_titulaire' ? 'selected' : '' ?>>Inspecteur Titulaire</option>
                                        <option value="inspecteur_principal" <?= ($agent_data['grade'] ?? '') === 'inspecteur_principal' ? 'selected' : '' ?>>Inspecteur Principal</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_recrutement" class="form-label">Date de Recrutement</label>
                                    <input type="date" class="form-control" id="date_recrutement" name="date_recrutement" 
                                           value="<?= $agent_data['date_recrutement'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="structure_attache" class="form-label">Structure Attachée</label>
                                    <input type="text" class="form-control" id="structure_attache" name="structure_attache" 
                                           value="<?= htmlspecialchars($agent_data['structure_attache'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="domaine_activites" class="form-label">Domaine d'Activités</label>
                                    <input type="text" class="form-control" id="domaine_activites" name="domaine_activites" 
                                           value="<?= htmlspecialchars($agent_data['domaine_activites'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="specialiste" class="form-label">Spécialiste</label>
                                    <input type="text" class="form-control" id="specialiste" name="specialiste" 
                                           value="<?= htmlspecialchars($agent_data['specialiste'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Section Photo pour tous les agents -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="photo" class="form-label">Photo de profil</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <small class="form-text text-muted">Formats acceptés: JPG, PNG, GIF (max 2MB)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <?php if ($agent_data['photo']): ?>
                                        <img src="uploads/photos/<?= htmlspecialchars($agent_data['photo']) ?>" 
                                             class="img-fluid rounded" alt="Photo de profil" style="max-height: 150px;">
                                    <?php else: ?>
                                        <div class="border rounded p-3 bg-light">
                                            <i class="fas fa-user fa-3x text-muted"></i>
                                            <p class="mt-2 text-muted small">Aucune photo</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Champs spécifiques pour Inspecteur Titulaire -->
                        <div id="inspecteur_fields" style="display: <?= ($agent_data['grade'] ?? '') === 'inspecteur_titulaire' ? 'block' : 'none' ?>;">
                            <hr>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="matricule" class="form-label">Matricule</label>
                                                    <input type="text" class="form-control" id="matricule" name="matricule" 
                                                           value="<?= htmlspecialchars($agent_data['matricule']) ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?= htmlspecialchars($agent_data['email']) ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="prenom" class="form-label">Prénom</label>
                                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                                           value="<?= htmlspecialchars($agent_data['prenom']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="nom" class="form-label">Nom</label>
                                                    <input type="text" class="form-control" id="nom" name="nom" 
                                                           value="<?= htmlspecialchars($agent_data['nom']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Mettre à jour mes informations
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Mes Diplômes et Attestations -->
        <div class="agent-section" id="diplomes">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-graduation-cap"></i> Mes Diplômes et Attestations</h5>
                </div>
                <div class="card-body">
                    <form id="diplomesForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="agent_id" value="<?= $agent_id ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="cv" class="form-label">CV</label>
                                <input type="file" class="form-control" id="cv" name="cv" accept=".pdf,.doc,.docx">
                                <small class="text-muted">Format: PDF, DOC, DOCX</small>
                            </div>
                            <div class="col-md-4">
                                <label for="diplome" class="form-label">Diplôme</label>
                                <input type="file" class="form-control" id="diplome" name="diplome" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="text-muted">Format: PDF, DOC, DOCX, JPG, PNG</small>
                            </div>
                            <div class="col-md-4">
                                <label for="attestation" class="form-label">Attestation</label>
                                <input type="file" class="form-control" id="attestation" name="attestation" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="text-muted">Format: PDF, DOC, DOCX, JPG, PNG</small>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer mes documents
                            </button>
                        </div>
                    </form>
                    
                    <!-- Liste des documents existants -->
                    <?php if (!empty($diplomes)): ?>
                        <hr class="my-4">
                        <h6 class="text-primary mb-3">Documents déjà enregistrés</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Fichier</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($diplomes as $diplome): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($diplome['type_diplome']) ?></td>
                                            <td>
                                                <?php if ($diplome['fichier_path']): ?>
                                                    <a href="uploads/diplomes/<?= htmlspecialchars($diplome['fichier_path']) ?>" 
                                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i> Voir
                                                    </a>
                                                <?php endif; ?>
                                            </td>
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
            </div>
        </div>

        <!-- Formations Non Effectuées -->
        <div class="agent-section" id="formations-non-effectuees">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-times-circle"></i> Toutes les Formations Disponibles</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Sélectionnez les formations que vous avez effectuées en cochant les cases correspondantes.
                    </div>
                    
                    <form id="selectFormationsForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="agent_id" value="<?= $agent_id ?>">
                        
                        <?php foreach ($formations_by_category as $categorie => $formations_cat): ?>
                            <div class="formation-category card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><?= getCategorieLabel($categorie) ?></h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($formations_cat as $formation): ?>
                                        <?php 
                                        $formations_effectuees_ids = array_column($formations_effectuees, 'formation_id');
                                        $is_completed = in_array($formation['id'], $formations_effectuees_ids);
                                        ?>
                                        <div class="formation-item mb-3 p-3 border rounded <?= $is_completed ? 'bg-light' : '' ?>" data-formation-id="<?= $formation['id'] ?>">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       id="formation_<?= $formation['id'] ?>" 
                                                       name="formations_effectuees[]" 
                                                       value="<?= $formation['id'] ?>"
                                                       <?= $is_completed ? 'checked' : '' ?>
                                                       onchange="toggleFormationDetails(<?= $formation['id'] ?>)">
                                                <label class="form-check-label" for="formation_<?= $formation['id'] ?>">
                                                    <strong><?= htmlspecialchars($formation['code']) ?></strong> - 
                                                    <?= htmlspecialchars($formation['intitule']) ?>
                                                    <br><small class="text-muted">Ressource: <?= $formation['ressource'] ?> | Périodicité: <?= $formation['periodicite_mois'] ?> mois</small>
                                                </label>
                                            </div>
                                            
                                            <!-- Détails de la formation (masqués par défaut) -->
                                            <div id="details_<?= $formation['id'] ?>" class="formation-details mt-3" style="display: <?= $is_completed ? 'block' : 'none' ?>;">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label class="form-label">Centre de formation</label>
                                                        <select class="form-select formation-required" name="centre_formation[<?= $formation['id'] ?>]">
                                                            <option value="">Sélectionner...</option>
                                                            <option value="interne" <?= $is_completed && isset($formations_effectuees[array_search($formation['id'], $formations_effectuees_ids)]['centre_formation']) && $formations_effectuees[array_search($formation['id'], $formations_effectuees_ids)]['centre_formation'] == 'interne' ? 'selected' : '' ?>>Formation Interne</option>
                                                            <option value="externe" <?= $is_completed && isset($formations_effectuees[array_search($formation['id'], $formations_effectuees_ids)]['centre_formation']) && $formations_effectuees[array_search($formation['id'], $formations_effectuees_ids)]['centre_formation'] == 'externe' ? 'selected' : '' ?>>Formation Externe</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Date de début</label>
                                                        <input type="date" class="form-control" 
                                                               name="date_debut[<?= $formation['id'] ?>]" 
                                                               value="<?= $is_completed ? ($formations_effectuees[array_search($formation['id'], $formations_effectuees_ids)]['date_debut'] ?? '') : '' ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Date de fin</label>
                                                        <input type="date" class="form-control" 
                                                               name="date_fin[<?= $formation['id'] ?>]" 
                                                               value="<?= $is_completed ? ($formations_effectuees[array_search($formation['id'], $formations_effectuees_ids)]['date_fin'] ?? '') : '' ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Certificat</label>
                                                        <input type="file" class="form-control" 
                                                               name="certificat[<?= $formation['id'] ?>]" 
                                                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                        <small class="text-muted">PDF, DOC, DOCX, JPG, PNG</small>
                                                        <?php if ($is_completed && !empty($formations_effectuees[array_search($formation['id'], $formations_effectuees_ids)]['fichier_joint'])): ?>
                                                            <div class="mt-1">
                                                                <a href="uploads/formations/<?= htmlspecialchars($formations_effectuees[array_search($formation['id'], $formations_effectuees_ids)]['fichier_joint']) ?>" 
                                                                   target="_blank" class="btn btn-sm btn-outline-success">
                                                                    <i class="fas fa-file"></i> Voir certificat
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer mes formations
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId) {
            // Masquer toutes les sections
            document.querySelectorAll('.agent-section').forEach(section => {
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

        // Gestion des formations
        document.getElementById('selectFormationsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Validation côté client
            const checkedFormations = this.querySelectorAll('input[name="formations_effectuees[]"]:checked');
            
            // Vérifier que pour chaque formation cochée, les champs requis sont remplis
            let validationError = false;
            let errorMessage = '';
            
            checkedFormations.forEach(checkbox => {
                const formationId = checkbox.value;
                const centre = this.querySelector(`select[name="centre_formation[${formationId}]"]`);
                const dateDebut = this.querySelector(`input[name="date_debut[${formationId}]"]`);
                const dateFin = this.querySelector(`input[name="date_fin[${formationId}]"]`);
                
                if (!centre.value) {
                    validationError = true;
                    errorMessage = 'Veuillez sélectionner le centre de formation pour toutes les formations cochées';
                }
                if (!dateDebut.value) {
                    validationError = true;
                    errorMessage = 'Veuillez remplir la date de début pour toutes les formations cochées';
                }
                if (!dateFin.value) {
                    validationError = true;
                    errorMessage = 'Veuillez remplir la date de fin pour toutes les formations cochées';
                }
            });
            
            if (validationError) {
                alert(errorMessage);
                return;
            }
            
            // Désactiver le bouton et changer le texte
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
            
            const formData = new FormData(this);
            
            console.log('Sending request to save_formations_agent.php');
            fetch('ajax/save_formations_agent.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received:', response);
                if (!response.ok) {
                    throw new Error('Erreur réseau: ' + response.status);
                }
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Réponse invalide du serveur');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Enregistré !';
                    submitBtn.className = 'btn btn-success';
                    alert('Formations enregistrées avec succès !');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    submitBtn.className = 'btn btn-primary';
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-primary';
                alert('Une erreur est survenue: ' + error.message);
            });
        });

        // Gestion des diplômes
        document.getElementById('diplomesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Désactiver le bouton et changer le texte
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
            
            const formData = new FormData(this);
            
            fetch('ajax/save_diplomes_agent.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Enregistré !';
                    submitBtn.className = 'btn btn-success';
                    alert('Documents enregistrés avec succès !');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                alert('Une erreur est survenue: ' + error.message);
            });
        });

        function toggleFormationDetails(formationId) {
            const checkbox = document.getElementById('formation_' + formationId);
            const details = document.getElementById('details_' + formationId);
            const centre = details.querySelector(`select[name="centre_formation[${formationId}]"]`);
            const dateDebut = details.querySelector(`input[name="date_debut[${formationId}]"]`);
            const dateFin = details.querySelector(`input[name="date_fin[${formationId}]"]`);
            
            if (checkbox.checked) {
                details.style.display = 'block';
                // Ajouter required quand la formation est cochée
                centre.required = true;
                dateDebut.required = true;
                dateFin.required = true;
            } else {
                details.style.display = 'none';
                // Retirer required quand la formation n'est pas cochée
                centre.required = false;
                dateDebut.required = false;
                dateFin.required = false;
                // Vider les valeurs
                centre.value = '';
                dateDebut.value = '';
                dateFin.value = '';
            }
        }

        function renewFormation(formationId) {
            // Pré-remplir le formulaire d'ajout avec cette formation
            document.getElementById('formation_id').value = formationId;
            showSection('add-formation');
        }

        function editFormation(formationId) {
            // Fonction pour modifier une formation
            console.log('Modifier formation:', formationId);
        }

        function deleteDiplome(diplomeId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce diplôme ?')) {
                fetch('ajax/delete_diplome.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({diplome_id: diplomeId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Diplôme supprimé avec succès !');
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Une erreur est survenue.');
                });
            }
        }

        function addThisFormation(formationId) {
            // Pré-remplir le formulaire d'ajout avec cette formation
            document.getElementById('formation_id').value = formationId;
            showSection('add-formation');
        }

        function showAddDiplomeForm() {
            // Fonction pour afficher le formulaire d'ajout de diplôme
            alert('Fonctionnalité d\'ajout de diplôme à implémenter');
        }

        function toggleInspecteurFields() {
            const grade = document.getElementById('grade').value;
            const inspecteurFields = document.getElementById('inspecteur_fields');
            
            if (grade === 'inspecteur_titulaire') {
                inspecteurFields.style.display = 'block';
            } else {
                inspecteurFields.style.display = 'none';
            }
        }

        function showAddPlanningForm() {
            // Fonction pour afficher le formulaire d'ajout de planning
            alert('Fonctionnalité d\'ajout de planning à implémenter');
        }

        // Ajouter l'événement de soumission au formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const updateForm = document.getElementById('updateInfoForm');
            if (updateForm) {
                updateForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitUpdateForm();
                });
            }
        });

        // Fonction pour soumettre le formulaire de mise à jour
        function submitUpdateForm() {
            const form = document.getElementById('updateInfoForm');
            const formData = new FormData(form);
            
            // Debug: Vérifier si le fichier photo est inclus
            console.log('FormData contents:');
            for (let [key, value] of formData.entries()) {
                console.log(key, value);
            }

            fetch('ajax/update_agent_info.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    alert('Informations mises à jour avec succès!');
                    if (data.debug) {
                        console.log('Debug info:', data.debug);
                    }
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Une erreur est survenue.');
            });
        }
    </script>
</body>
</html>
