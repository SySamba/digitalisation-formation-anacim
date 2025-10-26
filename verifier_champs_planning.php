<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Vérification des Champs de la Table planning_formations</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; }
</style>";

try {
    // Récupérer la structure de la table
    $stmt = $db->query("DESCRIBE planning_formations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_fields = ['ville', 'pays', 'duree', 'perdiem', 'priorite'];
    $existing_fields = array_column($columns, 'Field');
    
    $missing_fields = array_diff($required_fields, $existing_fields);
    
    if (empty($missing_fields)) {
        echo "<div class='success'>";
        echo "<strong>✅ Tous les champs sont présents!</strong><br>";
        echo "Les champs ville, pays, durée, perdiem et priorité existent dans la table.";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<strong>❌ Champs manquants:</strong><br>";
        echo implode(', ', $missing_fields);
        echo "<br><br><strong>Action requise:</strong> Exécutez le script SQL add_planning_fields.sql";
        echo "</div>";
    }
    
    echo "<h3>Structure complète de la table planning_formations</h3>";
    echo "<table>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Défaut</th></tr>";
    
    foreach ($columns as $col) {
        $is_new = in_array($col['Field'], $required_fields);
        $style = $is_new ? 'style="background-color: #d4edda; font-weight: bold;"' : '';
        
        echo "<tr {$style}>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    if (!empty($missing_fields)) {
        echo "<hr>";
        echo "<div class='warning'>";
        echo "<h3>Comment ajouter les champs manquants</h3>";
        echo "<p><strong>Option 1:</strong> Avec phpMyAdmin</p>";
        echo "<ol>";
        echo "<li>Ouvrez phpMyAdmin</li>";
        echo "<li>Sélectionnez votre base de données</li>";
        echo "<li>Cliquez sur l'onglet SQL</li>";
        echo "<li>Copiez le contenu du fichier <code>add_planning_fields.sql</code></li>";
        echo "<li>Exécutez la requête</li>";
        echo "</ol>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>Erreur:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<br><a href='admin_planning.php' style='display: inline-block; padding: 10px 20px; background: #124c97; color: white; text-decoration: none; border-radius: 5px;'>← Retour au Planning</a>";
?>
