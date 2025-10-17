-- Simple fix for diplomes table structure
USE formation_anacim;

-- Add missing columns to diplomes table
ALTER TABLE diplomes ADD COLUMN titre VARCHAR(255) NOT NULL DEFAULT 'Document sans titre';
ALTER TABLE diplomes ADD COLUMN nom_fichier_original VARCHAR(255);
ALTER TABLE diplomes ADD COLUMN date_obtention DATE;
ALTER TABLE diplomes ADD COLUMN etablissement VARCHAR(255);

-- Update type_diplome enum
ALTER TABLE diplomes MODIFY COLUMN type_diplome ENUM('cv', 'diplome', 'attestation', 'certificat', 'autre') NOT NULL;
