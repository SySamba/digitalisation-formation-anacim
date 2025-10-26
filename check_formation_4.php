<?php
// Script pour vérifier si la formation ID=4 existe dans la base de données
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Vérification de la formation ID=4</h2>";

// 1. Vérifier si la formation ID=4 existe
echo "<h3>1. Recherche directe de la formation ID=4</h3>";
$stmt = $db->prepare("SELECT * FROM formations WHERE id = 4");
$stmt->execute();
$formation_4 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($formation_4) {
    echo "<div style='color: green;'>";
    echo "<strong>✅ Formation ID=4 trouvée !</strong><br>";
    echo "Code: " . htmlspecialchars($formation_4['code']) . "<br>";
    echo "Intitulé: " . htmlspecialchars($formation_4['intitule']) . "<br>";
    echo "Catégorie: " . htmlspecialchars($formation_4['categorie']) . "<br>";
    echo "</div>";
} else {
    echo "<div style='color: red;'>";
    echo "<strong>❌ Formation ID=4 NON TROUVÉE</strong>";
    echo "</div>";
}

// 2. Vérifier toutes les formations avec leurs IDs
echo "<h3>2. Liste de toutes les formations (ORDER BY id)</h3>";
$stmt = $db->prepare("SELECT id, code, intitule FROM formations ORDER BY id");
$stmt->execute();
$all_formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Nombre total de formations:</strong> " . count($all_formations) . "</p>";

if (count($all_formations) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th></tr>";
    
    foreach ($all_formations as $formation) {
        $style = ($formation['id'] == 4) ? "background-color: yellow;" : "";
        echo "<tr style='$style'>";
        echo "<td>" . htmlspecialchars($formation['id']) . "</td>";
        echo "<td>" . htmlspecialchars($formation['code']) . "</td>";
        echo "<td>" . htmlspecialchars($formation['intitule']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Aucune formation trouvée dans la base de données.</p>";
}

// 3. Rechercher SUR-INI-02 par code
echo "<h3>3. Recherche par code 'SUR-INI-02'</h3>";
$stmt = $db->prepare("SELECT * FROM formations WHERE code LIKE '%SUR-INI-02%'");
$stmt->execute();
$sur_ini_formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($sur_ini_formations) > 0) {
    echo "<div style='color: green;'>";
    echo "<strong>✅ Formation(s) SUR-INI-02 trouvée(s) !</strong><br>";
    foreach ($sur_ini_formations as $formation) {
        echo "ID: " . $formation['id'] . " - Code: " . htmlspecialchars($formation['code']) . " - " . htmlspecialchars($formation['intitule']) . "<br>";
    }
    echo "</div>";
} else {
    echo "<div style='color: red;'>";
    echo "<strong>❌ Aucune formation avec le code SUR-INI-02 trouvée</strong>";
    echo "</div>";
}

// 4. Statistiques des IDs
echo "<h3>4. Statistiques des IDs</h3>";
if (count($all_formations) > 0) {
    $ids = array_column($all_formations, 'id');
    echo "<p><strong>ID minimum:</strong> " . min($ids) . "</p>";
    echo "<p><strong>ID maximum:</strong> " . max($ids) . "</p>";
    echo "<p><strong>IDs manquants entre 1 et " . max($ids) . ":</strong> ";
    
    $missing_ids = [];
    for ($i = 1; $i <= max($ids); $i++) {
        if (!in_array($i, $ids)) {
            $missing_ids[] = $i;
        }
    }
    
    if (count($missing_ids) > 0) {
        echo implode(', ', $missing_ids);
    } else {
        echo "Aucun";
    }
    echo "</p>";
}
?>
