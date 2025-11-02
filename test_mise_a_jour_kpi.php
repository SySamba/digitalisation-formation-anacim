<?php
session_start();
require_once 'config/database.php';

$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 1;

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔄 Test de la mise à jour des KPI pour l'agent ID: $agent_id</h2>";

// Trouver l'agent
$stmt_agent = $pdo->prepare("SELECT id, matricule, prenom, nom, email FROM agents WHERE id = ?");
$stmt_agent->execute([$agent_id]);
$agent = $stmt_agent->fetch();

if (!$agent) {
    echo "<p style='color: red;'>❌ Agent non trouvé avec l'ID $agent_id</p>";
    exit;
}

echo "<p><strong>Agent:</strong> {$agent['prenom']} {$agent['nom']} (Matricule: {$agent['matricule']})</p>";

// Test 1: Formations effectuées avec doublons potentiels
echo "<h3>1. 📋 Toutes les formations effectuées (avec doublons potentiels)</h3>";
$stmt_all_effectuees = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_effectuees' as source_table
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ?
    
    UNION ALL
    
    SELECT fa.*, f.intitule, f.code, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_agents' as source_table
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ?
    
    ORDER BY code, date_fin DESC
");
$stmt_all_effectuees->execute([$agent_id, $agent_id]);
$all_effectuees = $stmt_all_effectuees->fetchAll();

