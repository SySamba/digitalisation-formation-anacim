<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>Nouveaux IDs des Formations (Après Réinsertion)</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; position: sticky; top: 0; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .highlight { background-color: #ffeb3b !important; font-weight: bold; }
</style>";

// Récupérer TOUTES les formations avec leurs nouveaux IDs
$stmt = $db->prepare("SELECT id, code, intitule, categorie FROM formations ORDER BY id");
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Total formations:</strong> " . count($formations) . "</p>";

echo "<table>";
echo "<tr>";
echo "<th>Nouveau ID</th>";
echo "<th>Code</th>";
echo "<th>Intitulé</th>";
echo "<th>Catégorie</th>";
echo "</tr>";

foreach ($formations as $formation) {
    $highlight = ($formation['id'] == 1) ? 'class="highlight"' : '';
    echo "<tr {$highlight}>";
    echo "<td><strong>" . htmlspecialchars($formation['id']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($formation['code']) . "</td>";
    echo "<td>" . htmlspecialchars($formation['intitule']) . "</td>";
    echo "<td>" . htmlspecialchars($formation['categorie']) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h3>Plage d'IDs par Code</h3>";
echo "<ul>";
echo "<li><strong>SUR-FAM:</strong> IDs ";
$fam = array_filter($formations, fn($f) => strpos($f['code'], 'SUR-FAM') !== false);
echo implode(", ", array_column($fam, 'id'));
echo "</li>";

echo "<li><strong>SUR-INI:</strong> IDs ";
$ini = array_filter($formations, fn($f) => strpos($f['code'], 'SUR-INI') !== false);
echo implode(", ", array_column($ini, 'id'));
echo "</li>";

echo "<li><strong>SUR-FCE:</strong> IDs ";
$fce = array_filter($formations, fn($f) => strpos($f['code'], 'SUR-FCE') !== false);
echo implode(", ", array_column($fce, 'id'));
echo "</li>";

echo "<li><strong>SUR-FTS:</strong> IDs ";
$fts = array_filter($formations, fn($f) => strpos($f['code'], 'SUR-FTS') !== false);
echo implode(", ", array_column($fts, 'id'));
echo "</li>";
echo "</ul>";

echo "<hr>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border: 1px solid #ffc107;'>";
echo "<h3>⚠️ IMPORTANT</h3>";
echo "<p><strong>Les IDs ont changé après la réinsertion!</strong></p>";
echo "<p>Vous devez maintenant:</p>";
echo "<ol>";
echo "<li>Rafraîchir complètement la page <code>admin.php</code> avec <strong>Ctrl+F5</strong> (pour vider le cache)</li>";
echo "<li>Fermer et rouvrir la modal de l'agent si elle était déjà ouverte</li>";
echo "<li>Les nouveaux IDs ci-dessus sont maintenant les bons</li>";
echo "</ol>";
echo "</div>";

echo "<br><a href='admin_planning.php' style='display: inline-block; padding: 10px 20px; background: #124c97; color: white; text-decoration: none; border-radius: 5px;'>← Retour au Planning</a>";
echo " ";
echo "<a href='admin.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>← Retour Admin</a>";
?>
