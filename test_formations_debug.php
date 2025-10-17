<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer les formations
$stmt = $db->prepare("SELECT id, code, intitule FROM formations LIMIT 10");
$stmt->execute();
$formations = $stmt->fetchAll();

echo "<h3>Debug Formations Data:</h3>";
echo "<pre>";
foreach ($formations as $formation) {
    echo "ID: " . $formation['id'] . "\n";
    echo "Code: " . $formation['code'] . "\n";
    echo "Intitule: " . $formation['intitule'] . "\n";
    echo "Combined: " . $formation['code'] . ' - ' . $formation['intitule'] . "\n";
    echo "HTML Escaped: " . htmlspecialchars($formation['code'] . ' - ' . $formation['intitule']) . "\n";
    echo "---\n";
}
echo "</pre>";
?>
