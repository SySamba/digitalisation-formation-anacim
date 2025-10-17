<?php
session_start();

// Vérifier si l'agent est connecté
if (!isset($_SESSION['agent_logged_in']) || $_SESSION['agent_logged_in'] !== true) {
    header('Location: agent_login.php');
    exit;
}

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$agent_id = $_SESSION['agent_id'];

// Récupérer les diplômes existants
$stmt = $db->prepare("SELECT * FROM diplomes WHERE agent_id = ? ORDER BY created_at DESC");
$stmt->execute([$agent_id]);
$diplomes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Diplômes et Attestations - ANACIM</title>
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
        
        .document-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }
        
        .remove-document {
            color: #dc3545;
            cursor: pointer;
        }
        
        .remove-document:hover {
            color: #a71d2a;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="agent_profile.php">
                <img src="logo-anacim.png" alt="ANACIM" class="logo-header">
                <span>Mes Diplômes et Attestations - <?= htmlspecialchars($_SESSION['agent_nom']) ?></span>
            </a>
            <div class="navbar-nav ms-auto">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="agent_profile.php"><i class="fas fa-arrow-left"></i> Retour au profil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agent_logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Formulaire d'ajout de documents multiples -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> Ajouter des Documents</h5>
            </div>
            <div class="card-body">
                <form id="multipleDocumentsForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="agent_id" value="<?= $agent_id ?>">
                    
                    <div id="documentsContainer">
                        <!-- Premier document par défaut -->
                        <div class="document-item" data-index="0">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Document 1</h6>
                                <span class="remove-document" onclick="removeDocument(0)" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </span>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Type de document *</label>
                                    <select class="form-select" name="type_diplome[]" required>
                                        <option value="">Sélectionner...</option>
                                        <option value="cv">CV</option>
                                        <option value="diplome">Diplôme</option>
                                        <option value="attestation">Attestation</option>
                                        <option value="certificat">Certificat</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Titre du document *</label>
                                    <input type="text" class="form-control" name="titre[]" placeholder="Ex: Diplôme d'ingénieur" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Fichier *</label>
                                    <input type="file" class="form-control" name="documents[]" 
                                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                    <small class="text-muted">PDF, DOC, DOCX, JPG, PNG (max 5MB)</small>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description[]" rows="2" 
                                              placeholder="Description optionnelle du document"></textarea>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date d'obtention</label>
                                    <input type="date" class="form-control" name="date_obtention[]">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Établissement</label>
                                    <input type="text" class="form-control" name="etablissement[]" 
                                           placeholder="Nom de l'établissement">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-outline-primary" onclick="addDocument()">
                            <i class="fas fa-plus"></i> Ajouter un autre document
                        </button>
                        <button type="submit" class="btn btn-primary ms-2">
                            <i class="fas fa-save"></i> Enregistrer tous les documents
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des documents existants -->
        <?php if (!empty($diplomes)): ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-folder-open"></i> Documents Enregistrés</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Titre</th>
                                <th>Établissement</th>
                                <th>Date d'obtention</th>
                                <th>Date d'ajout</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diplomes as $diplome): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= ucfirst(htmlspecialchars($diplome['type_diplome'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($diplome['titre'] ?? 'Sans titre') ?></strong>
                                        <?php if (!empty($diplome['description'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($diplome['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($diplome['etablissement'] ?? '-') ?></td>
                                    <td><?= $diplome['date_obtention'] ? date('d/m/Y', strtotime($diplome['date_obtention'])) : '-' ?></td>
                                    <td><?= date('d/m/Y', strtotime($diplome['created_at'])) ?></td>
                                    <td>
                                        <?php if ($diplome['fichier_path']): ?>
                                            <a href="uploads/diplomes/<?= htmlspecialchars($diplome['fichier_path']) ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                        <?php endif; ?>
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
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let documentIndex = 1;

        function addDocument() {
            const container = document.getElementById('documentsContainer');
            const newIndex = documentIndex++;
            
            const documentDiv = document.createElement('div');
            documentDiv.className = 'document-item';
            documentDiv.setAttribute('data-index', newIndex);
            
            documentDiv.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Document ${newIndex + 1}</h6>
                    <span class="remove-document" onclick="removeDocument(${newIndex})">
                        <i class="fas fa-times"></i>
                    </span>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Type de document *</label>
                        <select class="form-select" name="type_diplome[]" required>
                            <option value="">Sélectionner...</option>
                            <option value="cv">CV</option>
                            <option value="diplome">Diplôme</option>
                            <option value="attestation">Attestation</option>
                            <option value="certificat">Certificat</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Titre du document *</label>
                        <input type="text" class="form-control" name="titre[]" placeholder="Ex: Diplôme d'ingénieur" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Fichier *</label>
                        <input type="file" class="form-control" name="documents[]" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <small class="text-muted">PDF, DOC, DOCX, JPG, PNG (max 5MB)</small>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description[]" rows="2" 
                                  placeholder="Description optionnelle du document"></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date d'obtention</label>
                        <input type="date" class="form-control" name="date_obtention[]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Établissement</label>
                        <input type="text" class="form-control" name="etablissement[]" 
                               placeholder="Nom de l'établissement">
                    </div>
                </div>
            `;
            
            container.appendChild(documentDiv);
            updateRemoveButtons();
        }

        function removeDocument(index) {
            const documentDiv = document.querySelector(`[data-index="${index}"]`);
            if (documentDiv) {
                documentDiv.remove();
                updateRemoveButtons();
                updateDocumentNumbers();
            }
        }

        function updateRemoveButtons() {
            const documents = document.querySelectorAll('.document-item');
            documents.forEach((doc, index) => {
                const removeBtn = doc.querySelector('.remove-document');
                if (documents.length > 1) {
                    removeBtn.style.display = 'inline';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
        }

        function updateDocumentNumbers() {
            const documents = document.querySelectorAll('.document-item');
            documents.forEach((doc, index) => {
                const title = doc.querySelector('h6');
                title.textContent = `Document ${index + 1}`;
            });
        }

        // Gestion du formulaire
        document.getElementById('multipleDocumentsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Validation
            const documents = this.querySelectorAll('input[name="documents[]"]');
            const titres = this.querySelectorAll('input[name="titre[]"]');
            const types = this.querySelectorAll('select[name="type_diplome[]"]');
            
            let hasFiles = false;
            for (let i = 0; i < documents.length; i++) {
                if (documents[i].files.length > 0) {
                    hasFiles = true;
                    if (!titres[i].value.trim()) {
                        alert('Veuillez saisir un titre pour tous les documents sélectionnés.');
                        return;
                    }
                    if (!types[i].value) {
                        alert('Veuillez sélectionner un type pour tous les documents sélectionnés.');
                        return;
                    }
                }
            }
            
            if (!hasFiles) {
                alert('Veuillez sélectionner au moins un fichier à uploader.');
                return;
            }
            
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
                    submitBtn.className = 'btn btn-success ms-2';
                    alert(`${data.count} document(s) enregistré(s) avec succès !`);
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    submitBtn.className = 'btn btn-primary ms-2';
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-primary ms-2';
                alert('Une erreur est survenue: ' + error.message);
            });
        });

        function deleteDiplome(diplomeId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce document ?')) {
                fetch('ajax/delete_diplome.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        diplome_id: diplomeId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Document supprimé avec succès !');
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
    </script>
</body>
</html>
