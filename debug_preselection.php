<?php
// Page de debug pour tester la pré-sélection
session_start();
$_SESSION['admin_logged_in'] = true; // Simuler connexion admin

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer les paramètres URL
$agent_id = $_GET['agent_id'] ?? '';
$formation_id = $_GET['formation_id'] ?? '';

echo "<h2>Debug de la Pré-sélection</h2>";
echo "<hr>";

// Afficher les paramètres reçus
echo "<h3>1. Paramètres URL reçus :</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Paramètre</th><th>Valeur</th></tr>";
echo "<tr><td>agent_id</td><td><strong>" . htmlspecialchars($agent_id) . "</strong></td></tr>";
echo "<tr><td>formation_id</td><td><strong>" . htmlspecialchars($formation_id) . "</strong></td></tr>";
echo "<tr><td>URL complète</td><td>" . htmlspecialchars($_SERVER['REQUEST_URI']) . "</td></tr>";
echo "</table>";

// Récupérer les agents
$stmt = $db->prepare("SELECT * FROM agents ORDER BY nom, prenom");
$stmt->execute();
$agents = $stmt->fetchAll();

// Récupérer les formations
$stmt = $db->prepare("SELECT id, code, intitule FROM formations ORDER BY code");
$stmt->execute();
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<hr>";
echo "<h3>2. Test de sélection Agent :</h3>";
echo "<select class='form-select' style='width: 500px; padding: 10px;'>";
echo "<option value=''>Sélectionner un agent...</option>";
foreach ($agents as $agent) {
    $selected = ($agent_id == $agent['id']) ? 'selected' : '';
    $mark = ($agent_id == $agent['id']) ? ' ✅ SÉLECTIONNÉ' : '';
    echo "<option value='{$agent['id']}' $selected>";
    echo htmlspecialchars($agent['matricule'] . ' - ' . $agent['prenom'] . ' ' . $agent['nom']) . $mark;
    echo "</option>";
}
echo "</select>";

echo "<hr>";
echo "<h3>3. Test de sélection Formation :</h3>";
echo "<select class='form-select' style='width: 500px; padding: 10px;'>";
echo "<option value=''>Sélectionner une formation...</option>";
foreach ($formations as $formation) {
    $is_selected = (!empty($formation_id) && $formation_id == $formation['id']);
    $selected = $is_selected ? 'selected' : '';
    $mark = $is_selected ? ' ✅ SÉLECTIONNÉ' : '';
    
    echo "<option value='{$formation['id']}' $selected>";
    echo htmlspecialchars($formation['code'] . ' - ' . $formation['intitule']) . $mark;
    echo "</option>";
}
echo "</select>";

echo "<hr>";
echo "<h3>4. Debug détaillé des formations :</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID Formation</th><th>Code</th><th>ID URL</th><th>Match?</th></tr>";
foreach ($formations as $formation) {
    $match = (!empty($formation_id) && $formation_id == $formation['id']) ? '✅ OUI' : '❌ NON';
    $style = (!empty($formation_id) && $formation_id == $formation['id']) ? 'background-color: #90EE90;' : '';
    echo "<tr style='$style'>";
    echo "<td>{$formation['id']}</td>";
    echo "<td>{$formation['code']}</td>";
    echo "<td>$formation_id</td>";
    echo "<td><strong>$match</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>5. Lien de test :</h3>";
if (!empty($agents) && !empty($formations)) {
    $test_agent = $agents[0];
    $test_formation = $formations[0];
    $test_url = "debug_preselection.php?agent_id={$test_agent['id']}&formation_id={$test_formation['id']}";
    echo "<p>Cliquez sur ce lien pour tester avec le premier agent et la première formation :</p>";
    echo "<a href='$test_url' style='display: inline-block; padding: 10px 20px; background-color: #124c97; color: white; text-decoration: none; border-radius: 5px;'>";
    echo "Tester la pré-sélection";
    echo "</a>";
}

echo "<hr>";
echo "<h3>6. Aller vers admin_planning.php :</h3>";
if (!empty($agent_id) && !empty($formation_id)) {
    $planning_url = "admin_planning.php?section=planifier&agent_id=$agent_id&formation_id=$formation_id";
    echo "<p>Les paramètres sont définis. Cliquez ci-dessous pour aller vers la page de planification :</p>";
    echo "<a href='$planning_url' style='display: inline-block; padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px;'>";
    echo "Aller vers Planning (Agent: $agent_id, Formation: $formation_id)";
    echo "</a>";
} else {
    echo "<p style='color: red;'>Aucun paramètre défini. Utilisez le lien de test ci-dessus.</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
}
h2 { color: #124c97; }
h3 { color: #333; margin-top: 20px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th { background-color: #124c97; color: white; }
select { margin: 10px 0; }
</style>
