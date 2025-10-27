<?php
// Script pour ajouter les nouvelles formations de cybersécurité SUR-FTS
require_once 'includes/functions.php';

echo "<h2>Ajout des nouvelles formations SUR-FTS de cybersécurité</h2>";

try {
    $db = getDbConnection();
    
    // Vérifier si les formations existent déjà
    $check_sql = "SELECT code FROM formations WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')";
    $existing = $db->query($check_sql)->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($existing)) {
        echo "<div class='alert alert-warning'>Les formations suivantes existent déjà: " . implode(', ', $existing) . "</div>";
        echo "<p>Suppression des formations existantes pour les recréer...</p>";
        
        $delete_sql = "DELETE FROM formations WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')";
        $db->exec($delete_sql);
        echo "<p>✅ Formations existantes supprimées</p>";
    }
    
    // Ajouter les nouvelles formations
    $insert_sql = "INSERT INTO formations (numero, intitule, code, ressource, periodicite_mois, categorie) VALUES
        ('3.22', 'Aviation Cybersecurity Training', 'SUR-FTS-20', 'Externe', 40, 'FORMATION_TECHNIQUE'),
        ('3.8', 'Gouvernance Cybersecurite', 'SUR-FTS-21', 'Externe/Interne', 36, 'FORMATION_TECHNIQUE')";
    
    $db->exec($insert_sql);
    echo "<p>✅ Nouvelles formations SUR-FTS ajoutées avec succès!</p>";
    
    // Vérifier l'ajout
    $verify_sql = "SELECT id, numero, intitule, code, ressource, periodicite_mois, categorie 
                   FROM formations 
                   WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')
                   ORDER BY code";
    
    $new_formations = $db->query($verify_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Formations ajoutées:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Numéro</th><th>Intitulé</th><th>Code</th><th>Ressource</th><th>Périodicité (mois)</th><th>Catégorie</th></tr>";
    
    foreach ($new_formations as $formation) {
        echo "<tr>";
        echo "<td>{$formation['id']}</td>";
        echo "<td>{$formation['numero']}</td>";
        echo "<td>{$formation['intitule']}</td>";
        echo "<td><strong>{$formation['code']}</strong></td>";
        echo "<td>{$formation['ressource']}</td>";
        echo "<td>{$formation['periodicite_mois']}</td>";
        echo "<td>{$formation['categorie']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Compter le total des formations SUR-FTS
    $count_sql = "SELECT COUNT(*) as total FROM formations WHERE code LIKE 'SUR-FTS-%'";
    $total = $db->query($count_sql)->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Total des formations SUR-FTS: {$total['total']}</strong></p>";
    
    // Afficher toutes les formations SUR-FTS pour vérification
    echo "<h3>Toutes les formations SUR-FTS:</h3>";
    $all_fts_sql = "SELECT id, numero, intitule, code, ressource, periodicite_mois
                    FROM formations 
                    WHERE code LIKE 'SUR-FTS-%'
                    ORDER BY CAST(SUBSTRING(code, 8) AS UNSIGNED)";
    
    $all_fts = $db->query($all_fts_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Numéro</th><th>Intitulé</th><th>Code</th><th>Ressource</th><th>Périodicité</th></tr>";
    
    foreach ($all_fts as $formation) {
        $highlight = in_array($formation['code'], ['SUR-FTS-20', 'SUR-FTS-21']) ? 'style="background-color: #d4edda;"' : '';
        echo "<tr {$highlight}>";
        echo "<td>{$formation['id']}</td>";
        echo "<td>{$formation['numero']}</td>";
        echo "<td>{$formation['intitule']}</td>";
        echo "<td><strong>{$formation['code']}</strong></td>";
        echo "<td>{$formation['ressource']}</td>";
        echo "<td>{$formation['periodicite_mois']} mois</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='margin-top: 20px; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h4>✅ Succès!</h4>";
    echo "<p>Les formations SUR-FTS-20 (Aviation Cybersecurity Training) et SUR-FTS-21 (Gouvernance Cybersecurite) ont été ajoutées avec succès.</p>";
    echo "<p>Elles sont maintenant disponibles dans:</p>";
    echo "<ul>";
    echo "<li>📋 agent_profile.php (profil des agents)</li>";
    echo "<li>📅 admin_planning.php (système de planification)</li>";
    echo "<li>👥 admin.php (gestion des agents)</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Erreur: " . $e->getMessage() . "</div>";
}
?>
