<?php
session_start();
$_SESSION['admin_logged_in'] = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Lien Direct</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 40px; max-width: 800px; margin: 0 auto; font-family: Arial, sans-serif; }
        .test-box { border: 3px solid #124c97; padding: 30px; margin: 20px 0; border-radius: 10px; background-color: #f8f9fa; }
        h2 { color: #124c97; }
        .big-button { padding: 20px 40px; font-size: 18px; margin: 10px; }
    </style>
</head>
<body>
    <h2>🔗 Test de Lien Direct vers Planning</h2>
    
    <div class="test-box">
        <h4>Cliquez sur ce bouton pour tester la pré-sélection :</h4>
        <p><strong>Agent ID :</strong> 2 (samba sy)</p>
        <p><strong>Formation ID :</strong> 4 (SUR-INI-02 - BASE/Sensibilisation)</p>
        
        <div class="text-center mt-4">
            <a href="admin_planning.php?section=planifier&agent_id=2&formation_id=4" 
               class="btn btn-primary big-button">
                <i class="fas fa-calendar-plus"></i> ALLER VERS PLANNING
            </a>
        </div>
        
        <hr class="my-4">
        
        <h5>Après avoir cliqué, vérifiez :</h5>
        <ol>
            <li>L'URL dans la barre d'adresse contient : <code>?section=planifier&agent_id=2&formation_id=4</code></li>
            <li>Un message bleu "Pré-sélection active" s'affiche</li>
            <li>Le dropdown Agent montre : <strong>2017081JD - samba sy</strong></li>
            <li>Le dropdown Formation montre: <strong>SUR-INI-02 - BASE/Sensibilisation</strong></li>
        </ol>
        
        <div class="alert alert-warning mt-3">
            <strong>⚠️ Si la formation n'est PAS sélectionnée :</strong>
            <ol>
                <li>Faites <strong>Clic droit → Afficher le code source</strong> (Ctrl+U)</li>
                <li>Cherchez (Ctrl+F) : <code>F: ID=4</code></li>
                <li>Notez ce qui est écrit : <code>F: ID=4, Pre=?, Sel=?</code></li>
                <li>Envoyez-moi cette ligne complète</li>
            </ol>
        </div>
    </div>
    
    <div class="test-box">
        <h4>🔍 Autres liens de test :</h4>
        <a href="admin_planning.php?section=planifier&agent_id=1&formation_id=1" class="btn btn-outline-primary">
            Test avec Agent 1, Formation 1
        </a>
        <a href="admin_planning.php?section=planifier&agent_id=3&formation_id=10" class="btn btn-outline-primary">
            Test avec Agent 3, Formation 10
        </a>
    </div>
</body>
</html>
