-- Requête SQL pour ajouter les nouvelles formations FORMATION INITIALE (SUR-INI)
-- À exécuter dans la base de données formation_anacim

USE formation_anacim;

-- Insertion des nouvelles formations FORMATION INITIALE (1.15 à 1.24)
INSERT INTO formations (numero, intitule, code, ressource, periodicite_mois, categorie) VALUES
('1.15', 'Gestion de Crise -MP', 'SUR-INI-25', 'Interne/Externe', 36, 'FORMATION_INITIALE'),
('1.16', 'Sûreté du Fret et de la Poste -MP', 'SUR-INI-16', 'Interne/Externe', 36, 'FORMATION_INITIALE'),
('1.17', 'Imagerie Radioscopique -MP', 'SUR-INI-17', 'Externe', 36, 'FORMATION_INITIALE'),
('1.18', 'Programme National de Formation en Sûreté de l\'Aviation Civile (PNFSAC) -MP', 'SUR-INI-18', 'Interne/Externe', 36, 'FORMATION_INITIALE'),
('1.19', 'Maintenance des équipements Sûreté -MP', 'SUR-INI-19', 'Externe', 36, 'FORMATION_INITIALE'),
('1.20', 'Superviseur d\'aéroport -MP', 'SUR-INI-20', 'Interne/Externe', 36, 'FORMATION_INITIALE'),
('1.21', 'Instructeur en Sûreté de l\'aviation -MP', 'SUR-INI-21', 'Interne/Externe', 36, 'FORMATION_INITIALE'),
('1.22', 'Base Sûreté -MP', 'SUR-INI-22', 'Interne/Externe', 36, 'FORMATION_INITIALE'),
('1.23', 'Marchandises dangereuses -MP', 'SUR-INI-23', 'Interne/Externe', 36, 'FORMATION_INITIALE'),
('1.24', 'Gestion des risques en Sûreté -MP', 'SUR-INI-24', 'Interne/Externe', 36, 'FORMATION_INITIALE');

-- Vérification des insertions
SELECT COUNT(*) as 'Nombre total de formations FORMATION_INITIALE' 
FROM formations 
WHERE categorie = 'FORMATION_INITIALE';

-- Affichage des nouvelles formations ajoutées
SELECT numero, intitule, code, ressource 
FROM formations 
WHERE code IN ('SUR-INI-25', 'SUR-INI-16', 'SUR-INI-17', 'SUR-INI-18', 'SUR-INI-19', 'SUR-INI-20', 'SUR-INI-21', 'SUR-INI-22', 'SUR-INI-23', 'SUR-INI-24')
ORDER BY numero;
