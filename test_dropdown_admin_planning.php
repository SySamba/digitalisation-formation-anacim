<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Dropdown Admin Planning</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        select { width: 100%; padding: 10px; font-size: 14px; margin: 10px 0; }
        button { padding: 10px 20px; background: #124c97; color: white; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Test du Dropdown de Formation (Admin Planning)</h1>
    
    <?php
    // Simuler exactement ce que fait admin_planning.php
    $stmt = $db->prepare("SELECT id, code, intitule, categorie, periodicite_mois FROM formations ORDER BY id");
    $stmt->execute();
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>";
    echo "<strong>Formations récupérées par la requête SQL:</strong> " . count($formations) . "<br>";
    echo "Premier ID: " . ($formations[0]['id'] ?? 'N/A') . " - " . ($formations[0]['code'] ?? 'N/A') . "<br>";
    echo "Dernier ID: " . (end($formations)['id'] ?? 'N/A') . " - " . (end($formations)['code'] ?? 'N/A');
    echo "</div>";
    ?>
    
    <h3>Dropdown tel qu'il apparaît dans admin_planning.php</h3>
    <select id="formation_dropdown" name="formation_id">
        <option value="">Sélectionner une formation...</option>
        <?php 
        foreach ($formations as $formation) {
            $fid = $formation['id'] ?? $formation['formation_id'] ?? '';
            echo "<option value='$fid'>" . htmlspecialchars($formation['code'] ?? 'NO_CODE') . " - " . htmlspecialchars($formation['intitule'] ?? 'NO_TITLE') . "</option>\n";
        }
        ?>
    </select>
    
    <h3>Test de Pré-sélection</h3>
    <p>Entrez un ID de formation pour tester la pré-sélection :</p>
    <input type="number" id="test_id" value="1" style="padding: 10px; margin-right: 10px;">
    <button onclick="testPreselection()">Tester la Pré-sélection</button>
    
    <div id="result" style="margin-top: 20px;"></div>
    
    <h3>Liste des 10 premières formations dans le dropdown</h3>
    <table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">
        <tr>
            <th>ID (value)</th>
            <th>Code</th>
            <th>Intitulé</th>
        </tr>
        <?php 
        for ($i = 0; $i < min(10, count($formations)); $i++) {
            echo "<tr>";
            echo "<td><strong>" . $formations[$i]['id'] . "</strong></td>";
            echo "<td>" . htmlspecialchars($formations[$i]['code']) . "</td>";
            echo "<td>" . htmlspecialchars($formations[$i]['intitule']) . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
    
    <script>
        function testPreselection() {
            const testId = document.getElementById('test_id').value;
            const dropdown = document.getElementById('formation_dropdown');
            const result = document.getElementById('result');
            
            console.log('Test de pré-sélection pour ID:', testId);
            
            // Méthode 1: Recherche directe
            const formationIdStr = String(testId);
            const allOptions = Array.from(dropdown.options);
            
            console.log('Nombre total d\'options:', allOptions.length);
            console.log('Première option value:', allOptions[1]?.value); // Skip l'option vide
            
            const foundOption = allOptions.find(opt => opt.value === formationIdStr);
            
            if (foundOption) {
                dropdown.value = foundOption.value;
                foundOption.selected = true;
                
                result.innerHTML = `
                    <div class="success">
                        <strong>✅ SUCCÈS!</strong><br>
                        Formation trouvée et sélectionnée:<br>
                        <strong>ID: ${foundOption.value}</strong><br>
                        <strong>Texte: ${foundOption.text}</strong>
                    </div>
                `;
            } else {
                result.innerHTML = `
                    <div class="error">
                        <strong>❌ ÉCHEC!</strong><br>
                        Formation ID=${testId} non trouvée dans le dropdown.<br>
                        <strong>IDs disponibles:</strong> ${allOptions.map(o => o.value).filter(v => v !== '').slice(0, 20).join(', ')}
                    </div>
                `;
            }
        }
        
        // Test automatique avec ID=1 au chargement
        window.onload = function() {
            console.log('=== AUTO-TEST AU CHARGEMENT ===');
            testPreselection();
        };
    </script>
    
    <hr style="margin: 30px 0;">
    <a href="admin_planning.php" style="display: inline-block; padding: 10px 20px; background: #124c97; color: white; text-decoration: none; border-radius: 5px;">← Retour au Planning</a>
</body>
</html>
