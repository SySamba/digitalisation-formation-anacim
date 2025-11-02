<?php
session_start();
require_once 'config/database.php';

$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 1;

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Debug du détail agent pour l'agent ID: $agent_id</h2>";

// Test 1: Vérifier si ROW_NUMBER() fonctionne
echo "<h3>1. Test de ROW_NUMBER() (compatibilité MySQL)</h3>";
try {
    $test_query = "SELECT ROW_NUMBER() OVER (ORDER BY id) as rn, id FROM agents LIMIT 1";
    $stmt_test = $pdo->prepare($test_query);
    $stmt_test->execute();
    $result = $stmt_test->fetch();
    if ($result) {
        echo "<p style='color: green;'>✅ ROW_NUMBER() fonctionne (MySQL 8.0+)</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ROW_NUMBER() ne fonctionne pas: " . $e->getMessage() . "</p>";
    echo "<p><strong>Solution:</strong> Utiliser une approche alternative pour MySQL 5.7</p>";
}

// Test 2: Requête actuelle du détail agent
echo "<h3>2. Test de la requête actuelle (get_agent_details.php)</h3>";
try {
    $stmt_current = $pdo->prepare("
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
    $stmt_current->execute([$agent_id, $agent_id]);
    $formations_current = $stmt_current->fetchAll();
    
    echo "<p style='color: green;'>✅ Requête actuelle fonctionne</p>";
    echo "<p><strong>Formations à renouveler trouvées:</strong> " . count($formations_current) . "</p>";
    
    if (!empty($formations_current)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Jours Restants</th><th>Source</th></tr>";
        foreach ($formations_current as $formation) {
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
    echo "<p style='color: red;'>❌ Erreur avec la requête actuelle: " . $e->getMessage() . "</p>";
    
    // Test 3: Requête alternative sans ROW_NUMBER()
    echo "<h3>3. Requête alternative (compatible MySQL 5.7)</h3>";
    try {
        $stmt_alt = $pdo->prepare("
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
        $stmt_alt->execute([$agent_id, $agent_id, $agent_id, $agent_id]);
        $formations_alt = $stmt_alt->fetchAll();
        
        echo "<p style='color: green;'>✅ Requête alternative fonctionne</p>";
        echo "<p><strong>Formations à renouveler trouvées:</strong> " . count($formations_alt) . "</p>";
        
        if (!empty($formations_alt)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Jours Restants</th><th>Source</th></tr>";
            foreach ($formations_alt as $formation) {
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
        
    } catch (Exception $e2) {
        echo "<p style='color: red;'>❌ Erreur avec la requête alternative: " . $e2->getMessage() . "</p>";
    }
}

// Test 4: Vérifier la version MySQL
echo "<h3>4. Version MySQL</h3>";
try {
    $version_query = "SELECT VERSION() as version";
    $stmt_version = $pdo->prepare($version_query);
    $stmt_version->execute();
    $version = $stmt_version->fetch();
    echo "<p><strong>Version MySQL:</strong> " . $version['version'] . "</p>";
    
    $version_number = floatval($version['version']);
    if ($version_number >= 8.0) {
        echo "<p style='color: green;'>✅ MySQL 8.0+ - ROW_NUMBER() supporté</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ MySQL < 8.0 - ROW_NUMBER() peut ne pas être supporté</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur version: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>🔗 Liens de test</h3>";
echo "<a href='admin.php' target='_blank'>Admin.php</a><br>";
echo "<a href='ajax/get_agent_details.php?id=$agent_id' target='_blank'>get_agent_details.php direct</a><br>";
echo "<a href='rapports_formations.php?action=view_agent&agent_id=$agent_id' target='_blank'>rapports_formations.php</a><br>";
?>
