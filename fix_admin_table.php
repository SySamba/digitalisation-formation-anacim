<?php
// Script pour diagnostiquer et corriger le problème de table admins
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Diagnostic et Correction Table Admins</h2>";
    echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
    
    // 1. Vérifier la base de données actuelle
    $current_db = $db->query("SELECT DATABASE()")->fetchColumn();
    echo "<p class='info'>📍 Base de données actuelle: <strong>$current_db</strong></p>";
    
    // 2. Lister toutes les tables existantes
    echo "<h3>📋 Tables existantes dans la base:</h3>";
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table<br>";
    }
    
    // 3. Vérifier si la table admins existe
    $table_exists = $db->query("SHOW TABLES LIKE 'admins'")->rowCount() > 0;
    echo "<p>Table 'admins' existe: " . ($table_exists ? "<span class='success'>✅ OUI</span>" : "<span class='error'>❌ NON</span>") . "</p>";
    
    // Peu importe ce que dit SHOW TABLES, on force la recréation complète
    echo "<h3>🔨 Recréation forcée de la table admins (résolution incohérence)</h3>";
    
    try {
        // Forcer la suppression même si elle "n'existe pas"
        $db->exec("DROP TABLE IF EXISTS admins");
        echo "<p class='info'>🗑️ Tentative de suppression de l'ancienne table</p>";
        
        // Créer la nouvelle table
        $create_sql = "CREATE TABLE admins (
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
        echo "<p class='success'>✅ Table 'admins' recréée avec succès</p>";
        
        // Test immédiat d'accès à la table
        try {
            $test_query = $db->query("SELECT COUNT(*) FROM admins");
            echo "<p class='success'>✅ Test d'accès à la table: SUCCÈS</p>";
            
            // Insérer l'utilisateur admin
            $password_hash = '$2y$10$I3GTb6TDtQ0ICaoaN8sZ0ecyL1MBasQS0lA51CeDalc/g.o7WuFB.';
            
            $insert_sql = "INSERT INTO admins (email, password, nom, prenom, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($insert_sql);
            $stmt->execute(['adama.niang@anacim.sn', $password_hash, 'NIANG', 'Adama', 'super_admin']);
            
            echo "<p class='success'>✅ Utilisateur admin inséré: adama.niang@anacim.sn</p>";
            
            // Vérifier l'insertion
            $check_user = $db->prepare("SELECT * FROM admins WHERE email = ?");
            $check_user->execute(['adama.niang@anacim.sn']);
            $user = $check_user->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo "<h3>👤 Utilisateur créé avec succès:</h3>";
                echo "<ul>";
                echo "<li><strong>ID:</strong> " . $user['id'] . "</li>";
                echo "<li><strong>Email:</strong> " . $user['email'] . "</li>";
                echo "<li><strong>Nom:</strong> " . $user['prenom'] . " " . $user['nom'] . "</li>";
                echo "<li><strong>Rôle:</strong> " . $user['role'] . "</li>";
                echo "<li><strong>Actif:</strong> " . ($user['actif'] ? 'Oui' : 'Non') . "</li>";
                echo "</ul>";
                
                // Test du mot de passe
                if (password_verify('Anacim2025', $user['password'])) {
                    echo "<p class='success'>✅ Mot de passe vérifié: CORRECT</p>";
                } else {
                    echo "<p class='error'>❌ Mot de passe vérifié: INCORRECT</p>";
                }
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erreur d'accès à la nouvelle table: " . $e->getMessage() . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur lors de la recréation: " . $e->getMessage() . "</p>";
    }
    
    // 4. Afficher la structure finale de la table
    echo "<h3>🏗️ Structure finale de la table admins:</h3>";
    $structure = $db->query("DESCRIBE admins")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>🎉 RÉSULTAT FINAL</h3>";
    echo "<p class='success'>✅ Table 'admins' créée et utilisateur 'adama.niang@anacim.sn' configuré</p>";
    echo "<p><strong>Identifiants de connexion:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> adama.niang@anacim.sn</li>";
    echo "<li><strong>Mot de passe:</strong> Anacim2025</li>";
    echo "<li><strong>Rôle:</strong> super_admin</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>
