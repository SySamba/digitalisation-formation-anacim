<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Debug Formation IDs - Besoins vs Dropdown</h2>";

// 1. Récupérer les formations des besoins (comme dans admin_planning.php)
$query_besoins = "
    SELECT DISTINCT 
        f.id as formation_id,
        f.code,
        f.intitule,
        'besoin' as source
    FROM formations f
    INNER JOIN planning p ON f.id = p.formation_id
    WHERE p.statut IN ('planifie', 'en_cours', 'termine')
    ORDER BY f.id
";

$stmt_besoins = $db->prepare($query_besoins);
$stmt_besoins->execute();
$formations_besoins = $stmt_besoins->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Formations dans les besoins (" . count($formations_besoins) . "):</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th><th>Catégorie</th></tr>";

foreach ($formations_besoins as $f) {
    $categorie = 'AUTRE';
    if (strpos($f['code'], 'SUR-FAM') !== false) {
        $categorie = 'SUR-FAM';
    } elseif (strpos($f['code'], 'SUR-INI') !== false) {
        $categorie = 'SUR-INI';
    } elseif (strpos($f['code'], 'SUR-FCE') !== false) {
        $categorie = 'SUR-FCE';
    } elseif (strpos($f['code'], 'SUR-FTS') !== false) {
        $categorie = 'SUR-FTS';
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($f['formation_id']) . "</td>";
    echo "<td>" . htmlspecialchars($f['code']) . "</td>";
    echo "<td>" . htmlspecialchars($f['intitule']) . "</td>";
    echo "<td><strong>" . $categorie . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

// 2. Récupérer les formations du dropdown (comme dans admin_planning.php)
$query_dropdown = "SELECT id, code, intitule FROM formations ORDER BY id";
$stmt_dropdown = $db->prepare($query_dropdown);
$stmt_dropdown->execute();
$formations_dropdown = $stmt_dropdown->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Formations dans le dropdown (" . count($formations_dropdown) . "):</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th><th>Catégorie</th></tr>";

foreach ($formations_dropdown as $f) {
    $categorie = 'AUTRE';
    if (strpos($f['code'], 'SUR-FAM') !== false) {
        $categorie = 'SUR-FAM';
    } elseif (strpos($f['code'], 'SUR-INI') !== false) {
        $categorie = 'SUR-INI';
    } elseif (strpos($f['code'], 'SUR-FCE') !== false) {
        $categorie = 'SUR-FCE';
    } elseif (strpos($f['code'], 'SUR-FTS') !== false) {
        $categorie = 'SUR-FTS';
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($f['id']) . "</td>";
    echo "<td>" . htmlspecialchars($f['code']) . "</td>";
    echo "<td>" . htmlspecialchars($f['intitule']) . "</td>";
    echo "<td><strong>" . $categorie . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

// 3. Comparer les IDs
$ids_besoins = array_column($formations_besoins, 'formation_id');
$ids_dropdown = array_column($formations_dropdown, 'id');

$ids_manquants_dropdown = array_diff($ids_besoins, $ids_dropdown);
$ids_manquants_besoins = array_diff($ids_dropdown, $ids_besoins);

echo "<h3>Analyse des différences:</h3>";

if (!empty($ids_manquants_dropdown)) {
    echo "<p><strong style='color: red;'>IDs dans besoins mais PAS dans dropdown:</strong> " . implode(', ', $ids_manquants_dropdown) . "</p>";
} else {
    echo "<p><strong style='color: green;'>✅ Tous les IDs des besoins sont dans le dropdown</strong></p>";
}

if (!empty($ids_manquants_besoins)) {
    echo "<p><strong style='color: blue;'>IDs dans dropdown mais PAS dans besoins:</strong> " . implode(', ', $ids_manquants_besoins) . "</p>";
}

// 4. Test spécifique pour SUR-INI, SUR-FCE, SUR-FAM
echo "<h3>Test spécifique pour les catégories problématiques:</h3>";

$categories_test = ['SUR-INI', 'SUR-FCE', 'SUR-FAM'];
foreach ($categories_test as $cat) {
    echo "<h4>Catégorie: $cat</h4>";
    
    // Dans besoins
    $besoins_cat = array_filter($formations_besoins, function($f) use ($cat) {
        return strpos($f['code'], $cat) !== false;
    });
    
    // Dans dropdown
    $dropdown_cat = array_filter($formations_dropdown, function($f) use ($cat) {
        return strpos($f['code'], $cat) !== false;
    });
    
    echo "<p>Dans besoins: " . count($besoins_cat) . " formations</p>";
    echo "<p>Dans dropdown: " . count($dropdown_cat) . " formations</p>";
    
    if (count($besoins_cat) > 0) {
        echo "<p>IDs besoins $cat: " . implode(', ', array_column($besoins_cat, 'formation_id')) . "</p>";
    }
    if (count($dropdown_cat) > 0) {
        echo "<p>IDs dropdown $cat: " . implode(', ', array_column($dropdown_cat, 'id')) . "</p>";
    }
}

?>
