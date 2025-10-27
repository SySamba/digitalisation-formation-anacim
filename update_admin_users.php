<?php
// Script pour modifier le mot de passe d'Adama Niang et ajouter Coutaille Ba
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Mise à jour des utilisateurs administrateurs</h2>";
    echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
    
    // Déterminer quelle table utiliser (admins ou admin_users)
    $table_name = 'admins';
    $admins_exists = $db->query("SHOW TABLES LIKE 'admins'")->rowCount() > 0;
    $admin_users_exists = $db->query("SHOW TABLES LIKE 'admin_users'")->rowCount() > 0;
    
    if ($admins_exists) {
        $table_name = 'admins';
        echo "<p class='info'>📋 Utilisation de la table: admins</p>";
    } elseif ($admin_users_exists) {
        $table_name = 'admin_users';
        echo "<p class='info'>📋 Utilisation de la table: admin_users</p>";
    } else {
        echo "<p class='error'>❌ Aucune table d'administration trouvée. Veuillez d'abord exécuter fix_tablespace_admin.php</p>";
        echo "<p class='info'>📋 <a href='fix_tablespace_admin.php' style='color:blue;'>Cliquez ici pour créer la table</a></p>";
        exit;
    }
    
    // Nouveau mot de passe: Anacim2025@
    $new_password = 'Anacim2025@';
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "<p class='info'>🔐 Nouveau mot de passe haché généré</p>";
    
    // 1. Modifier le mot de passe d'Adama Niang
    echo "<h3>👤 Modification du mot de passe d'Adama Niang</h3>";
    
    $update_adama = $db->prepare("UPDATE $table_name SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
    $result_adama = $update_adama->execute([$password_hash, 'adama.niang@anacim.sn']);
    
    if ($result_adama && $update_adama->rowCount() > 0) {
        echo "<p class='success'>✅ Mot de passe d'Adama Niang mis à jour avec succès</p>";
    } else {
        echo "<p class='error'>❌ Erreur: Utilisateur Adama Niang non trouvé ou mot de passe déjà identique</p>";
    }
    
    // 2. Ajouter Coutaille Ba comme administrateur
    echo "<h3>👤 Ajout de Coutaille Ba comme administrateur</h3>";
    
    // Vérifier si l'utilisateur existe déjà
    $check_coutaille = $db->prepare("SELECT id FROM $table_name WHERE email = ?");
    $check_coutaille->execute(['coutay.ba@anacim.sn']);
    
    if ($check_coutaille->rowCount() > 0) {
        // L'utilisateur existe, mettre à jour son mot de passe
        $update_coutaille = $db->prepare("UPDATE $table_name SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
        $result_coutaille = $update_coutaille->execute([$password_hash, 'coutay.ba@anacim.sn']);
        
        if ($result_coutaille) {
            echo "<p class='success'>✅ Mot de passe de Coutaille Ba mis à jour avec succès</p>";
        } else {
            echo "<p class='error'>❌ Erreur lors de la mise à jour du mot de passe de Coutaille Ba</p>";
        }
    } else {
        // L'utilisateur n'existe pas, le créer
        $insert_coutaille = $db->prepare("INSERT INTO $table_name (email, password, nom, prenom, role, actif) VALUES (?, ?, ?, ?, ?, ?)");
        $result_coutaille = $insert_coutaille->execute([
            'coutay.ba@anacim.sn',
            $password_hash,
            'BA',
            'Coutaille',
            'admin',
            1
        ]);
        
        if ($result_coutaille) {
            echo "<p class='success'>✅ Utilisateur Coutaille Ba créé avec succès</p>";
        } else {
            echo "<p class='error'>❌ Erreur lors de la création de l'utilisateur Coutaille Ba</p>";
        }
    }
    
    // 3. Afficher tous les utilisateurs administrateurs
    echo "<h3>📋 Liste des utilisateurs administrateurs</h3>";
    
    $all_admins = $db->query("SELECT id, email, nom, prenom, role, actif, created_at, updated_at FROM $table_name ORDER BY id");
    $admins = $all_admins->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($admins) > 0) {
        echo "<table border='1' style='border-collapse:collapse; width:100%; margin-top:10px;'>";
        echo "<tr style='background-color:#f0f0f0;'>";
        echo "<th>ID</th><th>Email</th><th>Nom</th><th>Prénom</th><th>Rôle</th><th>Actif</th><th>Créé le</th><th>Modifié le</th>";
        echo "</tr>";
        
        foreach ($admins as $admin) {
            echo "<tr>";
            echo "<td>" . $admin['id'] . "</td>";
            echo "<td>" . $admin['email'] . "</td>";
            echo "<td>" . $admin['nom'] . "</td>";
            echo "<td>" . $admin['prenom'] . "</td>";
            echo "<td>" . $admin['role'] . "</td>";
            echo "<td>" . ($admin['actif'] ? '✅ Oui' : '❌ Non') . "</td>";
            echo "<td>" . $admin['created_at'] . "</td>";
            echo "<td>" . $admin['updated_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ Aucun utilisateur administrateur trouvé</p>";
    }
    
    // 4. Test de vérification des mots de passe
    echo "<h3>🔐 Vérification des mots de passe</h3>";
    
    foreach ($admins as $admin) {
        if (password_verify($new_password, $admin['password'])) {
            echo "<p class='success'>✅ " . $admin['email'] . " - Mot de passe correct</p>";
        } else {
            echo "<p class='error'>❌ " . $admin['email'] . " - Mot de passe incorrect</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>🎉 RÉSUMÉ DES MODIFICATIONS</h3>";
    echo "<div style='background-color:#f9f9f9; padding:15px; border-left:4px solid #4CAF50;'>";
    echo "<h4>Identifiants de connexion mis à jour:</h4>";
    echo "<ul>";
    echo "<li><strong>Adama Niang:</strong> adama.niang@anacim.sn / Anacim2025@</li>";
    echo "<li><strong>Coutaille Ba:</strong> coutay.ba@anacim.sn / Anacim2025@</li>";
    echo "</ul>";
    echo "<p><strong>Note:</strong> Les deux utilisateurs peuvent maintenant se connecter avec le mot de passe <code>Anacim2025@</code></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>