if (empty($all_effectuees)) {
    echo "<p style='color: orange;'>⚠️ Aucune formation effectuée trouvée</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Prochaine Échéance</th><th>Jours Restants</th><th>Source</th><th>Doublon?</th></tr>";
    
    $formations_par_code = [];
    foreach ($all_effectuees as $formation) {
        $code = $formation['code'];
        if (!isset($formations_par_code[$code])) {
            $formations_par_code[$code] = [];
        }
        $formations_par_code[$code][] = $formation;
    }
    
    foreach ($all_effectuees as $formation) {
        $code = $formation['code'];
        $est_doublon = count($formations_par_code[$code]) > 1;
        $couleur = $est_doublon ? 'orange' : 'black';
        
        echo "<tr style='color: $couleur;'>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 40) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['prochaine_echeance'])) . "</td>";
        echo "<td><strong>" . $formation['jours_restants'] . " jours</strong></td>";
        echo "<td>" . $formation['source_table'] . "</td>";
        echo "<td>" . ($est_doublon ? '⚠️ OUI' : '✅ NON') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Compter les doublons
    $doublons = 0;
    foreach ($formations_par_code as $code => $formations) {
        if (count($formations) > 1) {
            $doublons++;
        }
    }
    echo "<p><strong>Formations avec doublons: $doublons</strong></p>";
}

// Test 2: Formations à renouveler (ancienne logique - avec doublons)
echo "<h3>2. 🔄 Formations à renouveler (ANCIENNE logique - avec doublons)</h3>";
$stmt_ancienne = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_effectuees' as source_table
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ? 
    AND fe.statut IN ('termine', 'valide')
    AND f.periodicite_mois > 0
    AND (
        (f.code LIKE 'SUR-FTS-%' AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH))
        OR
        (f.code NOT LIKE 'SUR-FTS-%' AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
    )
    
    UNION ALL
    
    SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_agents' as source_table
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ? 
    AND fa.statut IN ('termine', 'valide')
    AND f.periodicite_mois > 0
    AND (
        (f.code LIKE 'SUR-FTS-%' AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH))
        OR
        (f.code NOT LIKE 'SUR-FTS-%' AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
    )
    
    ORDER BY jours_restants ASC
");
$stmt_ancienne->execute([$agent_id, $agent_id]);
$formations_ancienne = $stmt_ancienne->fetchAll();

echo "<p><strong>Nombre avec ancienne logique: " . count($formations_ancienne) . "</strong></p>";

// Test 3: Formations à renouveler (nouvelle logique - sans doublons)
echo "<h3>3. 🔄 Formations à renouveler (NOUVELLE logique - sans doublons)</h3>";
$stmt_nouvelle = $pdo->prepare("
    SELECT * FROM (
        SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
               DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
               DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
               'formations_effectuees' as source_table,
               ROW_NUMBER() OVER (PARTITION BY fe.formation_id ORDER BY fe.date_fin DESC) as rn
        FROM formations_effectuees fe
        JOIN formations f ON fe.formation_id = f.id
        WHERE fe.agent_id = ? 
        AND fe.statut IN ('termine', 'valide')
        AND f.periodicite_mois > 0
        
        UNION ALL
        
        SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
               DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
               DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
               'formations_agents' as source_table,
               ROW_NUMBER() OVER (PARTITION BY fa.formation_id ORDER BY fa.date_fin DESC) as rn
        FROM formations_agents fa
        JOIN formations f ON fa.formation_id = f.id
        WHERE fa.agent_id = ? 
        AND fa.statut IN ('termine', 'valide')
        AND f.periodicite_mois > 0
    ) as all_formations
    WHERE rn = 1  -- Prendre seulement la formation la plus récente pour chaque type
    AND (
        (code LIKE 'SUR-FTS-%' AND prochaine_echeance_calculee <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH))
        OR
        (code NOT LIKE 'SUR-FTS-%' AND prochaine_echeance_calculee <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
    )
    ORDER BY jours_restants ASC
");
$stmt_nouvelle->execute([$agent_id, $agent_id]);
$formations_nouvelle = $stmt_nouvelle->fetchAll();

echo "<p><strong>Nombre avec nouvelle logique: " . count($formations_nouvelle) . "</strong></p>";

if (!empty($formations_nouvelle)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Prochaine Échéance</th><th>Jours Restants</th><th>Priorité</th><th>Source</th></tr>";
    foreach ($formations_nouvelle as $formation) {
        $priorite = $formation['jours_restants'] <= 365 ? 'URGENT' : ($formation['jours_restants'] <= 730 ? 'HAUTE' : 'MOYENNE');
        $couleur = $formation['jours_restants'] <= 365 ? 'red' : ($formation['jours_restants'] <= 730 ? 'orange' : 'blue');
        echo "<tr style='color: $couleur;'>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 40) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['prochaine_echeance_calculee'])) . "</td>";
        echo "<td><strong>" . $formation['jours_restants'] . " jours</strong></td>";
        echo "<td><strong>$priorite</strong></td>";
        echo "<td>" . $formation['source_table'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Comparaison
echo "<h3>4. 📊 Comparaison des résultats</h3>";
echo "<div style='display: flex; gap: 20px;'>";

echo "<div style='border: 2px solid #dc3545; padding: 15px; text-align: center; min-width: 200px;'>";
echo "<h4 style='color: #dc3545;'>ANCIENNE LOGIQUE</h4>";
echo "<h2 style='color: #dc3545;'>" . count($formations_ancienne) . "</h2>";
echo "<small>Formations à renouveler<br>(avec doublons potentiels)</small>";
echo "</div>";

echo "<div style='border: 2px solid #28a745; padding: 15px; text-align: center; min-width: 200px;'>";
echo "<h4 style='color: #28a745;'>NOUVELLE LOGIQUE</h4>";
echo "<h2 style='color: #28a745;'>" . count($formations_nouvelle) . "</h2>";
echo "<small>Formations à renouveler<br>(sans doublons)</small>";
echo "</div>";

echo "</div>";

$difference = count($formations_ancienne) - count($formations_nouvelle);
if ($difference > 0) {
    echo "<p style='color: green;'><strong>✅ Amélioration: $difference doublons supprimés</strong></p>";
} elseif ($difference < 0) {
    echo "<p style='color: orange;'><strong>⚠️ Attention: " . abs($difference) . " formations supplémentaires détectées</strong></p>";
} else {
    echo "<p style='color: blue;'><strong>ℹ️ Même nombre de formations détectées</strong></p>";
}

echo "<hr>";
echo "<h3>🔗 Liens de test</h3>";
echo "<a href='admin.php' target='_blank'>Retour à admin.php</a><br>";
echo "<a href='ajax/get_agent_details.php?id=$agent_id' target='_blank'>Voir détail agent</a><br>";
echo "<a href='agent_profile.php' target='_blank'>Profil agent (pour mise à jour)</a><br>";

// Test avec différents agents
echo "<hr>";
echo "<h3>👥 Tester avec d'autres agents</h3>";
$query_agents = "SELECT id, prenom, nom, matricule FROM agents LIMIT 5";
$stmt_agents = $pdo->prepare($query_agents);
$stmt_agents->execute();
$agents_list = $stmt_agents->fetchAll();

foreach ($agents_list as $agent_test) {
    echo "<a href='?agent_id=" . $agent_test['id'] . "'>" . $agent_test['prenom'] . " " . $agent_test['nom'] . " (ID: " . $agent_test['id'] . ")</a><br>";
}
?>
