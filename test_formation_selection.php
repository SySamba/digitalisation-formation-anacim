<?php
// Test spécifique pour la sélection de formation
session_start();
$_SESSION['admin_logged_in'] = true;

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Simuler les paramètres URL
$preselect_agent = 2;
$preselect_formation = 4;

// Récupérer les formations
$stmt = $db->prepare("SELECT id, code, intitule FROM formations ORDER BY code");
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Test de Sélection de Formation</h2>";
echo "<p><strong>Formation à pré-sélectionner :</strong> ID = $preselect_formation (SUR-INI-02)</p>";
echo "<hr>";

echo "<h3>Méthode 1 : Dropdown HTML standard</h3>";
echo "<select class='form-select' style='width: 600px; padding: 10px; font-size: 14px;'>";
echo "<option value=''>Sélectionner une formation...</option>";
foreach ($formations as $formation) {
    $formation_id_raw = $formation['id'];
    $code = htmlspecialchars($formation['code']);
    $intitule = htmlspecialchars($formation['intitule']);
    
    $is_selected = ($preselect_formation == $formation_id_raw);
    $selected_attr = $is_selected ? 'selected' : '';
    
    echo "<option value='$formation_id_raw' $selected_attr>";
    echo "$code - $intitule";
    if ($is_selected) echo " ✅ SÉLECTIONNÉ";
    echo "</option>";
}
echo "</select>";

echo "<hr>";
echo "<h3>Debug détaillé :</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background-color: #124c97; color: white;'>";
echo "<th>ID</th><th>Code</th><th>Intitulé</th><th>Preselect</th><th>Match?</th><th>Selected Attr</th>";
echo "</tr>";

foreach ($formations as $formation) {
    $formation_id_raw = $formation['id'];
    $code = $formation['code'];
    $intitule = $formation['intitule'];
    
    $is_selected = ($preselect_formation == $formation_id_raw);
    $selected_attr = $is_selected ? 'selected' : '';
    $match = $is_selected ? '✅ OUI' : '❌ NON';
    
    $bg_color = $is_selected ? 'background-color: #90EE90;' : '';
    
    echo "<tr style='$bg_color'>";
    echo "<td>$formation_id_raw</td>";
    echo "<td>$code</td>";
    echo "<td>" . substr($intitule, 0, 50) . "...</td>";
    echo "<td>$preselect_formation</td>";
    echo "<td><strong>$match</strong></td>";
    echo "<td><code>$selected_attr</code></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>Test JavaScript :</h3>";
echo "<button onclick='testSelection()' class='btn btn-primary'>Vérifier la sélection</button>";
echo "<div id='result' style='margin-top: 20px; padding: 20px; border: 2px solid #ccc;'></div>";

?>

<script>
function testSelection() {
    const select = document.querySelector('select');
    const selectedValue = select.value;
    const selectedText = select.options[select.selectedIndex].text;
    
    const result = document.getElementById('result');
    result.innerHTML = `
        <h4>Résultat :</h4>
        <p><strong>Valeur sélectionnée :</strong> ${selectedValue}</p>
        <p><strong>Texte sélectionné :</strong> ${selectedText}</p>
        <p><strong>Attendu :</strong> 4 (SUR-INI-02)</p>
        <p><strong>Status :</strong> ${selectedValue == '4' ? '✅ CORRECT' : '❌ INCORRECT'}</p>
    `;
}

// Auto-test au chargement
window.onload = function() {
    setTimeout(testSelection, 500);
};
</script>

<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
h2 { color: #124c97; }
.btn-primary { padding: 10px 20px; background-color: #124c97; color: white; border: none; border-radius: 5px; cursor: pointer; }
.btn-primary:hover { background-color: #0a3570; }
</style>
