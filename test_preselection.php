<?php
// Test de pré-sélection - Simuler un clic sur le bouton "Planifier"
session_start();

// Simuler une session admin
$_SESSION['admin_logged_in'] = true;

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer un agent et une formation pour le test
$stmt = $db->prepare("SELECT id, matricule, prenom, nom FROM agents LIMIT 1");
$stmt->execute();
$agent = $stmt->fetch();

$stmt = $db->prepare("SELECT id, code, intitule FROM formations LIMIT 1");
$stmt->execute();
$formation = $stmt->fetch();

if ($agent && $formation) {
    $url = "admin_planning.php?section=planifier&agent_id={$agent['id']}&formation_id={$formation['id']}";
    
    echo "<h2>Test de Pré-sélection</h2>";
    echo "<p><strong>Agent sélectionné :</strong> {$agent['matricule']} - {$agent['prenom']} {$agent['nom']}</p>";
    echo "<p><strong>Formation sélectionnée :</strong> {$formation['code']} - {$formation['intitule']}</p>";
    echo "<p><strong>URL générée :</strong> <code>{$url}</code></p>";
    echo "<hr>";
    echo "<p>Cliquez sur le bouton ci-dessous pour tester la pré-sélection :</p>";
    echo "<a href='{$url}' class='btn btn-primary' style='display: inline-block; padding: 10px 20px; background-color: #124c97; color: white; text-decoration: none; border-radius: 5px;'>";
    echo "<i class='fas fa-calendar-plus'></i> Planifier cette Formation";
    echo "</a>";
    echo "<hr>";
    echo "<p><strong>Instructions :</strong></p>";
    echo "<ol>";
    echo "<li>Cliquez sur le bouton ci-dessus</li>";
    echo "<li>Vérifiez que l'agent et la formation sont automatiquement sélectionnés dans le formulaire</li>";
    echo "<li>Remplissez les autres champs (Centre, Dates, Ville, Pays, Durée, Perdiem, Priorité)</li>";
    echo "<li>Cliquez sur 'Planifier la Formation'</li>";
    echo "</ol>";
} else {
    echo "<p style='color: red;'>Erreur : Aucun agent ou formation trouvé dans la base de données.</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
}
h2 {
    color: #124c97;
}
code {
    background-color: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
}
</style>
