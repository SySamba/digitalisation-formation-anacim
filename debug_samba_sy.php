<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Debug spécifique pour Samba Sy (2017081JD)</h2>";

// Trouver l'agent Samba Sy
$stmt_agent = $pdo->prepare("SELECT id, matricule, prenom, nom, email FROM agents WHERE matricule = '2017081JD' OR prenom LIKE '%samba%' OR nom LIKE '%sy%'");
$stmt_agent->execute();
$agent = $stmt_agent->fetch();

if (!$agent) {
    echo "<p style='color: red;'>❌ Agent Samba Sy non trouvé</p>";
    exit;
}

$agent_id = $agent['id'];
echo "<p><strong>Agent trouvé:</strong> {$agent['prenom']} {$agent['nom']} (ID: $agent_id, Matricule: {$agent['matricule']})</p>";

// Test 1: Toutes les formations effectuées (avec détails)
echo "<h3>1. 📋 Toutes les formations effectuées par Samba Sy</h3>";
$stmt_all = $pdo->prepare("
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
$stmt_all->execute([$agent_id, $agent_id]);
$all_formations = $stmt_all->fetchAll();

echo "<p><strong>Total formations effectuées: " . count($all_formations) . "</strong></p>";

if (!empty($all_formations)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Périodicité</th><th>Prochaine Échéance</th><th>Jours Restants</th><th>À Renouveler?</th><th>Source</th></tr>";
    
    foreach ($all_formations as $formation) {
        $a_renouveler = false;
        if ($formation['periodicite_mois'] > 0) {
            if (strpos($formation['code'], 'SUR-FTS') !== false) {
                // Formations techniques : échéance dans les 3 ans
                $a_renouveler = $formation['jours_restants'] <= (36 * 30); // 3 ans approximatif
            } else {
                // Autres formations : échéance dans les 6 mois
                $a_renouveler = $formation['jours_restants'] <= (6 * 30); // 6 mois approximatif
            }
        }
        
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

// Test 2: Formations à renouveler (logique rapports_formations.php)
echo "<h3>2. 🔄 Formations à renouveler (logique rapports_formations.php)</h3>";
$stmt_rapports = $pdo->prepare("
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
    WHERE rn = 1
    AND (
        (code LIKE 'SUR-FTS-%' AND prochaine_echeance_calculee <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH))
        OR
        (code NOT LIKE 'SUR-FTS-%' AND prochaine_echeance_calculee <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
    )
    ORDER BY jours_restants ASC
");

try {
    $stmt_rapports->execute([$agent_id, $agent_id]);
    $formations_rapports = $stmt_rapports->fetchAll();
    echo "<p style='color: green;'>✅ Logique rapports_formations.php (avec ROW_NUMBER): " . count($formations_rapports) . " formations</p>";
    
    if (!empty($formations_rapports)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Jours Restants</th><th>Source</th></tr>";
        foreach ($formations_rapports as $formation) {
            echo "<tr>";
            echo "<td><strong>" . $formation['code'] . "</strong></td>";
            echo "<td>" . substr($formation['intitule'], 0, 40) . "...</td>";
            echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
            echo "<td>" . $formation['jours_restants'] . "</td>";
            echo "<td>" . $formation['source_table'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur avec ROW_NUMBER: " . $e->getMessage() . "</p>";
}

// Test 3: Formations à renouveler (logique get_agent_details.php - compatible MySQL 5.7)
echo "<h3>3. 🔄 Formations à renouveler (logique get_agent_details.php - compatible)</h3>";
$stmt_details = $pdo->prepare("
    SELECT DISTINCT 
           t1.formation_id,
           t1.agent_id,
           t1.centre_formation,
           t1.date_debut,
           t1.date_fin,
           t1.fichier_joint,
           t1.statut,
           t1.prochaine_echeance,
           t1.created_at,
           t1.intitule,
           t1.code,
           t1.categorie,
           t1.periodicite_mois,
           DATE_ADD(t1.date_fin, INTERVAL t1.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(t1.date_fin, INTERVAL t1.periodicite_mois MONTH), CURDATE()) as jours_restants,
           t1.source_table
    FROM (
        SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois, 'formations_effectuees' as source_table
        FROM formations_effectuees fe
        JOIN formations f ON fe.formation_id = f.id
        WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide') AND f.periodicite_mois > 0
        
        UNION ALL
        
        SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois, 'formations_agents' as source_table
        FROM formations_agents fa
        JOIN formations f ON fa.formation_id = f.id
        WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide') AND f.periodicite_mois > 0
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
    AND (
        (t1.code LIKE 'SUR-FTS-%' AND DATE_ADD(t1.date_fin, INTERVAL t1.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH))
        OR
        (t1.code NOT LIKE 'SUR-FTS-%' AND DATE_ADD(t1.date_fin, INTERVAL t1.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
    )
    ORDER BY jours_restants ASC
");

$stmt_details->execute([$agent_id, $agent_id, $agent_id, $agent_id]);
$formations_details = $stmt_details->fetchAll();
echo "<p style='color: blue;'>ℹ️ Logique get_agent_details.php (compatible): " . count($formations_details) . " formations</p>";

if (!empty($formations_details)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Jours Restants</th><th>Source</th></tr>";
    foreach ($formations_details as $formation) {
        echo "<tr>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 40) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . $formation['jours_restants'] . "</td>";
        echo "<td>" . $formation['source_table'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Résumé
echo "<h3>4. 📊 Résumé des résultats</h3>";
echo "<div style='display: flex; gap: 20px;'>";

echo "<div style='border: 2px solid #17a2b8; padding: 15px; text-align: center; min-width: 200px;'>";
echo "<h4 style='color: #17a2b8;'>RAPPORTS_FORMATIONS.PHP</h4>";
echo "<h2 style='color: #17a2b8;'>" . (isset($formations_rapports) ? count($formations_rapports) : 'Erreur') . "</h2>";
echo "<small>Formations à renouveler<br>(avec ROW_NUMBER)</small>";
echo "</div>";

echo "<div style='border: 2px solid #28a745; padding: 15px; text-align: center; min-width: 200px;'>";
echo "<h4 style='color: #28a745;'>GET_AGENT_DETAILS.PHP</h4>";
echo "<h2 style='color: #28a745;'>" . count($formations_details) . "</h2>";
echo "<small>Formations à renouveler<br>(compatible MySQL 5.7)</small>";
echo "</div>";

echo "</div>";

echo "<hr>";
echo "<h3>🔗 Liens de test</h3>";
echo "<a href='rapports_formations.php' target='_blank'>rapports_formations.php</a><br>";
echo "<a href='admin.php' target='_blank'>admin.php (puis cliquer sur Samba Sy)</a><br>";
echo "<a href='ajax/get_agent_details.php?id=$agent_id' target='_blank'>get_agent_details.php direct</a><br>";
?>
