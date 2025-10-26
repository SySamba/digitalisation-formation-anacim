<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Debug des Formations</h2>";

// Chercher spécifiquement la formation ID=4
echo "<h3>1. Recherche formation ID=4 :</h3>";
$stmt = $db->prepare("SELECT * FROM formations WHERE id = 4");
$stmt->execute();
$formation4 = $stmt->fetch(PDO::FETCH_ASSOC);
if ($formation4) {
    echo "<p style='color: green;'>✅ Formation ID=4 trouvée :</p>";
    echo "<pre>" . print_r($formation4, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ Formation ID=4 NON TROUVÉE</p>";
}

// Chercher par code SUR-INI-02
echo "<h3>2. Recherche par code SUR-INI-02 :</h3>";
$stmt = $db->prepare("SELECT * FROM formations WHERE code = 'SUR-INI-02'");
$stmt->execute();
$surIni02 = $stmt->fetch(PDO::FETCH_ASSOC);
if ($surIni02) {
    echo "<p style='color: green;'>✅ Formation SUR-INI-02 trouvée :</p>";
    echo "<pre>" . print_r($surIni02, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ Formation SUR-INI-02 NON TROUVÉE</p>";
}

// Lister les 10 premières formations
echo "<h3>3. Premières formations (ORDER BY id) :</h3>";
$stmt = $db->prepare("SELECT id, code, intitule FROM formations ORDER BY id LIMIT 10");
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th></tr>";
foreach ($formations as $f) {
    echo "<tr><td>{$f['id']}</td><td>{$f['code']}</td><td>{$f['intitule']}</td></tr>";
}
echo "</table>";

// Compter le total
echo "<h3>4. Statistiques :</h3>";
$stmt = $db->prepare("SELECT COUNT(*) as total FROM formations");
$stmt->execute();
$total = $stmt->fetch();
echo "<p>Total formations : <strong>{$total['total']}</strong></p>";

$stmt = $db->prepare("SELECT MIN(id) as min_id, MAX(id) as max_id FROM formations");
$stmt->execute();
$minmax = $stmt->fetch();
echo "<p>ID minimum : <strong>{$minmax['min_id']}</strong></p>";
echo "<p>ID maximum : <strong>{$minmax['max_id']}</strong></p>";

echo "<hr>";
echo "<h3>5. Solution :</h3>";
if (!$formation4 && $surIni02) {
    echo "<p style='background: yellow; padding: 10px;'>";
    echo "<strong>Problème identifié :</strong> La formation SUR-INI-02 existe mais avec l'ID {$surIni02['id']} au lieu de 4.<br>";
    echo "<strong>Solution :</strong> Utilisez l'ID {$surIni02['id']} dans vos liens de test.";
    echo "</p>";
    echo "<p><a href='admin_planning.php?section=planifier&agent_id=5&formation_id={$surIni02['id']}' style='background: #124c97; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>";
    echo "Tester avec le bon ID ({$surIni02['id']})";
    echo "</a></p>";
} elseif (!$formation4 && !$surIni02) {
    echo "<p style='background: red; color: white; padding: 10px;'>";
    echo "<strong>Problème :</strong> La formation SUR-INI-02 n'existe pas du tout dans la base de données.";
    echo "</p>";
}

?>
    $id = $formation['id'] ?? 'NO_ID';
    $code = $formation['code'] ?? 'NO_CODE';
    $intitule = $formation['intitule'] ?? 'NO_INTITULE';
    echo "<option value=\"$id\">$code - $intitule</option>";
}
echo "</select>";
?>
