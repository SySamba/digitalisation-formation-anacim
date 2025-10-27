<?php
// Script pour résoudre le problème de tablespace corrompu
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Résolution Tablespace Corrompu - Table Admins</h2>";
    echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";
    
    // 1. Vérifier la base de données actuelle
    $current_db = $db->query("SELECT DATABASE()")->fetchColumn();
    echo "<p class='info'>📍 Base de données: <strong>$current_db</strong></p>";
    
    echo "<h3>🔍 Étape 1: Diagnostic du problème tablespace</h3>";
    
    // Essayer de supprimer le tablespace orphelin
    try {
        // Méthode 1: Forcer la suppression avec DISCARD TABLESPACE
        echo "<p class='info'>🗑️ Tentative de suppression du tablespace orphelin...</p>";
        
        // D'abord essayer de créer temporairement la table pour pouvoir faire DISCARD
        $temp_create = "CREATE TABLE IF NOT EXISTS admins_temp (id INT) ENGINE=InnoDB";
        $db->exec($temp_create);
        
        // Renommer pour récupérer le tablespace
        try {
            $db->exec("RENAME TABLE admins_temp TO admins");
            echo "<p class='info'>📝 Table temporaire renommée</p>";
            
            // Maintenant on peut faire DISCARD
            $db->exec("ALTER TABLE admins DISCARD TABLESPACE");
            echo "<p class='success'>✅ Tablespace supprimé avec DISCARD</p>";
            
            // Supprimer la table maintenant
            $db->exec("DROP TABLE admins");
            echo "<p class='success'>✅ Table admins supprimée</p>";
            
        } catch (Exception $e) {
            echo "<p class='warning'>⚠️ Méthode DISCARD échouée: " . $e->getMessage() . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='warning'>⚠️ Première méthode échouée: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>🔨 Étape 2: Nettoyage complet</h3>";
    
    // Méthode 2: Utiliser un nom de table différent
    try {
        // Supprimer toute trace de la table admins
        $cleanup_queries = [
            "DROP TABLE IF EXISTS admins",
            "DROP TABLE IF EXISTS admins_temp", 
            "DROP TABLE IF EXISTS admin_users"
        ];
        
        foreach ($cleanup_queries as $query) {
            try {
                $db->exec($query);
                echo "<p class='info'>🧹 Nettoyage: $query</p>";
            } catch (Exception $e) {
                // Ignorer les erreurs de nettoyage
            }
        }
        
        echo "<p class='success'>✅ Nettoyage terminé</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur nettoyage: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>🏗️ Étape 3: Création de la nouvelle table</h3>";
    
    // Créer la table avec un nom légèrement différent d'abord
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
    
    try {
        $db->exec($create_sql);
        echo "<p class='success'>✅ Table 'admin_users' créée avec succès</p>";
        
        // Test d'accès immédiat
        $test = $db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        echo "<p class='success'>✅ Test d'accès: $test lignes</p>";
        
        // Renommer vers le nom final
        try {
            $db->exec("RENAME TABLE admin_users TO admins");
            echo "<p class='success'>✅ Table renommée vers 'admins'</p>";
        } catch (Exception $e) {
            echo "<p class='warning'>⚠️ Renommage échoué, on garde 'admin_users': " . $e->getMessage() . "</p>";
            // On continue avec admin_users
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur création: " . $e->getMessage() . "</p>";
        
        // Méthode de dernier recours: table avec timestamp
        $timestamp = time();
        $fallback_name = "admins_" . $timestamp;
        
        echo "<p class='info'>🔄 Tentative avec nom unique: $fallback_name</p>";
        
        $fallback_sql = str_replace("admin_users", $fallback_name, $create_sql);
        try {
            $db->exec($fallback_sql);
            echo "<p class='success'>✅ Table '$fallback_name' créée</p>";
            
            // Essayer de renommer
            try {
                $db->exec("RENAME TABLE $fallback_name TO admins");
                echo "<p class='success'>✅ Table renommée vers 'admins'</p>";
            } catch (Exception $e) {
                echo "<p class='warning'>⚠️ On garde le nom '$fallback_name'</p>";
                // Mettre à jour les requêtes suivantes pour utiliser ce nom
                $table_name = $fallback_name;
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Échec complet: " . $e->getMessage() . "</p>";
            throw $e;
        }
    }
    
    echo "<h3>👤 Étape 4: Création de l'utilisateur admin</h3>";
    
    // Déterminer le nom de table à utiliser
    $final_table = 'admins';
    try {
        $db->query("SELECT 1 FROM admins LIMIT 1");
    } catch (Exception $e) {
        // Essayer admin_users
        try {
            $db->query("SELECT 1 FROM admin_users LIMIT 1");
            $final_table = 'admin_users';
            echo "<p class='info'>📝 Utilisation de la table: admin_users</p>";
        } catch (Exception $e2) {
            // Chercher une table avec timestamp
            $tables = $db->query("SHOW TABLES LIKE 'admins_%'")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($tables)) {
                $final_table = $tables[0];
                echo "<p class='info'>📝 Utilisation de la table: $final_table</p>";
            }
        }
    }
    
    // Insérer l'utilisateur admin
    $password_hash = '$2y$10$I3GTb6TDtQ0ICaoaN8sZ0ecyL1MBasQS0lA51CeDalc/g.o7WuFB.';
    
    $insert_sql = "INSERT INTO $final_table (email, password, nom, prenom, role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($insert_sql);
    $stmt->execute(['adama.niang@anacim.sn', $password_hash, 'NIANG', 'Adama', 'super_admin']);
    
    echo "<p class='success'>✅ Utilisateur admin inséré dans '$final_table'</p>";
    
    // Vérification finale
    $check_sql = "SELECT * FROM $final_table WHERE email = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute(['adama.niang@anacim.sn']);
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<h3>🎉 SUCCÈS - Utilisateur créé:</h3>";
        echo "<ul>";
        echo "<li><strong>Table utilisée:</strong> $final_table</li>";
        echo "<li><strong>ID:</strong> " . $user['id'] . "</li>";
        echo "<li><strong>Email:</strong> " . $user['email'] . "</li>";
        echo "<li><strong>Nom:</strong> " . $user['prenom'] . " " . $user['nom'] . "</li>";
        echo "<li><strong>Rôle:</strong> " . $user['role'] . "</li>";
        echo "</ul>";
        
        // Test du mot de passe
        if (password_verify('Anacim2025', $user['password'])) {
            echo "<p class='success'>✅ Mot de passe vérifié: CORRECT</p>";
        } else {
            echo "<p class='error'>❌ Mot de passe vérifié: INCORRECT</p>";
        }
        
        echo "<div style='background:#e8f5e8;padding:15px;border-radius:5px;margin:20px 0;'>";
        echo "<h4>🔐 Identifiants de connexion:</h4>";
        echo "<p><strong>Email:</strong> adama.niang@anacim.sn</p>";
        echo "<p><strong>Mot de passe:</strong> Anacim2025</p>";
        echo "<p><strong>Rôle:</strong> super_admin</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur fatale: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>
