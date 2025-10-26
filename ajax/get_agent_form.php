<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Agent.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$agent = new Agent($db);

$agent_data = null;
$is_edit = false;

if (isset($_GET['id'])) {
    $agent_data = $agent->readOne($_GET['id']);
    $is_edit = true;
}
?>

<form id="agentForm" enctype="multipart/form-data">
    <?php if ($is_edit): ?>
        <input type="hidden" name="agent_id" value="<?= $agent_data['id'] ?>">
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="matricule" class="form-label">Matricule *</label>
                <input type="text" class="form-control" id="matricule" name="matricule" 
                       value="<?= $is_edit ? htmlspecialchars($agent_data['matricule']) : generateMatricule() ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="grade" class="form-label">Grade *</label>
                <select class="form-select" id="grade" name="grade" required onchange="toggleInspecteurFields()">
                    <option value="">Sélectionner un grade</option>
                    <option value="cadre_technique" <?= $is_edit && $agent_data['grade'] === 'cadre_technique' ? 'selected' : '' ?>>Cadre Technique</option>
                    <option value="agent_technique" <?= $is_edit && $agent_data['grade'] === 'agent_technique' ? 'selected' : '' ?>>Agent Technique</option>
                    <option value="inspecteur_stagiaire" <?= $is_edit && $agent_data['grade'] === 'inspecteur_stagiaire' ? 'selected' : '' ?>>Inspecteur Stagiaire</option>
                    <option value="inspecteur_titulaire" <?= $is_edit && $agent_data['grade'] === 'inspecteur_titulaire' ? 'selected' : '' ?>>Inspecteur Titulaire</option>
                    <option value="inspecteur_principal" <?= $is_edit && $agent_data['grade'] === 'inspecteur_principal' ? 'selected' : '' ?>>Inspecteur Principal</option>
                    <option value="verificateur_stagiaire" <?= $is_edit && $agent_data['grade'] === 'verificateur_stagiaire' ? 'selected' : '' ?>>Vérificateur Stagiaire</option>
                    <option value="verificateur_titulaire" <?= $is_edit && $agent_data['grade'] === 'verificateur_titulaire' ? 'selected' : '' ?>>Vérificateur Titulaire</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="prenom" class="form-label">Prénom *</label>
                <input type="text" class="form-control" id="prenom" name="prenom" 
                       value="<?= $is_edit ? htmlspecialchars($agent_data['prenom']) : '' ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="nom" class="form-label">Nom *</label>
                <input type="text" class="form-control" id="nom" name="nom" 
                       value="<?= $is_edit ? htmlspecialchars($agent_data['nom']) : '' ?>" required>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="date_recrutement" class="form-label">Date de Recrutement *</label>
                <input type="date" class="form-control" id="date_recrutement" name="date_recrutement" 
                       value="<?= $is_edit ? $agent_data['date_recrutement'] : '' ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="structure_attache" class="form-label">Structure Attachée</label>
                <input type="text" class="form-control" id="structure_attache" name="structure_attache" 
                       value="<?= $is_edit ? htmlspecialchars($agent_data['structure_attache'] ?? '') : '' ?>">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="domaine_activites" class="form-label">Domaine d'Activités</label>
                <input type="text" class="form-control" id="domaine_activites" name="domaine_activites" 
                       value="<?= $is_edit ? htmlspecialchars($agent_data['domaine_activites'] ?? '') : '' ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="specialite" class="form-label">Spécialité</label>
                <input type="text" class="form-control" id="specialite" name="specialite" 
                       value="<?= $is_edit ? htmlspecialchars($agent_data['specialite'] ?? '') : '' ?>">
            </div>
        </div>
    </div>

    <!-- Champs spécifiques pour Inspecteur Titulaire -->
    <div id="inspecteur_fields" style="display: <?= $is_edit && isInspecteurTitulaire($agent_data['grade']) ? 'block' : 'none' ?>;">
        <hr>
        <h6 class="text-primary">Informations Inspecteur Titulaire</h6>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="date_nomination" class="form-label">Date de Nomination *</label>
                    <input type="date" class="form-control" id="date_nomination" name="date_nomination" 
                           value="<?= $is_edit ? $agent_data['date_nomination'] : '' ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="numero_badge" class="form-label">Numéro de Badge *</label>
                    <input type="text" class="form-control" id="numero_badge" name="numero_badge" 
                           value="<?= $is_edit ? htmlspecialchars($agent_data['numero_badge'] ?? '') : '' ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="date_validite_badge" class="form-label">Date de Validité du Badge *</label>
                    <input type="date" class="form-control" id="date_validite_badge" name="date_validite_badge" 
                           value="<?= $is_edit ? $agent_data['date_validite_badge'] : '' ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="date_prestation_serment" class="form-label">Date de Prestation de Serment *</label>
                    <input type="date" class="form-control" id="date_prestation_serment" name="date_prestation_serment" 
                           value="<?= $is_edit ? $agent_data['date_prestation_serment'] : '' ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label for="photo" class="form-label">Photo de l'Agent</label>
        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
        <?php if ($is_edit && $agent_data['photo']): ?>
            <small class="form-text text-muted">Photo actuelle: <?= htmlspecialchars($agent_data['photo']) ?></small>
        <?php endif; ?>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-primary">
            <?= $is_edit ? 'Modifier' : 'Créer' ?> l'Agent
        </button>
    </div>
</form>

<script>
function toggleInspecteurFields() {
    const grade = document.getElementById('grade').value;
    const inspecteurFields = document.getElementById('inspecteur_fields');
    
    if (grade === 'inspecteur_titulaire') {
        inspecteurFields.style.display = 'block';
        // Rendre les champs obligatoires
        document.getElementById('date_nomination').required = true;
        document.getElementById('numero_badge').required = true;
        document.getElementById('date_validite_badge').required = true;
        document.getElementById('date_prestation_serment').required = true;
    } else {
        inspecteurFields.style.display = 'none';
        // Retirer l'obligation
        document.getElementById('date_nomination').required = false;
        document.getElementById('numero_badge').required = false;
        document.getElementById('date_validite_badge').required = false;
        document.getElementById('date_prestation_serment').required = false;
    }
}

document.getElementById('agentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const isEdit = formData.has('agent_id');
    
    fetch(isEdit ? 'ajax/update_agent.php' : 'ajax/create_agent.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload(); // Recharger la page pour voir les changements
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Une erreur est survenue lors de l\'enregistrement.');
    });
});

// Initialiser l'affichage des champs inspecteur si nécessaire
toggleInspecteurFields();
</script>
