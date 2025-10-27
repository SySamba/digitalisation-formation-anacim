<?php
// Script pour générer le hash du mot de passe admin
$password = 'Anacim2025';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Mot de passe: $password\n";
echo "Hash généré: $hash\n";

// Connexion à la base de données pour insérer directement l'admin
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // S'assurer qu'on utilise la bonne base de données
    $db->exec("USE formation_anacim");
    
    // Créer la table admins si elle n'existe pas
    $create_table = "CREATE TABLE IF NOT EXISTS admins (
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
    
    $db->exec($create_table);
    echo "✅ Table 'admins' créée avec succès\n";
    
    // Vérifier si l'utilisateur existe déjà
    $check_user = $db->prepare("SELECT id FROM admins WHERE email = ?");
    $check_user->execute(['adama.niang@anacim.sn']);
    
    if ($check_user->rowCount() > 0) {
        // Mettre à jour le mot de passe
        $update_user = $db->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE email = ?");
        $update_user->execute([$hash, 'adama.niang@anacim.sn']);
        echo "✅ Mot de passe mis à jour pour adama.niang@anacim.sn\n";
    } else {
        // Insérer le nouvel utilisateur
        $insert_user = $db->prepare("INSERT INTO admins (email, password, nom, prenom, role) VALUES (?, ?, ?, ?, ?)");
        $insert_user->execute(['adama.niang@anacim.sn', $hash, 'NIANG', 'Adama', 'super_admin']);
        echo "✅ Utilisateur admin créé: adama.niang@anacim.sn\n";
    }
    
    // Vérifier la création
    $verify = $db->prepare("SELECT * FROM admins WHERE email = ?");
    $verify->execute(['adama.niang@anacim.sn']);
    $admin = $verify->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "✅ Vérification réussie:\n";
        echo "   - ID: " . $admin['id'] . "\n";
        echo "   - Email: " . $admin['email'] . "\n";
        echo "   - Nom: " . $admin['prenom'] . " " . $admin['nom'] . "\n";
        echo "   - Rôle: " . $admin['role'] . "\n";
        echo "   - Actif: " . ($admin['actif'] ? 'Oui' : 'Non') . "\n";
        
        // Test de vérification du mot de passe
        if (password_verify('Anacim2025', $admin['password'])) {
            echo "✅ Vérification du mot de passe: SUCCÈS\n";
        } else {
            echo "❌ Vérification du mot de passe: ÉCHEC\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
