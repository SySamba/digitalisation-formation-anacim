<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔧 Debug Planning - Diagnostic Complet</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #f5c6cb; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ffeaa7; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #bee5eb; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .highlight { background-color: #ffeb3b !important; }
</style>";

// 1. Vérifier la structure de la table planning_formations
echo "<h3>1️⃣ Structure de la table planning_formations</h3>";
try {
    $stmt = $db->query("DESCRIBE planning_formations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_fields = ['ville', 'pays', 'duree', 'perdiem', 'priorite'];
    $existing_fields = array_column($columns, 'Field');
    $missing_fields = array_diff($required_fields, $existing_fields);
    
    if (empty($missing_fields)) {
        echo "<div class='success'>✅ Tous les champs requis existent dans la table</div>";
    } else {
        echo "<div class='error'>❌ Champs manquants: " . implode(', ', $missing_fields) . "</div>";
        echo "<div class='warning'>⚠️ Vous devez exécuter le script SQL: add_planning_fields.sql</div>";
    }
    
    echo "<table><tr><th>Champ</th><th>Type</th><th>Null</th><th>Défaut</th></tr>";
    foreach ($columns as $col) {
        $is_new = in_array($col['Field'], $required_fields);
        $class = $is_new ? 'class="highlight"' : '';
        echo "<tr {$class}>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
}

// 2. Vérifier les plannings existants
echo "<h3>2️⃣ Plannings existants dans la base</h3>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM planning_formations");
    $total = $stmt->fetch()['total'];
    
    echo "<div class='info'>📊 Total plannings: {$total}</div>";
    
    if ($total > 0) {
        $stmt = $db->query("
            SELECT pf.*, a.nom, a.prenom, f.code, f.intitule 
            FROM planning_formations pf 
            JOIN agents a ON pf.agent_id = a.id 
            JOIN formations f ON pf.formation_id = f.id 
            ORDER BY pf.created_at DESC 
            LIMIT 10
        ");
        $plannings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Les 10 derniers plannings:</h4>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Agent</th><th>Formation</th><th>Dates</th><th>Ville</th><th>Pays</th><th>Statut</th><th>Créé le</th></tr>";
        
        foreach ($plannings as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['nom']} {$p['prenom']}</td>";
            echo "<td>{$p['code']} - " . substr($p['intitule'], 0, 50) . "...</td>";
            echo "<td>Du " . date('d/m/Y', strtotime($p['date_prevue_debut'])) . " au " . date('d/m/Y', strtotime($p['date_prevue_fin'])) . "</td>";
            echo "<td>" . ($p['ville'] ?? 'N/A') . "</td>";
            echo "<td>" . ($p['pays'] ?? 'N/A') . "</td>";
            echo "<td>{$p['statut']}</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($p['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
}

// 3. Test de sauvegarde
echo "<h3>3️⃣ Test de sauvegarde d'un planning</h3>";

if ($_POST && isset($_POST['test_save'])) {
    try {
        // Récupérer le premier agent et la première formation
        $stmt = $db->query("SELECT id FROM agents LIMIT 1");
        $agent = $stmt->fetch();
        
        $stmt = $db->query("SELECT id FROM formations LIMIT 1");
        $formation = $stmt->fetch();
        
        if ($agent && $formation) {
            $test_data = [
                'agent_id' => $agent['id'],
                'formation_id' => $formation['id'],
                'date_prevue_debut' => date('Y-m-d', strtotime('+7 days')),
                'date_prevue_fin' => date('Y-m-d', strtotime('+12 days')),
                'centre_formation_prevu' => 'Test Centre',
                'ville' => 'Test Ville',
                'pays' => 'Test Pays',
                'duree' => 5,
                'perdiem' => 50000,
                'priorite' => '2',
                'statut' => 'planifie',
                'commentaires' => 'Test planning - ' . date('Y-m-d H:i:s')
            ];
            
            $sql = "INSERT INTO planning_formations 
                    (agent_id, formation_id, date_prevue_debut, date_prevue_fin, centre_formation_prevu, 
                     ville, pays, duree, perdiem, priorite, statut, commentaires, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
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
                echo "<div class='success'>✅ Test de sauvegarde réussi! Planning ID: {$planning_id}</div>";
                echo "<div class='info'>📝 Données sauvegardées: " . json_encode($test_data, JSON_PRETTY_PRINT) . "</div>";
            } else {
                echo "<div class='error'>❌ Échec de la sauvegarde</div>";
            }
        } else {
            echo "<div class='error'>❌ Aucun agent ou formation trouvé pour le test</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erreur lors du test: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<form method='POST'>";
    echo "<button type='submit' name='test_save' class='btn btn-primary' style='padding: 10px 20px; background: #124c97; color: white; border: none; border-radius: 5px; cursor: pointer;'>🧪 Tester la sauvegarde</button>";
    echo "</form>";
}

// 4. Vérifier les agents et formations
echo "<h3>4️⃣ Agents et Formations disponibles</h3>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM agents");
    $agents_count = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM formations");
    $formations_count = $stmt->fetch()['total'];
    
    echo "<div class='info'>";
    echo "👥 Agents: {$agents_count}<br>";
    echo "📚 Formations: {$formations_count}";
    echo "</div>";
    
    if ($agents_count == 0) {
        echo "<div class='error'>❌ Aucun agent dans la base de données!</div>";
    }
    
    if ($formations_count == 0) {
        echo "<div class='error'>❌ Aucune formation dans la base de données!</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
}

// 5. Vérifier les centres de formation
echo "<h3>5️⃣ Centres de formation</h3>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM centres_formation");
    $centres_count = $stmt->fetch()['total'];
    
    echo "<div class='info'>🏢 Centres: {$centres_count}</div>";
    
    if ($centres_count == 0) {
        echo "<div class='warning'>⚠️ Aucun centre de formation. Cela peut poser problème.</div>";
    } else {
        $stmt = $db->query("SELECT nom FROM centres_formation LIMIT 5");
        $centres = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<div class='info'>Centres disponibles: " . implode(', ', $centres) . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<h3>🔧 Actions Recommandées</h3>";
echo "<ol>";
echo "<li><strong>Si des champs manquent:</strong> Exécutez le script SQL <code>add_planning_fields.sql</code> dans phpMyAdmin</li>";
echo "<li><strong>Si aucun planning n'apparaît:</strong> Testez la sauvegarde avec le bouton ci-dessus</li>";
echo "<li><strong>Si le test échoue:</strong> Vérifiez les logs d'erreur PHP</li>";
echo "<li><strong>Si tout semble OK:</strong> Le problème vient du JavaScript ou de l'affichage</li>";
echo "</ol>";

echo "<br><a href='admin.php' style='display: inline-block; padding: 10px 20px; background: #124c97; color: white; text-decoration: none; border-radius: 5px;'>← Retour Admin</a>";
?>
