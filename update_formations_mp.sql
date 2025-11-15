-- Requête SQL pour modifier les intitulés des formations FORMATION INITIALE (SUR-INI)
-- Objectif : Remplacer les noms se terminant par "-MP" par des noms commençant par "MP-"
-- Date de création : 14 novembre 2024

-- Vérification avant modification (optionnel - pour voir l'état actuel)
SELECT code, intitule, categorie 
FROM formations 
WHERE code IN ('SUR-INI-15', 'SUR-INI-16', 'SUR-INI-17', 'SUR-INI-18', 'SUR-INI-19', 'SUR-INI-20', 'SUR-INI-21', 'SUR-INI-22', 'SUR-INI-23', 'SUR-INI-24')
AND categorie = 'FORMATION_INITIALE'
ORDER BY code;

-- Requête de modification principale - Ajout du préfixe "MP-" aux noms
UPDATE formations 
SET intitule = CASE 
    WHEN code = 'SUR-INI-15' THEN 'MP-Gestion de Crise'
    WHEN code = 'SUR-INI-16' THEN 'MP-Sûreté du Fret et de la Poste'
    WHEN code = 'SUR-INI-17' THEN 'MP-Imagerie Radioscopique'
    WHEN code = 'SUR-INI-18' THEN 'MP-Programme National de Formation en Sûreté de l''Aviation Civile (PNFSAC)'
    WHEN code = 'SUR-INI-19' THEN 'MP-Maintenance des équipements Sûreté'
    WHEN code = 'SUR-INI-20' THEN 'MP-Superviseur d''aéroport'
    WHEN code = 'SUR-INI-21' THEN 'MP-Instructeur en Sûreté de l''aviation'
    WHEN code = 'SUR-INI-22' THEN 'MP-Base Sûreté'
    WHEN code = 'SUR-INI-23' THEN 'MP-Marchandises dangereuses'
    WHEN code = 'SUR-INI-24' THEN 'MP-Gestion des risques en Sûreté'
END
WHERE code IN ('SUR-INI-15', 'SUR-INI-16', 'SUR-INI-17', 'SUR-INI-18', 'SUR-INI-19', 'SUR-INI-20', 'SUR-INI-21', 'SUR-INI-22', 'SUR-INI-23', 'SUR-INI-24')
AND categorie = 'FORMATION_INITIALE';

-- Vérification après modification (optionnel - pour confirmer les changements)
SELECT code, intitule, categorie 
FROM formations 
WHERE code IN ('SUR-INI-15', 'SUR-INI-16', 'SUR-INI-17', 'SUR-INI-18', 'SUR-INI-19', 'SUR-INI-20', 'SUR-INI-21', 'SUR-INI-22', 'SUR-INI-23', 'SUR-INI-24')
AND categorie = 'FORMATION_INITIALE'
ORDER BY code;

-- Affichage du nombre de lignes modifiées
SELECT ROW_COUNT() as 'Nombre de formations modifiées';
