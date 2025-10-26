<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Liste des Formations et leurs IDs</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .sur-fam { background-color: #e3f2fd; }
    .sur-ini { background-color: #fff9c4; }
    .sur-fce { background-color: #f3e5f5; }
    .sur-fts { background-color: #e8f5e9; }
</style>";

// Récupérer toutes les formations
$stmt = $db->prepare("SELECT id, code, intitule, categorie FROM formations ORDER BY id");
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Total formations:</strong> " . count($formations) . "</p>";

// Grouper par type
$groups = [
    'FAMILIARISATION' => [],
    'FORMATION_INITIALE' => [],
    'FORMATION_COURS_EMPLOI' => [],
    'FORMATION_TECHNIQUE' => []
];

foreach ($formations as $formation) {
    $groups[$formation['categorie']][] = $formation;
}

// Afficher par groupe
foreach ($groups as $categorie => $formations_cat) {
    if (empty($formations_cat)) continue;
    
    $class = '';
    $label = '';
    if (strpos($categorie, 'FAMILIARISATION') !== false) {
        $class = 'sur-fam';
        $label = 'FAMILIARISATION (SUR-FAM)';
    } elseif (strpos($categorie, 'INITIALE') !== false) {
        $class = 'sur-ini';
        $label = 'FORMATION INITIALE (SUR-INI)';
    } elseif (strpos($categorie, 'COURS_EMPLOI') !== false) {
        $class = 'sur-fce';
        $label = 'FORMATION EN COURS D\'EMPLOI (SUR-FCE)';
    } elseif (strpos($categorie, 'TECHNIQUE') !== false) {
        $class = 'sur-fts';
        $label = 'FORMATION TECHNIQUE/SPÉCIALISÉE (SUR-FTS)';
    }
    
    echo "<h3>{$label}</h3>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th></tr>";
    
    foreach ($formations_cat as $formation) {
        echo "<tr class='{$class}'>";
        echo "<td><strong>" . htmlspecialchars($formation['id']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($formation['code']) . "</td>";
        echo "<td>" . htmlspecialchars($formation['intitule']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<hr>";
echo "<h3>Plage d'IDs par catégorie</h3>";
echo "<ul>";
foreach ($groups as $categorie => $formations_cat) {
    if (empty($formations_cat)) continue;
    
    $ids = array_column($formations_cat, 'id');
    $min_id = min($ids);
    $max_id = max($ids);
    
    echo "<li><strong>{$categorie}:</strong> IDs de {$min_id} à {$max_id} (" . count($formations_cat) . " formations)</li>";
}
echo "</ul>";
?>
