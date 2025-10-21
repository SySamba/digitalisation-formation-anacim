-- Ajouter ANACIM comme centre de formation
-- Run this in phpMyAdmin or MySQL command line

USE formation_anacim;

-- Insert ANACIM as a training center
INSERT INTO centres_formation (nom, code, adresse, actif) VALUES
('ANACIM', 'ANACIM', 'Agence Nationale de l\'Aviation Civile et de la Météorologie', TRUE);
