<?php
// Test rapide pour vérifier que get_agent_details.php fonctionne sans erreur

session_start();

// Simuler une session admin pour le test
$_SESSION['admin_logged_in'] = true;

$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 1;

echo "<h2>Test de get_agent_details.php pour l'agent ID: $agent_id</h2>";

echo "<h3>Test direct du fichier get_agent_details.php</h3>";

// Capturer la sortie du fichier get_agent_details.php
ob_start();
$_GET['id'] = $agent_id;
try {
    include 'ajax/get_agent_details.php';
    $output = ob_get_contents();
    ob_end_clean();
    
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<h4>✅ Succès ! Le fichier s'exécute sans erreur</h4>";
    echo "<p>Taille de la sortie: " . strlen($output) . " caractères</p>";
    echo "</div>";
    
    // Afficher un aperçu de la sortie (premiers 500 caractères)
    echo "<h4>Aperçu de la sortie:</h4>";
    echo "<div style='border: 1px solid #ddd; padding: 10px; background: #f9f9f9; max-height: 300px; overflow-y: auto;'>";
    echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "...</pre>";
    echo "</div>";
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<div style='border: 1px solid #f00; padding: 10px; margin: 10px 0; background: #fee;'>";
    echo "<h4>❌ Erreur détectée :</h4>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Fichier:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Ligne:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>Liens de test</h3>";
echo "<a href='admin.php' target='_blank'>Retour à admin.php</a><br>";
echo "<a href='ajax/get_agent_details.php?id=$agent_id' target='_blank'>Tester get_agent_details.php directement</a><br>";

// Test avec différents agents
echo "<hr>";
echo "<h3>Tester avec d'autres agents</h3>";
require_once 'config/database.php';
$database = new Database();
$pdo = $database->getConnection();

$query_agents = "SELECT id, prenom, nom, matricule FROM agents LIMIT 5";
$stmt_agents = $pdo->prepare($query_agents);
$stmt_agents->execute();
$agents_list = $stmt_agents->fetchAll();

foreach ($agents_list as $agent) {
    echo "<a href='?agent_id=" . $agent['id'] . "'>" . $agent['prenom'] . " " . $agent['nom'] . " (ID: " . $agent['id'] . ")</a><br>";
}
?>
