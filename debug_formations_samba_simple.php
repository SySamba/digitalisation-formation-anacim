<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Debug simple pour Samba Sy - Formations à renouveler</h2>";

// Trouver l'agent Samba Sy
$stmt_agent = $pdo->prepare("SELECT id, matricule, prenom, nom FROM agents WHERE matricule = '2017081JD'");
$stmt_agent->execute();
$agent = $stmt_agent->fetch();

if (!$agent) {
    echo "<p style='color: red;'>❌ Agent Samba Sy non trouvé</p>";
    exit;
}

$agent_id = $agent['id'];
echo "<p><strong>Agent:</strong> {$agent['prenom']} {$agent['nom']} (ID: $agent_id)</p>";

// Test 1: Toutes les formations SUR-FTS effectuées (qui peuvent être à renouveler)
echo "<h3>1. 📋 Formations SUR-FTS effectuées par Samba Sy</h3>";
$stmt_fts = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_effectuees' as source_table
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ? AND f.code LIKE 'SUR-FTS-%'
    
    UNION ALL
    
    SELECT fa.*, f.intitule, f.code, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_agents' as source_table
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ? AND f.code LIKE 'SUR-FTS-%'
    
    ORDER BY code, date_fin DESC
");
$stmt_fts->execute([$agent_id, $agent_id]);
$formations_fts = $stmt_fts->fetchAll();

echo "<p><strong>Total formations SUR-FTS: " . count($formations_fts) . "</strong></p>";

