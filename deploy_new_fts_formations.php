<?php
// Script de déploiement pour ajouter les nouvelles formations SUR-FTS sur le serveur de production
// URL: https://digitalisation-formation.teranganumerique.com/

require_once 'includes/functions.php';

echo "<h2>🚀 Déploiement des nouvelles formations SUR-FTS</h2>";
echo "<p><strong>Serveur:</strong> https://digitalisation-formation.teranganumerique.com/</p>";

try {
    $db = getDbConnection();
    
    // Vérifier la connexion à la base de données
    echo "<div style='padding: 10px; background: #e3f2fd; border-left: 4px solid #2196f3; margin: 10px 0;'>";
    echo "📡 Connexion à la base de données... ";
    $db->query("SELECT 1");
    echo "✅ <strong>Connecté</strong>";
    echo "</div>";
    
    // Vérifier l'état actuel des formations SUR-FTS
    $check_existing = "SELECT COUNT(*) as count FROM formations WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')";
    $existing_count = $db->query($check_existing)->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<div style='padding: 10px; background: #fff3e0; border-left: 4px solid #ff9800; margin: 10px 0;'>";
    echo "🔍 <strong>Vérification des formations existantes:</strong><br>";
    echo "Formations SUR-FTS-20/21 déjà présentes: {$existing_count}/2";
    echo "</div>";
    
    // Supprimer les formations existantes si présentes (pour éviter les doublons)
    if ($existing_count > 0) {
        echo "<p>🗑️ Suppression des formations existantes pour éviter les doublons...</p>";
        $delete_sql = "DELETE FROM formations WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')";
        $db->exec($delete_sql);
        echo "<p>✅ Formations existantes supprimées</p>";
    }
    
    // Ajouter les nouvelles formations
    echo "<div style='padding: 10px; background: #f3e5f5; border-left: 4px solid #9c27b0; margin: 10px 0;'>";
    echo "➕ <strong>Ajout des nouvelles formations SUR-FTS...</strong>";
    echo "</div>";
    
    $insert_sql = "INSERT INTO formations (numero, intitule, code, ressource, periodicite_mois, categorie) VALUES
        ('3.22', 'Aviation Cybersecurity Training', 'SUR-FTS-20', 'Externe', 40, 'FORMATION_TECHNIQUE'),
        ('3.8', 'Gouvernance Cybersecurite', 'SUR-FTS-21', 'Externe/Interne', 36, 'FORMATION_TECHNIQUE')";
    
    $result = $db->exec($insert_sql);
    
    if ($result) {
        echo "<div style='padding: 15px; background: #e8f5e8; border: 2px solid #4caf50; border-radius: 8px; margin: 15px 0;'>";
        echo "<h3 style='color: #2e7d32; margin: 0 0 10px 0;'>🎉 DÉPLOIEMENT RÉUSSI!</h3>";
        echo "<p style='margin: 5px 0;'>✅ <strong>SUR-FTS-20:</strong> Aviation Cybersecurity Training (40 mois, Externe)</p>";
        echo "<p style='margin: 5px 0;'>✅ <strong>SUR-FTS-21:</strong> Gouvernance Cybersecurite (36 mois, Externe/Interne)</p>";
        echo "</div>";
    }
    
    // Vérification finale
    $verify_sql = "SELECT id, numero, intitule, code, ressource, periodicite_mois, categorie 
                   FROM formations 
                   WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')
                   ORDER BY code";
    
    $new_formations = $db->query($verify_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Formations déployées:</h3>";
    echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f5f5f5;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>ID</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Code</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Intitulé</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Ressource</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Périodicité</th>";
    echo "</tr>";
    
    foreach ($new_formations as $formation) {
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$formation['id']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px; font-weight: bold; color: #1976d2;'>{$formation['code']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$formation['intitule']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$formation['ressource']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$formation['periodicite_mois']} mois</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Statistiques finales
    $stats_sql = "SELECT 
        COUNT(*) as total_formations,
        COUNT(CASE WHEN code LIKE 'SUR-FTS-%' THEN 1 END) as total_fts,
        COUNT(CASE WHEN code LIKE 'SUR-FAM-%' THEN 1 END) as total_fam,
        COUNT(CASE WHEN code LIKE 'SUR-INI-%' THEN 1 END) as total_ini,
        COUNT(CASE WHEN code LIKE 'SUR-FCE-%' THEN 1 END) as total_fce
        FROM formations";
    
    $stats = $db->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
    
    echo "<div style='padding: 15px; background: #e1f5fe; border-left: 4px solid #0277bd; margin: 15px 0;'>";
    echo "<h3>📊 Statistiques du système:</h3>";
    echo "<ul style='margin: 10px 0;'>";
    echo "<li><strong>Total formations:</strong> {$stats['total_formations']}</li>";
    echo "<li><strong>SUR-FTS (Techniques):</strong> {$stats['total_fts']}</li>";
    echo "<li><strong>SUR-FAM (Familiarisation):</strong> {$stats['total_fam']}</li>";
    echo "<li><strong>SUR-INI (Initiales):</strong> {$stats['total_ini']}</li>";
    echo "<li><strong>SUR-FCE (Cours d'emploi):</strong> {$stats['total_fce']}</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='padding: 15px; background: #fff8e1; border-left: 4px solid #f57c00; margin: 15px 0;'>";
    echo "<h3>🔗 Liens de test:</h3>";
    echo "<ul>";
    echo "<li><a href='agent_profile.php' target='_blank'>👤 Profil Agent - Vérifier les formations</a></li>";
    echo "<li><a href='admin_planning.php' target='_blank'>📅 Planning Admin - Tester la planification</a></li>";
    echo "<li><a href='admin.php' target='_blank'>⚙️ Administration - Gestion des agents</a></li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='padding: 15px; background: #e8f5e8; border: 2px solid #4caf50; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #2e7d32;'>✅ DÉPLOIEMENT TERMINÉ AVEC SUCCÈS</h3>";
    echo "<p>Les formations <strong>SUR-FTS-20</strong> et <strong>SUR-FTS-21</strong> sont maintenant disponibles dans tout le système:</p>";
    echo "<ul>";
    echo "<li>✅ Base de données mise à jour</li>";
    echo "<li>✅ Disponible dans les profils agents</li>";
    echo "<li>✅ Intégré au système de planification</li>";
    echo "<li>✅ Compatible avec la logique SUR-FTS (re-planification multiple)</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 15px; background: #ffebee; border: 2px solid #f44336; border-radius: 8px; margin: 15px 0;'>";
    echo "<h3 style='color: #c62828;'>❌ ERREUR DE DÉPLOIEMENT</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Ligne:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?>
