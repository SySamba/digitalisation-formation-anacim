<?php
// Script pour exécuter directement les requêtes SQL de mise à jour
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Exécution directe des requêtes SQL</h2>";
    echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
    
    // Hash pour le mot de passe Anacim2025@
    $password_hash = password_hash('Anacim2025@', PASSWORD_DEFAULT);
    echo "<p class='info'>🔐 Hash généré pour Anacim2025@: " . substr($password_hash, 0, 50) . "...</p>";
    
    // 1. Mettre à jour Adama Niang
    echo "<h3>👤 Mise à jour Adama Niang</h3>";
    $sql1 = "UPDATE admin_users SET password = ?, updated_at = NOW() WHERE email = 'adama.niang@anacim.sn'";
    $stmt1 = $db->prepare($sql1);
    $result1 = $stmt1->execute([$password_hash]);
    
    if ($result1) {
        $rows1 = $stmt1->rowCount();
        echo "<p class='success'>✅ Adama mis à jour ($rows1 ligne(s) affectée(s))</p>";
    } else {
        echo "<p class='error'>❌ Erreur mise à jour Adama</p>";
    }
    
    // 2. Créer/Mettre à jour Coutaille Ba
    echo "<h3>👤 Création/Mise à jour Coutaille Ba</h3>";
    
    // D'abord vérifier s'il existe
    $check = $db->prepare("SELECT id FROM admin_users WHERE email = 'coutay.ba@anacim.sn'");
    $check->execute();
    
    if ($check->rowCount() > 0) {
        // Il existe, le mettre à jour
        $sql2 = "UPDATE admin_users SET password = ?, updated_at = NOW() WHERE email = 'coutay.ba@anacim.sn'";
        $stmt2 = $db->prepare($sql2);
        $result2 = $stmt2->execute([$password_hash]);
        echo "<p class='info'>⚠️ Coutaille existait déjà, mot de passe mis à jour</p>";
    } else {
        // Il n'existe pas, le créer
        $sql2 = "INSERT INTO admin_users (email, password, nom, prenom, role, actif, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt2 = $db->prepare($sql2);
        $result2 = $stmt2->execute(['coutay.ba@anacim.sn', $password_hash, 'BA', 'Coutaille', 'admin', 1]);
        echo "<p class='success'>✅ Coutaille créé</p>";
    }
    
    if (!$result2) {
        echo "<p class='error'>❌ Erreur avec Coutaille</p>";
    }
    
    // 3. Vérification finale
    echo "<h3>📋 Vérification finale</h3>";
    $verify = $db->query("SELECT id, email, nom, prenom, role, actif FROM admin_users ORDER BY id");
    $users = $verify->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Email</th><th>Nom</th><th>Prénom</th><th>Rôle</th><th>Test Connexion</th></tr>";
    
    foreach ($users as $user) {
        // Récupérer le hash pour tester
        $test_stmt = $db->prepare("SELECT password FROM admin_users WHERE id = ?");
        $test_stmt->execute([$user['id']]);
        $hash = $test_stmt->fetchColumn();
        
        $password_ok = password_verify('Anacim2025@', $hash);
        
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['nom'] . "</td>";
        echo "<td>" . $user['prenom'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . ($password_ok ? "<span class='success'>✅ Anacim2025@</span>" : "<span class='error'>❌ Ancien MDP</span>") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. Instructions finales
    echo "<hr>";
    echo "<div style='background:#e8f5e8;padding:15px;border-radius:5px;'>";
    echo "<h3>🎉 MISE À JOUR TERMINÉE</h3>";
    echo "<p><strong>Identifiants de connexion :</strong></p>";
    echo "<ul>";
    echo "<li><strong>Adama Niang :</strong> adama.niang@anacim.sn / Anacim2025@</li>";
    echo "<li><strong>Coutaille Ba :</strong> coutay.ba@anacim.sn / Anacim2025@</li>";
    echo "</ul>";
    echo "<p>Vous pouvez maintenant vous connecter avec ces identifiants.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur fatale: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>
