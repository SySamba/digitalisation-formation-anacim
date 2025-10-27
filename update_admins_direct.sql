-- Script SQL direct pour modifier les mots de passe des administrateurs
-- Mot de passe: Anacim2025@
-- Hash généré avec PHP: password_hash('Anacim2025@', PASSWORD_DEFAULT)

USE formation_anacim;

-- 1. Mettre à jour le mot de passe d'Adama Niang
UPDATE admin_users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    updated_at = NOW() 
WHERE email = 'adama.niang@anacim.sn';

-- 2. Insérer Coutaille Ba (ou mettre à jour s'il existe)
INSERT INTO admin_users (email, password, nom, prenom, role, actif, created_at, updated_at) 
VALUES ('coutay.ba@anacim.sn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BA', 'Coutaille', 'admin', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
updated_at = NOW();

-- 3. Vérifier les utilisateurs créés
SELECT id, email, nom, prenom, role, actif, created_at, updated_at 
FROM admin_users 
ORDER BY id;
