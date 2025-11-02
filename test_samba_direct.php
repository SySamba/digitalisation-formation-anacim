<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Test direct pour Samba Sy - Formations SUR-FTS</h2>";

// Trouver Samba Sy
$stmt_agent = $pdo->prepare("SELECT id, matricule, prenom, nom FROM agents WHERE matricule = '2017081JD'");
$stmt_agent->execute();
$agent = $stmt_agent->fetch();

if (!$agent) {
    echo "<p style='color: red;'>❌ Agent Samba Sy non trouvé</p>";
    exit;
}

$agent_id = $agent['id'];
echo "<p><strong>Agent:</strong> {$agent['prenom']} {$agent['nom']} (ID: $agent_id)</p>";

// Test 1: Formations SUR-FTS dans formations_effectuees
echo "<h3>1. 📋 Table formations_effectuees (SUR-FTS)</h3>";
$stmt_fe = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ? AND f.code LIKE 'SUR-FTS-%'
    ORDER BY f.code, fe.date_fin DESC
");
$stmt_fe->execute([$agent_id]);
$formations_fe = $stmt_fe->fetchAll();

echo "<p><strong>Formations SUR-FTS dans formations_effectuees: " . count($formations_fe) . "</strong></p>";
if (!empty($formations_fe)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Formation ID</th><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Périodicité</th><th>Jours Restants</th><th>À Renouveler?</th></tr>";
    foreach ($formations_fe as $formation) {
        $a_renouveler = $formation['jours_restants'] <= 1095 ? 'OUI' : 'NON';
        $couleur = $formation['jours_restants'] <= 1095 ? 'orange' : 'black';
        echo "<tr style='color: $couleur;'>";
        echo "<td>" . $formation['formation_id'] . "</td>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 30) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . $formation['periodicite_mois'] . " mois</td>";
        echo "<td><strong>" . $formation['jours_restants'] . "</strong></td>";
        echo "<td><strong>$a_renouveler</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 2: Formations SUR-FTS dans formations_agents
echo "<h3>2. 📋 Table formations_agents (SUR-FTS)</h3>";
$stmt_fa = $pdo->prepare("
    SELECT fa.*, f.intitule, f.code, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ? AND f.code LIKE 'SUR-FTS-%'
    ORDER BY f.code, fa.date_fin DESC
");
$stmt_fa->execute([$agent_id]);
$formations_fa = $stmt_fa->fetchAll();

echo "<p><strong>Formations SUR-FTS dans formations_agents: " . count($formations_fa) . "</strong></p>";
if (!empty($formations_fa)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Formation ID</th><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Périodicité</th><th>Jours Restants</th><th>À Renouveler?</th></tr>";
    foreach ($formations_fa as $formation) {
        $a_renouveler = $formation['jours_restants'] <= 1095 ? 'OUI' : 'NON';
        $couleur = $formation['jours_restants'] <= 1095 ? 'orange' : 'black';
        echo "<tr style='color: $couleur;'>";
        echo "<td>" . $formation['formation_id'] . "</td>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 30) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . $formation['periodicite_mois'] . " mois</td>";
        echo "<td><strong>" . $formation['jours_restants'] . "</strong></td>";
        echo "<td><strong>$a_renouveler</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Simulation de notre logique PHP
echo "<h3>3. 🧮 Simulation de notre logique PHP</h3>";

// Combiner les deux tables
$all_formations = array_merge($formations_fe, $formations_fa);

// Grouper par formation_id et garder la plus récente
$formations_par_id = [];
foreach ($all_formations as $formation) {
    $formation_id = $formation['formation_id'];
    if (!isset($formations_par_id[$formation_id]) || 
        strtotime($formation['date_fin']) > strtotime($formations_par_id[$formation_id]['date_fin'])) {
        $formations_par_id[$formation_id] = $formation;
    }
}

echo "<p><strong>Formations uniques après déduplication: " . count($formations_par_id) . "</strong></p>";

// Filtrer celles à renouveler
$formations_a_renouveler = [];
foreach ($formations_par_id as $formation) {
    if ($formation['jours_restants'] <= 1095) {
        $formations_a_renouveler[] = $formation;
    }
}

echo "<p><strong style='color: red; font-size: 1.5em;'>RÉSULTAT FINAL: " . count($formations_a_renouveler) . " formations à renouveler</strong></p>";

if (!empty($formations_a_renouveler)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Formation ID</th><th>Code</th><th>Intitulé</th><th>Date Fin (plus récente)</th><th>Jours Restants</th></tr>";
    foreach ($formations_a_renouveler as $formation) {
        echo "<tr style='color: orange;'>";
        echo "<td>" . $formation['formation_id'] . "</td>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 40) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td><strong>" . $formation['jours_restants'] . " jours</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 4: Vérifier les doublons
echo "<h3>4. 🔍 Analyse des doublons</h3>";
$doublons_detectes = [];
foreach ($formations_par_id as $formation_id => $formation) {
    $count_fe = 0;
    $count_fa = 0;
    
    foreach ($formations_fe as $fe) {
        if ($fe['formation_id'] == $formation_id) $count_fe++;
    }
    
    foreach ($formations_fa as $fa) {
        if ($fa['formation_id'] == $formation_id) $count_fa++;
    }
    
    if ($count_fe + $count_fa > 1) {
        $doublons_detectes[] = [
            'formation_id' => $formation_id,
            'code' => $formation['code'],
            'count_fe' => $count_fe,
            'count_fa' => $count_fa,
            'total' => $count_fe + $count_fa
        ];
    }
}

if (!empty($doublons_detectes)) {
    echo "<p><strong>Doublons détectés: " . count($doublons_detectes) . "</strong></p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Formation ID</th><th>Code</th><th>Dans FE</th><th>Dans FA</th><th>Total</th></tr>";
    foreach ($doublons_detectes as $doublon) {
        echo "<tr>";
        echo "<td>" . $doublon['formation_id'] . "</td>";
        echo "<td><strong>" . $doublon['code'] . "</strong></td>";
        echo "<td>" . $doublon['count_fe'] . "</td>";
        echo "<td>" . $doublon['count_fa'] . "</td>";
        echo "<td><strong>" . $doublon['total'] . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>✅ Aucun doublon détecté</p>";
}

echo "<hr>";
echo "<h3>📊 Résumé final</h3>";
echo "<p><strong>Total formations SUR-FTS:</strong> " . count($all_formations) . "</p>";
echo "<p><strong>Formations uniques:</strong> " . count($formations_par_id) . "</p>";
echo "<p><strong>Formations à renouveler:</strong> " . count($formations_a_renouveler) . "</p>";
echo "<p><strong>Doublons:</strong> " . count($doublons_detectes) . "</p>";

if (count($formations_a_renouveler) == 2) {
    echo "<p style='color: green; font-size: 1.2em;'><strong>✅ CORRECT: 2 formations à renouveler comme attendu!</strong></p>";
} else {
    echo "<p style='color: red; font-size: 1.2em;'><strong>❌ PROBLÈME: " . count($formations_a_renouveler) . " formations au lieu de 2!</strong></p>";
}
?>