if (!empty($formations_fts)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Périodicité</th><th>Prochaine Échéance</th><th>Jours Restants</th><th>À Renouveler?</th><th>Source</th></tr>";
    
    foreach ($formations_fts as $formation) {
        // Formations techniques : échéance dans les 3 ans (1095 jours)
        $a_renouveler = $formation['jours_restants'] <= 1095;
        
        $couleur = $a_renouveler ? 'orange' : 'black';
        echo "<tr style='color: $couleur;'>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 40) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . $formation['periodicite_mois'] . " mois</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['prochaine_echeance'])) . "</td>";
        echo "<td><strong>" . $formation['jours_restants'] . " jours</strong></td>";
        echo "<td>" . ($a_renouveler ? '🔄 OUI' : '✅ NON') . "</td>";
        echo "<td>" . $formation['source_table'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 2: Formations à renouveler selon notre logique actuelle
echo "<h3>2. 🔄 Test de notre logique actuelle (MAX date_fin)</h3>";

// Version simplifiée pour debug
$stmt_debug = $pdo->prepare("
    SELECT t1.formation_id, t1.code, t1.intitule, t1.date_fin, t1.periodicite_mois,
           DATE_ADD(t1.date_fin, INTERVAL t1.periodicite_mois MONTH) as prochaine_echeance,
           DATEDIFF(DATE_ADD(t1.date_fin, INTERVAL t1.periodicite_mois MONTH), CURDATE()) as jours_restants,
           t1.source_table
    FROM (
        SELECT fe.formation_id, fe.date_fin, f.code, f.intitule, f.periodicite_mois, 'formations_effectuees' as source_table
        FROM formations_effectuees fe
        JOIN formations f ON fe.formation_id = f.id
        WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide') AND f.periodicite_mois > 0 AND f.code LIKE 'SUR-FTS-%'
        
        UNION ALL
        
        SELECT fa.formation_id, fa.date_fin, f.code, f.intitule, f.periodicite_mois, 'formations_agents' as source_table
        FROM formations_agents fa
        JOIN formations f ON fa.formation_id = f.id
        WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide') AND f.periodicite_mois > 0 AND f.code LIKE 'SUR-FTS-%'
    ) t1
    WHERE t1.date_fin = (
        SELECT MAX(t2.date_fin)
        FROM (
            SELECT fe2.formation_id, fe2.date_fin
            FROM formations_effectuees fe2
            WHERE fe2.agent_id = ? AND fe2.statut IN ('termine', 'valide')
            
            UNION ALL
            
            SELECT fa2.formation_id, fa2.date_fin
            FROM formations_agents fa2
            WHERE fa2.agent_id = ? AND fa2.statut IN ('termine', 'valide')
        ) t2
        WHERE t2.formation_id = t1.formation_id
    )
    AND DATE_ADD(t1.date_fin, INTERVAL t1.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH)
    ORDER BY jours_restants ASC
");

$stmt_debug->execute([$agent_id, $agent_id, $agent_id, $agent_id]);
$formations_debug = $stmt_debug->fetchAll();

echo "<p><strong>Formations à renouveler trouvées: " . count($formations_debug) . "</strong></p>";

if (!empty($formations_debug)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Formation ID</th><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Jours Restants</th><th>Source</th></tr>";
    foreach ($formations_debug as $formation) {
        echo "<tr>";
        echo "<td>" . $formation['formation_id'] . "</td>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 40) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . $formation['jours_restants'] . "</td>";
        echo "<td>" . $formation['source_table'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Vérifier s'il y a des doublons par formation_id
echo "<h3>3. 🔍 Analyse des doublons par formation_id</h3>";

$formations_par_id = [];
foreach ($formations_fts as $formation) {
    $formation_id = $formation['formation_id'];
    if (!isset($formations_par_id[$formation_id])) {
        $formations_par_id[$formation_id] = [];
    }
    $formations_par_id[$formation_id][] = $formation;
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Formation ID</th><th>Code</th><th>Nombre d'occurrences</th><th>Dates</th><th>Sources</th></tr>";

foreach ($formations_par_id as $formation_id => $occurrences) {
    $formation = $occurrences[0]; // Prendre la première pour les infos générales
    $dates = [];
    $sources = [];
    
    foreach ($occurrences as $occ) {
        $dates[] = date('d/m/Y', strtotime($occ['date_fin']));
        $sources[] = $occ['source_table'];
    }
    
    $couleur = count($occurrences) > 1 ? 'orange' : 'black';
    echo "<tr style='color: $couleur;'>";
    echo "<td>" . $formation_id . "</td>";
    echo "<td><strong>" . $formation['code'] . "</strong></td>";
    echo "<td>" . count($occurrences) . "</td>";
    echo "<td>" . implode(', ', $dates) . "</td>";
    echo "<td>" . implode(', ', $sources) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 4: Logique manuelle pour compter les formations à renouveler
echo "<h3>4. 🧮 Comptage manuel des formations à renouveler</h3>";

$formations_a_renouveler_manuelles = [];
foreach ($formations_par_id as $formation_id => $occurrences) {
    // Prendre la formation avec la date_fin la plus récente
    usort($occurrences, function($a, $b) {
        return strtotime($b['date_fin']) - strtotime($a['date_fin']);
    });
    
    $formation_recente = $occurrences[0];
    
    // Vérifier si elle est à renouveler (SUR-FTS dans les 3 ans)
    if ($formation_recente['jours_restants'] <= 1095) {
        $formations_a_renouveler_manuelles[] = $formation_recente;
    }
}

echo "<p><strong>Comptage manuel: " . count($formations_a_renouveler_manuelles) . " formations à renouveler</strong></p>";

if (!empty($formations_a_renouveler_manuelles)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Date Fin (plus récente)</th><th>Jours Restants</th><th>Source</th></tr>";
    foreach ($formations_a_renouveler_manuelles as $formation) {
        echo "<tr>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . $formation['jours_restants'] . "</td>";
        echo "<td>" . $formation['source_table'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h3>📊 Résumé</h3>";
echo "<p><strong>Total formations SUR-FTS:</strong> " . count($formations_fts) . "</p>";
echo "<p><strong>Logique SQL actuelle:</strong> " . count($formations_debug) . "</p>";
echo "<p><strong>Comptage manuel:</strong> " . count($formations_a_renouveler_manuelles) . "</p>";

if (count($formations_debug) != count($formations_a_renouveler_manuelles)) {
    echo "<p style='color: red;'><strong>⚠️ PROBLÈME: La logique SQL ne donne pas le même résultat que le comptage manuel!</strong></p>";
} else {
    echo "<p style='color: green;'><strong>✅ La logique SQL fonctionne correctement</strong></p>";
}
?>
