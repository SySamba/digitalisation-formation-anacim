<?php
session_start();
require_once 'config/database.php';

// Simuler une session admin
$_SESSION['admin_logged_in'] = true;

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Test Soumission Formulaire Admin</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
    .btn-primary { background: #124c97; color: white; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .test-form { border: 2px solid #124c97; padding: 20px; margin: 20px 0; border-radius: 10px; }
</style>";

// Récupérer les données de l'agent 5 pour simuler exactement l'interface
$stmt = $pdo->prepare("SELECT * FROM agents WHERE id = 5");
$stmt->execute();
$agent = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM formations WHERE id = 1");
$stmt->execute();
$formation = $stmt->fetch();

echo "<div class='info'>";
echo "<h3>🎯 Simulation Interface Admin</h3>";
echo "<strong>Agent testé :</strong> {$agent['nom']} {$agent['prenom']} (ID: {$agent['id']})<br>";
echo "<strong>Formation testée :</strong> {$formation['code']} - {$formation['intitule']} (ID: {$formation['id']})";
echo "</div>";

?>

<div class="test-form">
    <h4>📝 Formulaire Identique à l'Interface Admin</h4>
    <p>Ce formulaire reproduit exactement celui de l'interface admin :</p>
    
    <form id="planificationFormAgent" method="POST">
        <input type="hidden" name="agent_id" value="5">
        <input type="hidden" name="formation_id" value="1">
        
        <div class="row">
            <div class="col-md-6">
                <label class="form-label">Centre de Formation Prévu *</label>
                <input type="text" class="form-control" name="centre_formation_prevu" value="ENAC" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Ville *</label>
                <input type="text" class="form-control" name="ville" value="Dakar" required>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <label class="form-label">Date Prévue Début *</label>
                <input type="date" class="form-control" name="date_prevue_debut" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date Prévue Fin *</label>
                <input type="date" class="form-control" name="date_prevue_fin" value="<?= date('Y-m-d', strtotime('+12 days')) ?>" required>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <label class="form-label">Pays *</label>
                <input type="text" class="form-control" name="pays" value="Sénégal" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Durée (jours) *</label>
                <input type="number" class="form-control" name="duree" value="5" required>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <label class="form-label">Perdiem</label>
                <input type="number" class="form-control" name="perdiem" value="50000">
            </div>
            <div class="col-md-6">
                <label class="form-label">Priorité *</label>
                <select class="form-select" name="priorite" required>
                    <option value="1">1 - Très élevé</option>
                    <option value="2" selected>2 - Moyen</option>
                    <option value="3">3 - Moins élevé</option>
                </select>
            </div>
        </div>
        
        <div class="mt-3">
            <label class="form-label">Commentaires</label>
            <textarea class="form-control" name="commentaires" rows="3">Test interface admin - <?= date('Y-m-d H:i:s') ?></textarea>
        </div>
        
        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Planifier Formation
            </button>
        </div>
    </form>
</div>

<div id="result-area"></div>

<script>
// Reproduire exactement le JavaScript de get_agent_details.php
function handlePlanificationFormAgent() {
    const form = document.getElementById('planificationFormAgent');
    if (!form) {
        console.error('Formulaire planificationFormAgent non trouvé');
        return;
    }
    
    console.log('✅ Gestionnaire d\'événement attaché au formulaire');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('🚀 Soumission du formulaire interceptée');
        
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        
        // Log des données envoyées
        console.log('📝 Données du formulaire:');
        for (let [key, value] of formData.entries()) {
            console.log(`  ${key}: ${value}`);
        }
        
        // Désactiver le bouton pendant la soumission
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Planification...';
        
        console.log('📡 Envoi vers ajax/save_planning.php...');
        
        fetch('ajax/save_planning.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('📥 Réponse reçue, status:', response.status);
            return response.text(); // Utiliser text() au lieu de json() pour voir la réponse brute
        })
        .then(responseText => {
            console.log('📄 Réponse brute:', responseText);
            
            // Afficher la réponse dans la page
            const resultArea = document.getElementById('result-area');
            resultArea.innerHTML = `
                <div class="info mt-4">
                    <h4>📡 Réponse du serveur :</h4>
                    <pre>${responseText}</pre>
                </div>
            `;
            
            // Essayer de parser en JSON
            try {
                const data = JSON.parse(responseText);
                console.log('✅ JSON parsé:', data);
                
                if (data.success) {
                    resultArea.innerHTML += `
                        <div class="success">
                            <h4>✅ Succès !</h4>
                            <p>${data.message}</p>
                        </div>
                    `;
                } else {
                    resultArea.innerHTML += `
                        <div class="error">
                            <h4>❌ Erreur !</h4>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
            } catch (e) {
                console.error('❌ Erreur parsing JSON:', e);
                resultArea.innerHTML += `
                    <div class="error">
                        <h4>❌ Erreur de parsing JSON !</h4>
                        <p>La réponse n'est pas du JSON valide</p>
                    </div>
                `;
            }
            
            // Réactiver le bouton
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        })
        .catch(error => {
            console.error('❌ Erreur fetch:', error);
            
            const resultArea = document.getElementById('result-area');
            resultArea.innerHTML = `
                <div class="error">
                    <h4>❌ Erreur de connexion !</h4>
                    <p>${error.message}</p>
                </div>
            `;
            
            // Réactiver le bouton
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        });
    });
}

// Initialiser quand la page est chargée
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Page chargée, initialisation...');
    handlePlanificationFormAgent();
});
</script>

<div class="info">
    <h4>🔍 Instructions de Test</h4>
    <ol>
        <li>Ouvrez la console du navigateur (F12)</li>
        <li>Cliquez sur "Planifier Formation"</li>
        <li>Regardez les logs dans la console</li>
        <li>Vérifiez la réponse affichée ci-dessous</li>
        <li>Retournez au <a href="test_planning_real_time.php" target="_blank">test temps réel</a> pour voir si le planning apparaît</li>
    </ol>
</div>

<br>
<a href="admin.php" class="btn btn-primary">← Retour Admin</a>
<a href="test_planning_real_time.php" class="btn btn-primary">Test Temps Réel</a>
