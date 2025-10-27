-- Script pour ajouter les nouvelles formations SUR-FTS
-- SUR-FTS-20: Aviation Cybersecurity Training
-- SUR-FTS-21: Gouvernance Cybersecurite

-- Ajouter les deux nouvelles formations techniques
INSERT INTO formations (numero, intitule, code, ressource, periodicite_mois, categorie) VALUES
('3.22', 'Aviation Cybersecurity Training', 'SUR-FTS-20', 'Externe', 40, 'FORMATION_TECHNIQUE'),
('3.8', 'Gouvernance Cybersecurite', 'SUR-FTS-21', 'Externe/Interne', 36, 'FORMATION_TECHNIQUE');

-- Vérification des nouvelles formations ajoutées
SELECT 
    id, 
    numero, 
    intitule, 
    code, 
    ressource, 
    periodicite_mois, 
    categorie 
FROM formations 
WHERE code IN ('SUR-FTS-20', 'SUR-FTS-21')
ORDER BY code;

-- Vérifier le total des formations SUR-FTS
SELECT 
    COUNT(*) as total_fts_formations
FROM formations 
WHERE code LIKE 'SUR-FTS-%';

-- Afficher toutes les formations SUR-FTS pour vérification
SELECT 
    id, 
    numero, 
    intitule, 
    code, 
    ressource, 
    periodicite_mois
FROM formations 
WHERE code LIKE 'SUR-FTS-%'
ORDER BY CAST(SUBSTRING(code, 8) AS UNSIGNED);
