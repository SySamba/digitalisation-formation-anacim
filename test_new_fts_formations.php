<?php
// Test simple pour vérifier les nouvelles formations SUR-FTS
require_once 'includes/functions.php';

echo "<h2>Test des nouvelles formations SUR-FTS de cybersécurité</h2>";

try {
    $db = getDbConnection();
    
    // Ajouter les formations si elles n'existent pas
    $check_sql = "SELECT COUNT(*) as count FROM formations WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')";
    $existing_count = $db->query($check_sql)->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($existing_count < 2) {
        echo "<p>Ajout des formations manquantes...</p>";
        
        // Supprimer d'abord si partiellement présentes
        $db->exec("DELETE FROM formations WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')");
        
        // Ajouter les nouvelles formations
        $insert_sql = "INSERT INTO formations (numero, intitule, code, ressource, periodicite_mois, categorie) VALUES
            ('3.22', 'Aviation Cybersecurity Training', 'SUR-FTS-20', 'Externe', 40, 'FORMATION_TECHNIQUE'),
            ('3.8', 'Gouvernance Cybersecurite', 'SUR-FTS-21', 'Externe/Interne', 36, 'FORMATION_TECHNIQUE')";
        
        $db->exec($insert_sql);
        echo "<p>✅ Formations ajoutées!</p>";
    } else {
        echo "<p>✅ Les formations existent déjà</p>";
    }
    
    // Vérifier les formations ajoutées
    $verify_sql = "SELECT id, numero, intitule, code, ressource, periodicite_mois 
                   FROM formations 
                   WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')
                   ORDER BY code";
    
    $formations = $db->query($verify_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Nouvelles formations SUR-FTS:</h3>";
    foreach ($formations as $formation) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0; background: #f9f9f9;'>";
        echo "<strong>ID:</strong> {$formation['id']}<br>";
        echo "<strong>Code:</strong> {$formation['code']}<br>";
        echo "<strong>Intitulé:</strong> {$formation['intitule']}<br>";
        echo "<strong>Ressource:</strong> {$formation['ressource']}<br>";
        echo "<strong>Périodicité:</strong> {$formation['periodicite_mois']} mois<br>";
        echo "</div>";
    }
    
    // Compter toutes les formations SUR-FTS
    $count_sql = "SELECT COUNT(*) as total FROM formations WHERE code LIKE 'SUR-FTS-%'";
    $total = $db->query($count_sql)->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p><strong>Total formations SUR-FTS: {$total}</strong></p>";
    
    echo "<p><a href='agent_profile.php' target='_blank'>🔗 Tester dans agent_profile.php</a></p>";
    echo "<p><a href='admin_planning.php' target='_blank'>🔗 Tester dans admin_planning.php</a></p>";
    echo "<p><a href='admin.php' target='_blank'>🔗 Tester dans admin.php</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>
