<?php
session_start();
require_once 'config/database.php';

$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 1;

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔧 Test des Formations Techniques (SUR-FTS) pour l'agent ID: $agent_id</h2>";

// Test 1: Vérifier toutes les formations SUR-FTS disponibles
echo "<h3>1. 📋 Toutes les formations SUR-FTS disponibles</h3>";
$stmt_all_fts = $pdo->prepare("SELECT id, code, intitule, periodicite_mois FROM formations WHERE code LIKE 'SUR-FTS-%' ORDER BY code");
$stmt_all_fts->execute();
$all_fts = $stmt_all_fts->fetchAll();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th><th>Périodicité (mois)</th></tr>";
foreach ($all_fts as $formation) {
    echo "<tr>";
    echo "<td>" . $formation['id'] . "</td>";
    echo "<td><strong>" . $formation['code'] . "</strong></td>";
    echo "<td>" . $formation['intitule'] . "</td>";
    echo "<td>" . $formation['periodicite_mois'] . " mois (" . round($formation['periodicite_mois']/12, 1) . " ans)</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><strong>Total formations SUR-FTS: " . count($all_fts) . "</strong></p>";

// Test 2: Formations SUR-FTS effectuées par cet agent
echo "<h3>2. ✅ Formations SUR-FTS effectuées par l'agent</h3>";
$stmt_fts_effectuees = $pdo->prepare("
    (SELECT fe.*, f.intitule, f.code, f.periodicite_mois,
            DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
            DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
            'formations_effectuees' as source_table
     FROM formations_effectuees fe
     JOIN formations f ON fe.formation_id = f.id
     WHERE fe.agent_id = ? AND f.code LIKE 'SUR-FTS-%')
    
    UNION ALL
    
    (SELECT fa.*, f.intitule, f.code, f.periodicite_mois,
            DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
            DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
            'formations_agents' as source_table
     FROM formations_agents fa
     JOIN formations f ON fa.formation_id = f.id
     WHERE fa.agent_id = ? AND f.code LIKE 'SUR-FTS-%')
    
    ORDER BY date_fin DESC
");
$stmt_fts_effectuees->execute([$agent_id, $agent_id]);
$fts_effectuees = $stmt_fts_effectuees->fetchAll();

if (empty($fts_effectuees)) {
    echo "<p style='color: orange;'>⚠️ Aucune formation SUR-FTS effectuée par cet agent</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Prochaine Échéance</th><th>Jours Restants</th><th>Source</th></tr>";
    foreach ($fts_effectuees as $formation) {
        $couleur = $formation['jours_restants'] <= 0 ? 'red' : ($formation['jours_restants'] <= 365 ? 'orange' : 'green');
        echo "<tr style='color: $couleur;'>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . $formation['intitule'] . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['prochaine_echeance'])) . "</td>";
        echo "<td><strong>" . $formation['jours_restants'] . " jours</strong></td>";
        echo "<td>" . $formation['source_table'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Formations SUR-FTS à renouveler (nouvelle requête)
echo "<h3>3. 🔄 Formations SUR-FTS à renouveler (échéance dans les 2 ans)</h3>";
$stmt_fts_renouveler = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_effectuees' as source_table
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ? 
    AND fe.statut IN ('termine', 'valide')
    AND f.code LIKE 'SUR-FTS-%'
    AND f.periodicite_mois > 0
    AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 24 MONTH)
    
    UNION ALL
    
    SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_agents' as source_table
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ? 
    AND fa.statut IN ('termine', 'valide')
    AND f.code LIKE 'SUR-FTS-%'
    AND f.periodicite_mois > 0
    AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 24 MONTH)
    
    ORDER BY jours_restants ASC
");
$stmt_fts_renouveler->execute([$agent_id, $agent_id]);
$fts_a_renouveler = $stmt_fts_renouveler->fetchAll();

if (empty($fts_a_renouveler)) {
    echo "<p style='color: green;'>✅ Aucune formation SUR-FTS à renouveler dans les 2 prochaines années</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Dernière Formation</th><th>Prochaine Échéance</th><th>Jours Restants</th><th>Priorité</th></tr>";
    foreach ($fts_a_renouveler as $formation) {
        $priorite = $formation['jours_restants'] <= 0 ? 'URGENT' : ($formation['jours_restants'] <= 365 ? 'HAUTE' : 'MOYENNE');
        $couleur = $formation['jours_restants'] <= 0 ? 'red' : ($formation['jours_restants'] <= 365 ? 'orange' : 'blue');
        echo "<tr style='color: $couleur;'>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . $formation['intitule'] . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['prochaine_echeance_calculee'])) . "</td>";
        echo "<td><strong>" . $formation['jours_restants'] . " jours</strong></td>";
        echo "<td><strong>$priorite</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 4: Formations non effectuées (debug)
echo "<h3>4. ❌ Debug - Formations non effectuées</h3>";
$stmt_debug_non_effectuees = $pdo->prepare("
    SELECT DISTINCT f.id, f.intitule, f.code, f.categorie, f.periodicite_mois,
           (SELECT COUNT(*) FROM formations_effectuees fe WHERE fe.agent_id = ? AND fe.formation_id = f.id AND fe.statut IN ('termine', 'valide')) as dans_fe,
           (SELECT COUNT(*) FROM formations_agents fa WHERE fa.agent_id = ? AND fa.formation_id = f.id AND fa.statut IN ('termine', 'valide')) as dans_fa,
           (SELECT COUNT(*) FROM planning_formations pf WHERE pf.agent_id = ? AND pf.formation_id = f.id AND pf.statut IN ('planifie', 'confirme')) as planifiee
    FROM formations f
    ORDER BY f.code
    LIMIT 10
");
$stmt_debug_non_effectuees->execute([$agent_id, $agent_id, $agent_id]);
$debug_formations = $stmt_debug_non_effectuees->fetchAll();

echo "<p><strong>Échantillon des 10 premières formations (pour debug) :</strong></p>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Code</th><th>Intitulé</th><th>Dans FE</th><th>Dans FA</th><th>Planifiée</th><th>Statut</th></tr>";
foreach ($debug_formations as $formation) {
    $effectuee = ($formation['dans_fe'] > 0 || $formation['dans_fa'] > 0);
    $planifiee = $formation['planifiee'] > 0;
    
    if ($effectuee) {
        $statut = "✅ Effectuée";
        $couleur = "green";
    } elseif ($planifiee) {
        $statut = "📅 Planifiée";
        $couleur = "blue";
    } else {
        $statut = "❌ Non effectuée";
        $couleur = "red";
    }
    
    echo "<tr style='color: $couleur;'>";
    echo "<td><strong>" . $formation['code'] . "</strong></td>";
    echo "<td>" . substr($formation['intitule'], 0, 50) . "...</td>";
    echo "<td>" . $formation['dans_fe'] . "</td>";
    echo "<td>" . $formation['dans_fa'] . "</td>";
    echo "<td>" . $formation['planifiee'] . "</td>";
    echo "<td><strong>$statut</strong></td>";
    echo "</tr>";
}
echo "</table>";

// Statistiques finales
echo "<hr>";
echo "<h3>📊 Statistiques finales</h3>";

// Recalculer toutes les statistiques
$stmt_stats = $pdo->prepare("
    -- Formations effectuées
    SELECT 'effectuees' as type, COUNT(*) as count FROM (
        SELECT DISTINCT fe.formation_id FROM formations_effectuees fe WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide')
        UNION
        SELECT DISTINCT fa.formation_id FROM formations_agents fa WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide')
    ) as effectuees
    
    UNION ALL
    
    -- Formations planifiées
    SELECT 'planifiees' as type, COUNT(*) as count FROM planning_formations pf WHERE pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme')
    
    UNION ALL
    
    -- Total formations disponibles
    SELECT 'total' as type, COUNT(*) as count FROM formations
");
$stmt_stats->execute([$agent_id, $agent_id, $agent_id]);
$stats = $stmt_stats->fetchAll();

$stats_array = [];
foreach ($stats as $stat) {
    $stats_array[$stat['type']] = $stat['count'];
}

$formations_effectuees_count = $stats_array['effectuees'] ?? 0;
$formations_planifiees_count = $stats_array['planifiees'] ?? 0;
$total_formations = $stats_array['total'] ?? 0;
$formations_non_effectuees_count = $total_formations - $formations_effectuees_count - $formations_planifiees_count;
$formations_a_renouveler_count = count($fts_a_renouveler);

echo "<div style='display: flex; gap: 20px; flex-wrap: wrap;'>";

echo "<div style='border: 2px solid #28a745; padding: 15px; text-align: center; min-width: 150px;'>";
echo "<h4 style='color: #28a745; margin: 10px 0;'>$formations_effectuees_count</h4>";
echo "<small>Formations Effectuées</small>";
echo "</div>";

echo "<div style='border: 2px solid #dc3545; padding: 15px; text-align: center; min-width: 150px;'>";
echo "<h4 style='color: #dc3545; margin: 10px 0;'>$formations_non_effectuees_count</h4>";
echo "<small>Non Effectuées</small>";
echo "</div>";

echo "<div style='border: 2px solid #ffc107; padding: 15px; text-align: center; min-width: 150px;'>";
echo "<h4 style='color: #ffc107; margin: 10px 0;'>$formations_a_renouveler_count</h4>";
echo "<small>À Renouveler (SUR-FTS)</small>";
echo "</div>";

echo "<div style='border: 2px solid #17a2b8; padding: 15px; text-align: center; min-width: 150px;'>";
echo "<h4 style='color: #17a2b8; margin: 10px 0;'>$formations_planifiees_count</h4>";
echo "<small>Planifiées</small>";
echo "</div>";

echo "</div>";

echo "<hr>";
echo "<h3>🔗 Liens de test</h3>";
echo "<a href='admin.php' target='_blank'>Retour à admin.php</a><br>";
echo "<a href='ajax/get_agent_details.php?id=$agent_id' target='_blank'>Tester get_agent_details.php directement</a><br>";

// Test avec différents agents
echo "<hr>";
echo "<h3>👥 Tester avec d'autres agents</h3>";
$query_agents = "SELECT id, prenom, nom, matricule FROM agents LIMIT 5";
$stmt_agents = $pdo->prepare($query_agents);
$stmt_agents->execute();
$agents_list = $stmt_agents->fetchAll();

foreach ($agents_list as $agent) {
    echo "<a href='?agent_id=" . $agent['id'] . "'>" . $agent['prenom'] . " " . $agent['nom'] . " (ID: " . $agent['id'] . ")</a><br>";
}
?>
