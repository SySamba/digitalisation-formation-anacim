-- Script pour réinsérer toutes les formations
-- Exécutez ce script si certaines formations manquent dans votre base de données

-- D'abord, sauvegarder les formations_agents existantes
CREATE TEMPORARY TABLE IF NOT EXISTS temp_formations_agents AS 
SELECT * FROM formations_agents;

-- Vider la table formations (attention: cela supprime toutes les formations)
-- TRUNCATE TABLE formations;

-- OU mieux: supprimer uniquement les formations existantes et réinsérer
DELETE FROM formations;

-- Réinitialiser l'auto-increment
ALTER TABLE formations AUTO_INCREMENT = 1;

-- Réinsérer TOUTES les formations
INSERT INTO formations (numero, intitule, code, ressource, periodicite_mois, categorie) VALUES
-- FAMILIARISATION (2 formations)
('0.1', 'Familiarisation avec l\'entreprise (ANACIM)', 'SUR-FAM-01', 'Interne', 564, 'FAMILIARISATION'),
('0.2', 'Familiarisation avec la Direction (DSF)', 'SUR-FAM-02', 'Interne', 564, 'FAMILIARISATION'),

-- FORMATION INITIALE (16 formations)
('1.1', 'Introduction à la Sûreté et à la Facilitation', 'SUR-INI-01', 'Interne', 564, 'FORMATION_INITIALE'),
('1.2', 'BASE/Sensibilisation', 'SUR-INI-02', 'Interne', 24, 'FORMATION_INITIALE'),
('1.3', 'Règlementation - Protection physique des Aéroports', 'SUR-INI-03', 'Interne', 36, 'FORMATION_INITIALE'),
('1.4', 'Règlementation - contrôle d\'accès et autres mesures applicables au personnel d\'aéroport', 'SUR-INI-04', 'Interne', 36, 'FORMATION_INITIALE'),
('1.5', 'Règlementation - Inspection-filtrage des passagers et des bagages de cabines', 'SUR-INI-05', 'Interne', 36, 'FORMATION_INITIALE'),
('1.6', 'Règlementation - Inspection-filtrage des bagages de soute', 'SUR-INI-06', 'Interne', 36, 'FORMATION_INITIALE'),
('1.7', 'Règlementation - Mesures applicables au fret et à la poste', 'SUR-INI-07', 'Interne', 36, 'FORMATION_INITIALE'),
('1.8', 'Règlementation - Mesures applicables aux approvisionnement de bord et fournitures d\'aéroport', 'SUR-INI-08', 'Interne', 36, 'FORMATION_INITIALE'),
('1.9', 'Règlementation - Mesures applicables à la sûreté des aéronefs', 'SUR-INI-09', 'Interne', 36, 'FORMATION_INITIALE'),
('1.10', 'Réglementation - Mesures applicables à l\'exploitation des équipements de sûreté', 'SUR-INI-110', 'Interne', 36, 'FORMATION_INITIALE'),
('1.11', 'Règlementation - Mesures de sûreté applicables au "coté ville"', 'SUR-INI-11', 'Interne', 36, 'FORMATION_INITIALE'),
('1.12', 'Règlementation - Dispositions relatives à la formation et à la certification du personnel de sûreté', 'SUR-INI-12', 'Interne', 36, 'FORMATION_INITIALE'),
('1.13', 'Contrôle Qualité - MPN "Inspecteur national" et dispositions nationales', 'SUR-INI-14', 'Interne/Externe', 36, 'FORMATION_INITIALE'),
('1.14', 'Réglementation - Mesures applicables à l\'exploitation des équipements de sûreté', 'SUR-INI-15', 'Interne', 36, 'FORMATION_INITIALE'),
('1.15', 'Introduction à l\'analyse comportementale', 'SUR-INI-13', 'Interne/Externe', 36, 'FORMATION_INITIALE'),

