<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Test direct de la requête
$stmt = $db->prepare("SELECT id, code, intitule FROM formations LIMIT 5");
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Debug Formations Data:</h3>";
echo "<pre>";
var_dump($formations);
echo "</pre>";

echo "<h3>HTML Generation Test:</h3>";
echo "<select>";
echo "<option value=''>Sélectionner...</option>";
foreach ($formations as $formation) {
    $id = $formation['id'] ?? 'NO_ID';
    $code = $formation['code'] ?? 'NO_CODE';
    $intitule = $formation['intitule'] ?? 'NO_INTITULE';
    echo "<option value=\"$id\">$code - $intitule</option>";
}
echo "</select>";
?>
