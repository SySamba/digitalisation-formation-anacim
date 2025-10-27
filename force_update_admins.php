<?php
// Script pour forcer la mise à jour des administrateurs
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Mise à jour forcée des administrateurs</h2>";
    echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
    
    // Utiliser la table admin_users (confirmée existante)
    $table_name = 'admin_users';
    echo "<p class='info'>📋 Utilisation de la table: $table_name</p>";
    
    // Nouveau mot de passe: Anacim2025@
    $new_password = 'Anacim2025@';
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "<p class='info'>🔐 Nouveau hash généré pour: $new_password</p>";
    echo "<p class='info'>Hash: " . substr($password_hash, 0, 50) . "...</p>";
    
    // 1. Vérifier l'utilisateur Adama existant
    echo "<h3>👤 Vérification Adama Niang</h3>";
    $check_adama = $db->prepare("SELECT * FROM $table_name WHERE email = ?");
    $check_adama->execute(['adama.niang@anacim.sn']);
    $adama = $check_adama->fetch(PDO::FETCH_ASSOC);
    
    if ($adama) {
        echo "<p class='success'>✅ Adama trouvé - ID: " . $adama['id'] . "</p>";
        
        // Mettre à jour le mot de passe
        $update_adama = $db->prepare("UPDATE $table_name SET password = ? WHERE email = ?");
        $result = $update_adama->execute([$password_hash, 'adama.niang@anacim.sn']);
        
        if ($result) {
            echo "<p class='success'>✅ Mot de passe d'Adama mis à jour</p>";
        } else {
            echo "<p class='error'>❌ Erreur mise à jour Adama</p>";
        }
    } else {
        echo "<p class='error'>❌ Adama non trouvé</p>";
    }
    
    // 2. Vérifier et créer Coutaille Ba
    echo "<h3>👤 Création Coutaille Ba</h3>";
    $check_coutaille = $db->prepare("SELECT * FROM $table_name WHERE email = ?");
    $check_coutaille->execute(['coutay.ba@anacim.sn']);
    $coutaille = $check_coutaille->fetch(PDO::FETCH_ASSOC);
    
    if ($coutaille) {
        echo "<p class='info'>⚠️ Coutaille existe déjà - ID: " . $coutaille['id'] . "</p>";
        
        // Mettre à jour son mot de passe
        $update_coutaille = $db->prepare("UPDATE $table_name SET password = ? WHERE email = ?");
        $result = $update_coutaille->execute([$password_hash, 'coutay.ba@anacim.sn']);
        
        if ($result) {
            echo "<p class='success'>✅ Mot de passe de Coutaille mis à jour</p>";
        } else {
            echo "<p class='error'>❌ Erreur mise à jour Coutaille</p>";
        }
    } else {
        echo "<p class='info'>➕ Création de Coutaille Ba...</p>";
        
        $insert_coutaille = $db->prepare("INSERT INTO $table_name (email, password, nom, prenom, role, actif) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $insert_coutaille->execute([
            'coutay.ba@anacim.sn',
            $password_hash,
            'BA',
            'Coutaille',
            'admin',
            1
        ]);
        
        if ($result) {
            echo "<p class='success'>✅ Coutaille Ba créé avec succès</p>";
        } else {
            echo "<p class='error'>❌ Erreur création Coutaille</p>";
            print_r($insert_coutaille->errorInfo());
        }
    }
    
    // 3. Vérification finale - Lister tous les utilisateurs
    echo "<h3>📋 Vérification finale</h3>";
    $all_users = $db->query("SELECT * FROM $table_name ORDER BY id");
    $users = $all_users->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Email</th><th>Nom</th><th>Prénom</th><th>Rôle</th><th>Test Mot de Passe</th></tr>";
    
    foreach ($users as $user) {
        $password_ok = password_verify($new_password, $user['password']);
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
    
    // 4. Test de connexion simulé
    echo "<h3>🔐 Test de connexion</h3>";
    
    // Test Adama
    $test_adama = $db->prepare("SELECT * FROM $table_name WHERE email = ?");
    $test_adama->execute(['adama.niang@anacim.sn']);
    $adama_final = $test_adama->fetch(PDO::FETCH_ASSOC);
    
    if ($adama_final && password_verify($new_password, $adama_final['password'])) {
        echo "<p class='success'>✅ Adama: adama.niang@anacim.sn / Anacim2025@ - CONNEXION OK</p>";
    } else {
        echo "<p class='error'>❌ Adama: CONNEXION ÉCHOUÉE</p>";
    }
    
    // Test Coutaille
    $test_coutaille = $db->prepare("SELECT * FROM $table_name WHERE email = ?");
    $test_coutaille->execute(['coutay.ba@anacim.sn']);
    $coutaille_final = $test_coutaille->fetch(PDO::FETCH_ASSOC);
    
    if ($coutaille_final && password_verify($new_password, $coutaille_final['password'])) {
        echo "<p class='success'>✅ Coutaille: coutay.ba@anacim.sn / Anacim2025@ - CONNEXION OK</p>";
    } else {
        echo "<p class='error'>❌ Coutaille: CONNEXION ÉCHOUÉE</p>";
    }
    
    echo "<hr>";
    echo "<div style='background:#e8f5e8;padding:15px;border-radius:5px;'>";
    echo "<h3>🎉 IDENTIFIANTS FINAUX</h3>";
    echo "<ul>";
    echo "<li><strong>Adama Niang:</strong> adama.niang@anacim.sn / Anacim2025@</li>";
    echo "<li><strong>Coutaille Ba:</strong> coutay.ba@anacim.sn / Anacim2025@</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>