-- FORMATION EN COURS D'EMPLOI (12 formations)
('2.1', 'Inspection - Protection physique des Aéroports', 'SUR-FCE-01', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.2', 'Inspection - Mesures applicables aux personnes autres que les passagers', 'SUR-FCE-02', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.3', 'Inspection - Mesures applicables à l\'inspection-filtrage des passagers', 'SUR-FCE-03', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.4', 'Inspection - Mesures applicables à Inspection-filtrage des bagages de soute', 'SUR-FCE-04', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.5', 'Inspection - Mesures applicables à la sureté du fret et de la poste', 'SUR-FCE-05', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.6', 'Inspection- Mesures applicables aux approvisionnements de bord et fournitures d\'aéroport', 'SUR-FCE-06', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.7', 'Inspection - Mesures applicables aux contrôles de sûreté des véhicules', 'SUR-FCE-07', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.8', 'Inspection - Mesures applicables à la sûreté des aéronefs', 'SUR-FCE-08', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.9', 'Inspection - Mesures applicables à l\'exploitation des équipements', 'SUR-FCE-09', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.10', 'Inspection - Mesures applicables à la sûreté coté ville', 'SUR-FCE-10', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.11', 'Inspection - Mesures applicables à la formation et certification du personnel', 'SUR-FCE-11', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),
('2.12', 'Test en situation opérationnel', 'SUR-FCE-12', 'Interne', 564, 'FORMATION_COURS_EMPLOI'),

-- FORMATION TECHNIQUE/SPECIALISEE (20 formations)
('3.1', 'Processus d\'inspection-filtrage des passagers et bagages de cabine', 'SUR-FTS-01', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.2', 'Processus d\'inspection-filtrage des bagages de soute', 'SUR-FTS-02', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.3', 'Techniques de contrôle des véhicules', 'SUR-FTS-03', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.4', 'Processus du contrôle de sûreté du fret et de la poste', 'SUR-FTS-04', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.5', 'Processus du contrôle de sûreté des approvisionnements de bord et fournitures d\'aéroports', 'SUR-FTS-05', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.6', 'Procédures d\'inspection filtrage des personnes (autres que les passagers), des bagages et des objets transportés', 'SUR-FTS-06', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.7', 'Sureté des aéronefs/sûreté aérienne', 'SUR-FTS-07', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.9', 'Imagerie radioscopique', 'SUR-FTS-09', 'Externe', 36, 'FORMATION_TECHNIQUE'),
('3.10', 'Exploitation des équipements de sûreté', 'SUR-FTS-10', 'Externe', 36, 'FORMATION_TECHNIQUE'),
('3.11', 'Utilisation des Chiens détecteurs d\'explosifs', 'SUR-FTS-11', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.12', 'Evaluation/Gestion du risque', 'SUR-FTS-12', 'Interne/Externe', 48, 'FORMATION_TECHNIQUE'),
('3.13', 'Instructeur en sûreté (MPN)', 'SUR-FTS-13', 'Interne/Externe', 120, 'FORMATION_TECHNIQUE'),
('3.14', 'Techniques d\'instructions - Trainair (TIC)', 'SUR-FTS-14', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.15', 'Développement/conception de cours/supports pédagogiques', 'SUR-FTS-15', 'Externe', 120, 'FORMATION_TECHNIQUE'),
('3.16', 'Evaluation du comportement des personnes (ECP) en milieu aéroportuaire', 'SUR-FTS-16', 'Externe', 36, 'FORMATION_TECHNIQUE'),
('3.17', 'Evaluation d\'un centre de formation (TMC)', 'SUR-FTS-17', 'Externe', 36, 'FORMATION_TECHNIQUE'),
('3.18', 'Facilitation - MPN OACI', 'SUR-FTS-18', 'Interne/Externe', 60, 'FORMATION_TECHNIQUE'),
('3.19', 'Marchandises dangereuses', 'SUR-FTS-19', 'Interne/Externe', 60, 'FORMATION_TECHNIQUE'),
('3.8', 'Détection d''explosifs et d''armes', 'SUR-FTS-08', 'Externe', 60, 'FORMATION_TECHNIQUE'),
('3.8', 'Gouvernance Cybersecurite', 'SUR-FTS-21', 'Externe/Interne', 36, 'FORMATION_TECHNIQUE'),
('3.22', 'Aviation Cybersecurity Training', 'SUR-FTS-20', 'Externe', 40, 'FORMATION_TECHNIQUE');

-- Vérification
SELECT 
    categorie,
    COUNT(*) as nombre
FROM formations
GROUP BY categorie;

-- Afficher le total
SELECT COUNT(*) as total_formations FROM formations;
