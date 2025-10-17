-- Fix missing centres_formation table and update diplomes structure
USE formation_anacim;

-- Create centres_formation table if it doesn't exist
CREATE TABLE IF NOT EXISTS centres_formation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL UNIQUE,
    code VARCHAR(50) NOT NULL UNIQUE,
    adresse TEXT,
    telephone VARCHAR(50),
    email VARCHAR(255),
    contact_responsable VARCHAR(255),
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert training centers
INSERT IGNORE INTO centres_formation (nom, code) VALUES
('École Nationale de l\'Aviation Civile', 'ENAC'),
('École Régionale de Navigation Aérienne du Maghreb', 'ERNAM'),
('Institut de Technologie Aéronautique', 'ITAerea'),
('Institut de Formation Universitaire et de Recherche en Transport Aérien', 'IFURTA'),
('École Polytechnique de Thiès', 'EPT'),
('Institut de Formation de la Navigation et de la Pêche Continentale', 'IFNPC'),
('École de Maintenance Aéronautique et de Services', 'EMAERO');

-- Update diplomes table structure step by step
-- First check if columns exist and add them if needed
SET @sql = CONCAT('ALTER TABLE diplomes ADD COLUMN titre VARCHAR(255) NOT NULL DEFAULT "Document sans titre"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE diplomes ADD COLUMN nom_fichier_original VARCHAR(255)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE diplomes ADD COLUMN date_obtention DATE');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE diplomes ADD COLUMN etablissement VARCHAR(255)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update type_diplome enum to include new types
ALTER TABLE diplomes 
MODIFY COLUMN type_diplome ENUM('cv', 'diplome', 'attestation', 'certificat', 'autre') NOT NULL;
