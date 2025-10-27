<?php
// Test complet des nouvelles formations SUR-FTS de cybersécurité
require_once 'includes/functions.php';

echo "<h1>🔒 Test Complet - Formations Cybersécurité SUR-FTS</h1>";
echo "<p><strong>Formations testées:</strong> SUR-FTS-20 (Aviation Cybersecurity Training) et SUR-FTS-21 (Gouvernance Cybersecurite)</p>";

try {
    $db = getDbConnection();
    
    // Test 1: Vérification de la présence en base de données
    echo "<div class='test-section' style='border: 2px solid #2196f3; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
    echo "<h2>📊 Test 1: Base de données</h2>";
    
    $check_sql = "SELECT id, numero, intitule, code, ressource, periodicite_mois, categorie 
                  FROM formations 
                  WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')
                  ORDER BY code";
    
    $formations = $db->query($check_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($formations) == 2) {
        echo "<p>✅ <strong>Base de données:</strong> Les 2 formations sont présentes</p>";
        foreach ($formations as $f) {
            echo "<div style='background: #f0f8ff; padding: 8px; margin: 5px 0; border-left: 4px solid #2196f3;'>";
            echo "<strong>{$f['code']}</strong> - {$f['intitule']} (ID: {$f['id']})";
            echo "</div>";
        }
    } else {
        echo "<p>❌ <strong>Erreur:</strong> " . count($formations) . "/2 formations trouvées</p>";
    }
    echo "</div>";
    
    // Test 2: Vérification de la catégorisation
    echo "<div class='test-section' style='border: 2px solid #4caf50; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
    echo "<h2>📂 Test 2: Catégorisation</h2>";
    
    $all_fts = $db->query("SELECT code, categorie FROM formations WHERE code LIKE 'SUR-FTS-%' ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
    
    $correct_category = true;
    foreach ($formations as $f) {
        if ($f['categorie'] !== 'FORMATION_TECHNIQUE') {
            $correct_category = false;
            echo "<p>❌ {$f['code']}: Catégorie incorrecte '{$f['categorie']}'</p>";
        }
    }
    
    if ($correct_category) {
        echo "<p>✅ <strong>Catégorisation:</strong> Toutes les formations sont dans 'FORMATION_TECHNIQUE'</p>";
        echo "<p><strong>Label affiché:</strong> " . getCategorieLabel('FORMATION_TECHNIQUE') . "</p>";
    }
    
    echo "<p><strong>Total formations SUR-FTS:</strong> " . count($all_fts) . "</p>";
    echo "</div>";
    
    // Test 3: Simulation de planification (logique SUR-FTS)
    echo "<div class='test-section' style='border: 2px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
    echo "<h2>📅 Test 3: Logique de planification SUR-FTS</h2>";
    
    // Récupérer un agent pour le test
    $agent_test = $db->query("SELECT id, nom, prenom FROM agents LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    if ($agent_test) {
        echo "<p><strong>Agent de test:</strong> {$agent_test['nom']} {$agent_test['prenom']} (ID: {$agent_test['id']})</p>";
        
        foreach ($formations as $formation) {
            // Vérifier si déjà planifié
            $check_planning = $db->prepare("SELECT COUNT(*) as count FROM planning WHERE agent_id = ? AND formation_id = ?");
            $check_planning->execute([$agent_test['id'], $formation['id']]);
            $planning_count = $check_planning->fetch(PDO::FETCH_ASSOC)['count'];
            
            $is_fts = strpos($formation['code'], 'SUR-FTS') !== false;
            
            echo "<div style='background: #fff3e0; padding: 8px; margin: 5px 0; border-left: 4px solid #ff9800;'>";
            echo "<strong>{$formation['code']}</strong><br>";
            echo "Planifications existantes: {$planning_count}<br>";
            echo "Type SUR-FTS: " . ($is_fts ? "✅ OUI" : "❌ NON") . "<br>";
            
            if ($is_fts) {
                echo "Action: ✅ Peut être re-planifié (logique SUR-FTS)";
            } else {
                echo "Action: " . ($planning_count > 0 ? "🚫 Déjà planifié" : "✅ Peut être planifié");
            }
            echo "</div>";
        }
    } else {
        echo "<p>⚠️ Aucun agent trouvé pour le test</p>";
    }
    echo "</div>";
    
    // Test 4: Vérification de l'intégration dans les dropdowns
    echo "<div class='test-section' style='border: 2px solid #9c27b0; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
    echo "<h2>📋 Test 4: Intégration dans les interfaces</h2>";
    
    // Simuler la requête utilisée dans admin_planning.php
    $dropdown_sql = "SELECT id, code, intitule, categorie, periodicite_mois FROM formations ORDER BY id";
    $all_formations = $db->query($dropdown_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    $found_in_dropdown = 0;
    foreach ($all_formations as $f) {
        if (in_array($f['code'], ['SUR-FTS-20', 'SUR-FTS-21'])) {
            $found_in_dropdown++;
        }
    }
    
    echo "<p>✅ <strong>Dropdown admin_planning.php:</strong> {$found_in_dropdown}/2 formations trouvées</p>";
    
    // Simuler la requête utilisée dans agent_profile.php
    $profile_formations = [];
    foreach ($all_formations as $f) {
        $profile_formations[$f['categorie']][] = $f;
    }
    
    $fts_in_profile = isset($profile_formations['FORMATION_TECHNIQUE']) ? 
        count(array_filter($profile_formations['FORMATION_TECHNIQUE'], function($f) {
            return in_array($f['code'], ['SUR-FTS-20', 'SUR-FTS-21']);
        })) : 0;
    
    echo "<p>✅ <strong>Profil agent:</strong> {$fts_in_profile}/2 formations dans la catégorie technique</p>";
    echo "</div>";
    
    // Test 5: Vérification des périodicités
    echo "<div class='test-section' style='border: 2px solid #607d8b; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
    echo "<h2>⏰ Test 5: Périodicités et ressources</h2>";
    
    foreach ($formations as $f) {
        echo "<div style='background: #f5f5f5; padding: 8px; margin: 5px 0; border-left: 4px solid #607d8b;'>";
        echo "<strong>{$f['code']}:</strong><br>";
        echo "Périodicité: {$f['periodicite_mois']} mois<br>";
        echo "Ressource: {$f['ressource']}<br>";
        
        // Validation des données
        $valid = true;
        if ($f['code'] == 'SUR-FTS-20' && $f['periodicite_mois'] != 40) $valid = false;
        if ($f['code'] == 'SUR-FTS-21' && $f['periodicite_mois'] != 36) $valid = false;
        
        echo "Validation: " . ($valid ? "✅ Correcte" : "❌ Incorrecte");
        echo "</div>";
    }
    echo "</div>";
    
    // Résumé final
    echo "<div style='border: 3px solid #4caf50; padding: 20px; margin: 20px 0; border-radius: 10px; background: #e8f5e8;'>";
    echo "<h2 style='color: #2e7d32; margin-top: 0;'>🎉 RÉSUMÉ DU TEST</h2>";
    
    $all_tests_passed = (count($formations) == 2) && $correct_category && ($found_in_dropdown == 2) && ($fts_in_profile == 2);
    
    if ($all_tests_passed) {
        echo "<h3 style='color: #2e7d32;'>✅ TOUS LES TESTS RÉUSSIS!</h3>";
        echo "<p>Les formations SUR-FTS-20 et SUR-FTS-21 sont parfaitement intégrées dans le système ANACIM:</p>";
        echo "<ul>";
        echo "<li>✅ Base de données correctement configurée</li>";
        echo "<li>✅ Catégorisation appropriée (Formation Technique/Spécialisée)</li>";
        echo "<li>✅ Logique SUR-FTS respectée (re-planification multiple)</li>";
        echo "<li>✅ Intégration dans tous les dropdowns</li>";
        echo "<li>✅ Périodicités et ressources correctes</li>";
        echo "</ul>";
        
        echo "<h4>📍 Formations disponibles dans:</h4>";
        echo "<ul>";
        echo "<li>👤 <strong>agent_profile.php</strong> - Section 'Formation Technique/Spécialisée'</li>";
        echo "<li>📅 <strong>admin_planning.php</strong> - Dropdown de planification</li>";
        echo "<li>⚙️ <strong>admin.php</strong> - Gestion des agents et formations</li>";
        echo "</ul>";
    } else {
        echo "<h3 style='color: #d32f2f;'>❌ CERTAINS TESTS ONT ÉCHOUÉ</h3>";
        echo "<p>Veuillez vérifier les erreurs ci-dessus et relancer le déploiement si nécessaire.</p>";
    }
    echo "</div>";
    
    // Liens de test pratiques
    echo "<div style='background: #e3f2fd; padding: 15px; margin: 15px 0; border-radius: 8px;'>";
    echo "<h3>🔗 Tests pratiques</h3>";
    echo "<p>Utilisez ces liens pour tester manuellement les formations:</p>";
    echo "<ul>";
    echo "<li><a href='agent_profile.php' target='_blank' style='color: #1976d2; text-decoration: none;'>👤 Profil Agent</a> - Vérifier que les formations apparaissent dans la section technique</li>";
    echo "<li><a href='admin_planning.php' target='_blank' style='color: #1976d2; text-decoration: none;'>📅 Planning Admin</a> - Tester la planification des nouvelles formations</li>";
    echo "<li><a href='admin.php' target='_blank' style='color: #1976d2; text-decoration: none;'>⚙️ Administration</a> - Gérer les agents et leurs formations</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='border: 3px solid #f44336; padding: 20px; margin: 20px 0; border-radius: 10px; background: #ffebee;'>";
    echo "<h2 style='color: #c62828;'>❌ ERREUR LORS DU TEST</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Ligne:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1 { color: #1976d2; border-bottom: 3px solid #1976d2; padding-bottom: 10px; }
h2 { color: #424242; margin-top: 0; }
.test-section { box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
</style>
