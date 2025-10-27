-- Utiliser la bonne base de données
USE formation_anacim;

-- Création de la table des administrateurs
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de l'utilisateur administrateur
INSERT INTO admins (email, password, nom, prenom, role) VALUES 
('adama.niang@anacim.sn', '$2y$10$7HPltx8zFCSSOzgUsD/w6O2YBG7ZjPMsUQQcruyaQ/tjexsb/byii', 'NIANG', 'Adama', 'super_admin');

-- Note: Le mot de passe haché correspond à "Anacim2025"
