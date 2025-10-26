<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔍 Test Planning - Étape par Étape</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #124c97; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .btn { padding: 10px 20px; background: #124c97; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
</style>";

echo "<div class='step'>";
echo "<h3>📋 Instructions de Test</h3>";
echo "<p>Suivez ces étapes <strong>exactement dans cet ordre</strong> :</p>";
echo "<ol>";
echo "<li>Ouvrez <code>admin.php</code> dans un nouvel onglet</li>";
echo "<li>Appuyez sur <strong>Ctrl + Shift + R</strong> pour vider le cache</li>";
echo "<li>Cliquez sur <strong>\"Voir Plus\"</strong> pour l'agent ID 5</li>";
echo "<li>Dans la modal, cliquez sur l'onglet <strong>\"Planning\"</strong> (pas \"Formations Non Effectuées\")</li>";
echo "<li>Cliquez sur <strong>\"Planifier Formation\"</strong></li>";
echo "<li>Ouvrez la console (F12) et regardez les messages</li>";
echo "<li>Remplissez le formulaire et soumettez</li>";
echo "</ol>";
echo "</div>";

// Test direct du formulaire de planning
echo "<div class='step'>";
echo "<h3>🧪 Test Direct du Formulaire</h3>";

if ($_POST && isset($_POST['test_direct'])) {
    // Simuler exactement ce que fait le formulaire
    $_SESSION['admin_logged_in'] = true;
    
    $test_data = [
        'agent_id' => '5',
        'formation_id' => '1',
        'centre_formation_prevu' => 'ENAC',
        'date_prevue_debut' => date('Y-m-d', strtotime('+1 day')),
        'date_prevue_fin' => date('Y-m-d', strtotime('+6 days')),
        'ville' => 'Dakar',
        'pays' => 'Sénégal',
        'duree' => '5',
        'perdiem' => '50000',
        'priorite' => '2',
        'statut' => 'planifie',
        'commentaires' => 'Test direct - ' . date('Y-m-d H:i:s')
    ];
    
    // Sauvegarder directement
    try {
        $stmt = $db->prepare("
            INSERT INTO planning_formations 
            (agent_id, formation_id, date_prevue_debut, date_prevue_fin, centre_formation_prevu, 
             ville, pays, duree, perdiem, priorite, statut, commentaires, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $test_data['agent_id'],
            $test_data['formation_id'],
            $test_data['date_prevue_debut'],
            $test_data['date_prevue_fin'],
            $test_data['centre_formation_prevu'],
            $test_data['ville'],
            $test_data['pays'],
            $test_data['duree'],
            $test_data['perdiem'],
            $test_data['priorite'],
            $test_data['statut'],
            $test_data['commentaires']
        ]);
        
        if ($result) {
            $planning_id = $db->lastInsertId();
            echo "<div class='success'>✅ Planning créé avec succès! ID: {$planning_id}</div>";
            
            // Vérifier si ce planning apparaît maintenant
            $stmt = $db->prepare("
                SELECT pf.*, a.nom, a.prenom, f.code, f.intitule 
                FROM planning_formations pf 
                JOIN agents a ON pf.agent_id = a.id 
                JOIN formations f ON pf.formation_id = f.id 
                WHERE pf.id = ?
            ");
            $stmt->execute([$planning_id]);
            $planning_created = $stmt->fetch();
            
            if ($planning_created) {
                echo "<div class='success'>";
                echo "<strong>📋 Planning créé:</strong><br>";
                echo "Agent: {$planning_created['prenom']} {$planning_created['nom']}<br>";
                echo "Formation: {$planning_created['code']} - {$planning_created['intitule']}<br>";
                echo "Dates: Du " . date('d/m/Y', strtotime($planning_created['date_prevue_debut'])) . " au " . date('d/m/Y', strtotime($planning_created['date_prevue_fin'])) . "<br>";
                echo "Ville: {$planning_created['ville']}<br>";
                echo "Pays: {$planning_created['pays']}<br>";
                echo "Durée: {$planning_created['duree']} jours<br>";
                echo "Priorité: {$planning_created['priorite']}<br>";
                echo "</div>";
            }
        } else {
            echo "<div class='error'>❌ Échec de la création du planning</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='test_direct' class='btn'>🧪 Créer un Planning de Test</button>";
echo "</form>";
echo "</div>";

// Vérifier les plannings existants pour l'agent 5
echo "<div class='step'>";
echo "<h3>📊 Plannings Existants pour l'Agent 5</h3>";

try {
    $stmt = $db->prepare("
        SELECT pf.*, f.code, f.intitule 
        FROM planning_formations pf 
        JOIN formations f ON pf.formation_id = f.id 
        WHERE pf.agent_id = 5 AND pf.statut != 'annule'
        ORDER BY pf.created_at DESC
    ");
    $stmt->execute();
    $plannings_agent5 = $stmt->fetchAll();
    
    echo "<p><strong>Nombre de plannings:</strong> " . count($plannings_agent5) . "</p>";
    
    if (count($plannings_agent5) > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #124c97; color: white;'>";
        echo "<th>ID</th><th>Formation</th><th>Dates</th><th>Ville</th><th>Pays</th><th>Durée</th><th>Priorité</th><th>Créé le</th>";
        echo "</tr>";
        
        foreach ($plannings_agent5 as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['code']}</td>";
            echo "<td>" . date('d/m/Y', strtotime($p['date_prevue_debut'])) . " - " . date('d/m/Y', strtotime($p['date_prevue_fin'])) . "</td>";
            echo "<td>" . ($p['ville'] ?? 'N/A') . "</td>";
            echo "<td>" . ($p['pays'] ?? 'N/A') . "</td>";
            echo "<td>" . ($p['duree'] ?? 'N/A') . " jours</td>";
            echo "<td>" . ($p['priorite'] ?? 'N/A') . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($p['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='success'>✅ Ces plannings devraient apparaître dans la modal de l'agent!</div>";
    } else {
        echo "<div class='warning'>⚠️ Aucun planning trouvé pour l'agent 5. Créez-en un avec le bouton ci-dessus.</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Test de la détection "Déjà planifié"
echo "<div class='step'>";
echo "<h3>🔍 Test Détection 'Déjà Planifié'</h3>";

try {
    $stmt = $db->prepare("
        SELECT 
            f.id,
            f.code,
            f.intitule,
            COUNT(pf.id) as nb_planifications
        FROM formations f
        LEFT JOIN planning_formations pf ON f.id = pf.formation_id AND pf.agent_id = 5 AND pf.statut IN ('planifie', 'confirme')
        GROUP BY f.id, f.code, f.intitule
        HAVING nb_planifications > 0
        ORDER BY f.code
        LIMIT 10
    ");
    $stmt->execute();
    $formations_planifiees = $stmt->fetchAll();
    
    echo "<p><strong>Formations déjà planifiées pour l'agent 5:</strong> " . count($formations_planifiees) . "</p>";
    
    if (count($formations_planifiees) > 0) {
        echo "<ul>";
        foreach ($formations_planifiees as $f) {
            echo "<li><strong>{$f['code']}</strong> - " . substr($f['intitule'], 0, 60) . "... ({$f['nb_planifications']} fois)</li>";
        }
        echo "</ul>";
        
        echo "<div class='success'>✅ Ces formations devraient afficher 'Déjà planifié' dans la modal!</div>";
    } else {
        echo "<div class='warning'>⚠️ Aucune formation planifiée détectée pour l'agent 5.</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
}

echo "</div>";

echo "<div class='step'>";
echo "<h3>🎯 Prochaines Étapes</h3>";
echo "<p>Si vous avez créé un planning de test ci-dessus :</p>";
echo "<ol>";
echo "<li>Allez sur <code>admin.php</code></li>";
echo "<li>Faites <strong>Ctrl + Shift + R</strong></li>";
echo "<li>Ouvrez la modal de l'agent 5</li>";
echo "<li>Allez dans <strong>Planning → Planning Existant</strong></li>";
echo "<li>Vous devriez voir le planning créé</li>";
echo "<li>Allez dans <strong>Besoins de Formation</strong></li>";
echo "<li>La formation planifiée devrait afficher 'Déjà planifié'</li>";
echo "</ol>";
echo "</div>";

echo "<br>";
echo "<a href='admin.php' class='btn'>← Retour Admin</a>";
echo " <a href='ajax/get_agent_details.php?id=5' class='btn' target='_blank'>🔍 Ouvrir Modal Agent 5</a>";
?>
