<?php
session_start();
require_once 'config/database.php';

// Simuler une session agent pour tester
$_SESSION['agent_logged_in'] = true;
$_SESSION['agent_id'] = 1; // Remplacez par un ID d'agent existant

echo "<h2>Test des formations</h2>";

// Test 1: Vérifier la connexion à la base
try {
    $database = new Database();
    $pdo = $database->getConnection();
    echo "<p>✅ Connexion base de données OK</p>";
} catch (Exception $e) {
    echo "<p>❌ Erreur connexion: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Vérifier si les tables existent
$tables = ['formations', 'formations_agents', 'diplomes'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        echo "<p>✅ Table '$table' existe</p>";
    } catch (Exception $e) {
        echo "<p>❌ Table '$table' manquante: " . $e->getMessage() . "</p>";
    }
}

// Test 3: Simuler une requête POST
echo "<h3>Test simulation POST</h3>";
$_POST['formations_effectuees'] = ['1', '2'];
$_POST['centre_formation'] = ['1' => 'interne', '2' => 'externe'];
$_POST['date_debut'] = ['1' => '2024-01-01', '2' => '2024-02-01'];
$_POST['date_fin'] = ['1' => '2024-01-05', '2' => '2024-02-05'];

// Inclure le script de sauvegarde
ob_start();
include 'ajax/save_formations_agent.php';
$output = ob_get_clean();

echo "<p>Réponse du script: <code>$output</code></p>";

// Test 4: Vérifier les données sauvegardées
try {
    $stmt = $pdo->prepare("SELECT * FROM formations_agents WHERE agent_id = ?");
    $stmt->execute([1]);
    $formations = $stmt->fetchAll();
    echo "<p>Formations sauvegardées: " . count($formations) . "</p>";
    foreach ($formations as $f) {
        echo "<p>- Formation ID: {$f['formation_id']}, Centre: {$f['centre_formation']}, Dates: {$f['date_debut']} à {$f['date_fin']}</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erreur lecture formations: " . $e->getMessage() . "</p>";
}
?>
