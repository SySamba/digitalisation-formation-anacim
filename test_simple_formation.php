<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Test simple pour voir les données
$stmt = $db->prepare("SELECT id, code, intitule FROM formations LIMIT 5");
$stmt->execute();
$formations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Formation Simple</title>
</head>
<body>
    <h2>Test Formation Dropdown</h2>
    <select name="formation_test">
        <option value="">Sélectionner...</option>
        <?php foreach ($formations as $formation): ?>
            <option value="<?php echo $formation['id']; ?>">
                <?php echo $formation['code'] . ' - ' . $formation['intitule']; ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <h3>Debug Info:</h3>
    <pre>
    <?php
    foreach ($formations as $formation) {
        echo "ID: " . $formation['id'] . "\n";
        echo "Code: " . $formation['code'] . "\n"; 
        echo "Intitule: " . $formation['intitule'] . "\n";
        echo "Combined: " . $formation['code'] . ' - ' . $formation['intitule'] . "\n";
        echo "---\n";
    }
    ?>
    </pre>
</body>
</html>
