<?php
// Script de déploiement pour le serveur de production
// URL: https://digitalisation-formation.teranganumerique.com/
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🚀 Déploiement Production - Mise à jour Administrateurs</h2>";
    echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";
    
    // Vérifier l'environnement
    $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    echo "<p class='info'>🌐 Serveur: <strong>$server_name</strong></p>";
    
    if (strpos($server_name, 'teranganumerique.com') !== false) {
        echo "<p class='success'>✅ Environnement de production détecté</p>";
    } else {
        echo "<p class='warning'>⚠️ Environnement de développement (localhost)</p>";
    }
    
    // Déterminer la table à utiliser
    $table_name = 'admin_users';
    $table_exists = $db->query("SHOW TABLES LIKE '$table_name'")->rowCount() > 0;
    
    if (!$table_exists) {
        // Essayer avec 'admins'
        $table_name = 'admins';
        $table_exists = $db->query("SHOW TABLES LIKE '$table_name'")->rowCount() > 0;
    }
    
    if (!$table_exists) {
        echo "<p class='error'>❌ Aucune table d'administration trouvée. Création nécessaire.</p>";
        
        // Créer la table admin_users
        $create_sql = "CREATE TABLE admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            role ENUM('admin', 'super_admin') DEFAULT 'admin',
            actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($create_sql);
        $table_name = 'admin_users';
        echo "<p class='success'>✅ Table '$table_name' créée</p>";
    } else {
        echo "<p class='info'>📋 Utilisation de la table: $table_name</p>";
    }
    
    // Générer le hash pour Anacim2025@
    $password = 'Anacim2025@';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<p class='info'>🔐 Hash généré pour: $password</p>";
    
    // 1. Mettre à jour/Créer Adama Niang
    echo "<h3>👤 Adama Niang</h3>";
    $check_adama = $db->prepare("SELECT id FROM $table_name WHERE email = ?");
    $check_adama->execute(['adama.niang@anacim.sn']);
    
    if ($check_adama->rowCount() > 0) {
        // Mettre à jour
        $update_adama = $db->prepare("UPDATE $table_name SET password = ?, updated_at = NOW() WHERE email = ?");
        $result = $update_adama->execute([$password_hash, 'adama.niang@anacim.sn']);
        echo "<p class='success'>✅ Mot de passe d'Adama mis à jour</p>";
    } else {
        // Créer
        $insert_adama = $db->prepare("INSERT INTO $table_name (email, password, nom, prenom, role, actif) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $insert_adama->execute(['adama.niang@anacim.sn', $password_hash, 'NIANG', 'Adama', 'super_admin', 1]);
        echo "<p class='success'>✅ Utilisateur Adama créé</p>";
    }
    
    // 2. Mettre à jour/Créer Coutaille Ba
    echo "<h3>👤 Coutaille Ba</h3>";
    $check_coutaille = $db->prepare("SELECT id FROM $table_name WHERE email = ?");
    $check_coutaille->execute(['coutay.ba@anacim.sn']);
    
    if ($check_coutaille->rowCount() > 0) {
        // Mettre à jour
        $update_coutaille = $db->prepare("UPDATE $table_name SET password = ?, updated_at = NOW() WHERE email = ?");
        $result = $update_coutaille->execute([$password_hash, 'coutay.ba@anacim.sn']);
        echo "<p class='success'>✅ Mot de passe de Coutaille mis à jour</p>";
    } else {
        // Créer
        $insert_coutaille = $db->prepare("INSERT INTO $table_name (email, password, nom, prenom, role, actif) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $insert_coutaille->execute(['coutay.ba@anacim.sn', $password_hash, 'BA', 'Coutaille', 'admin', 1]);
        echo "<p class='success'>✅ Utilisateur Coutaille créé</p>";
    }
    
    // 3. Vérification finale
    echo "<h3>📋 Vérification des utilisateurs</h3>";
    $all_users = $db->query("SELECT id, email, nom, prenom, role, actif, created_at FROM $table_name ORDER BY id");
    $users = $all_users->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Email</th><th>Nom</th><th>Prénom</th><th>Rôle</th><th>Test Connexion</th></tr>";
    
    foreach ($users as $user) {
        // Test du mot de passe
        $test_stmt = $db->prepare("SELECT password FROM $table_name WHERE id = ?");
        $test_stmt->execute([$user['id']]);
        $hash = $test_stmt->fetchColumn();
        
        $password_ok = password_verify($password, $hash);
        
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['nom'] . "</td>";
        echo "<td>" . $user['prenom'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . ($password_ok ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ NOK</span>") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. Instructions finales
    echo "<hr>";
    echo "<div style='background:#e8f5e8;padding:15px;border-radius:5px;'>";
    echo "<h3>🎉 DÉPLOIEMENT TERMINÉ</h3>";
    echo "<p><strong>Serveur:</strong> $server_name</p>";
    echo "<p><strong>Table utilisée:</strong> $table_name</p>";
    echo "<p><strong>Identifiants de connexion :</strong></p>";
    echo "<ul>";
    echo "<li><strong>Adama Niang :</strong> adama.niang@anacim.sn / Anacim2025@</li>";
    echo "<li><strong>Coutaille Ba :</strong> coutay.ba@anacim.sn / Anacim2025@</li>";
    echo "</ul>";
    echo "<p>✅ Les utilisateurs peuvent maintenant se connecter sur le serveur de production.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>
