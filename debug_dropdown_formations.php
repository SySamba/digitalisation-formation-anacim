<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Diagnostic: Formations dans la Base de Données</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
    .alert-warning { background-color: #fff3cd; border: 1px solid #ffc107; }
    .alert-success { background-color: #d4edda; border: 1px solid #28a745; }
    .alert-danger { background-color: #f8d7da; border: 1px solid #dc3545; }
</style>";

// 1. Compter le nombre total de formations
$stmt = $db->prepare("SELECT COUNT(*) as total FROM formations");
$stmt->execute();
$total = $stmt->fetch()['total'];

echo "<div class='alert alert-success'>";
echo "<strong>Total formations dans la base:</strong> {$total}";
echo "</div>";

// 2. Récupérer TOUTES les formations comme dans admin_planning.php
$stmt = $db->prepare("SELECT id, code, intitule, categorie, periodicite_mois FROM formations ORDER BY id");
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='alert alert-success'>";
echo "<strong>Formations récupérées par la requête:</strong> " . count($formations);
echo "</div>";

// 3. Compter par catégorie
$categories = [];
foreach ($formations as $formation) {
    $cat = $formation['categorie'] ?? 'UNKNOWN';
    if (!isset($categories[$cat])) {
        $categories[$cat] = 0;
    }
    $categories[$cat]++;
}

echo "<h3>Répartition par Catégorie</h3>";
echo "<table>";
echo "<tr><th>Catégorie</th><th>Nombre</th></tr>";
foreach ($categories as $cat => $count) {
    echo "<tr><td>{$cat}</td><td>{$count}</td></tr>";
}
echo "</table>";

// 4. Vérifier les codes spécifiques
$codes_to_check = ['SUR-FAM', 'SUR-INI', 'SUR-FCE', 'SUR-FTS'];
echo "<h3>Formations par Type de Code</h3>";
foreach ($codes_to_check as $code_prefix) {
    $matching = array_filter($formations, function($f) use ($code_prefix) {
        return strpos($f['code'], $code_prefix) !== false;
    });
    
    $count = count($matching);
    $class = $count > 0 ? 'alert-success' : 'alert-danger';
    
    echo "<div class='alert {$class}'>";
    echo "<strong>{$code_prefix}:</strong> {$count} formation(s) trouvée(s)";
    echo "</div>";
    
    if ($count > 0 && $count <= 5) {
        echo "<ul>";
        foreach ($matching as $f) {
            echo "<li>ID={$f['id']}: {$f['code']} - {$f['intitule']}</li>";
        }
        echo "</ul>";
    }
}

// 5. Afficher TOUTES les formations
echo "<h3>Liste Complète des Formations (comme dans le dropdown)</h3>";
echo "<table>";
echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th><th>Catégorie</th></tr>";

foreach ($formations as $formation) {
    $fid = $formation['id'] ?? '';
    $code = $formation['code'] ?? 'NO_CODE';
    $intitule = $formation['intitule'] ?? 'NO_TITLE';
    $categorie = $formation['categorie'] ?? 'NO_CATEGORY';
    
    echo "<tr>";
    echo "<td><strong>{$fid}</strong></td>";
    echo "<td>{$code}</td>";
    echo "<td>{$intitule}</td>";
    echo "<td>{$categorie}</td>";
    echo "</tr>";
}

echo "</table>";

// 6. Vérifier spécifiquement ID=4
echo "<h3>Vérification de la Formation ID=4</h3>";
$stmt = $db->prepare("SELECT * FROM formations WHERE id = 4");
$stmt->execute();
$formation_4 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($formation_4) {
    echo "<div class='alert alert-success'>";
    echo "<strong>Formation ID=4 EXISTE:</strong><br>";
    echo "Code: {$formation_4['code']}<br>";
    echo "Intitulé: {$formation_4['intitule']}<br>";
    echo "Catégorie: {$formation_4['categorie']}";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<strong>Formation ID=4 N'EXISTE PAS dans la base de données!</strong><br>";
    echo "C'est pour cela que la pré-sélection échoue.";
    echo "</div>";
}

// 7. Suggestion de correction
echo "<h3>Actions Recommandées</h3>";
if ($total == 0) {
    echo "<div class='alert alert-danger'>";
    echo "<strong>PROBLÈME:</strong> Aucune formation dans la base de données!<br>";
    echo "Vous devez exécuter le script SQL d'insertion: <code>database.sql</code>";
    echo "</div>";
} elseif ($total < 47) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>ATTENTION:</strong> Seulement {$total} formations sur 47 attendues.<br>";
    echo "Certaines formations n'ont pas été insérées. Vérifiez votre script SQL.";
    echo "</div>";
}

// Compter combien de formations de chaque type manquent
$expected_counts = [
    'FAMILIARISATION' => 2,
    'FORMATION_INITIALE' => 16,
    'FORMATION_COURS_EMPLOI' => 12,
    'FORMATION_TECHNIQUE' => 20
];

echo "<h4>Comparaison Attendu vs Réel</h4>";
echo "<table>";
echo "<tr><th>Type</th><th>Attendu</th><th>Réel</th><th>Statut</th></tr>";

foreach ($expected_counts as $cat_name => $expected) {
    $real = $categories[$cat_name] ?? 0;
    $status = $real == $expected ? '✅ OK' : '❌ MANQUANT';
    $class = $real == $expected ? '' : 'style="background-color: #f8d7da;"';
    
    echo "<tr {$class}>";
    echo "<td>{$cat_name}</td>";
    echo "<td>{$expected}</td>";
    echo "<td>{$real}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";
?>
