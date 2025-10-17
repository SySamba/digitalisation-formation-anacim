-- Base de données pour le système de gestion des formations ANACIM
CREATE DATABASE IF NOT EXISTS formation_anacim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE formation_anacim;

-- Table des formations disponibles
CREATE TABLE formations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) NOT NULL,
    intitule VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    ressource ENUM('Interne', 'Externe', 'Interne/Externe') NOT NULL,
    periodicite_mois INT NOT NULL,
    categorie ENUM('FAMILIARISATION', 'FORMATION_INITIALE', 'FORMATION_COURS_EMPLOI', 'FORMATION_TECHNIQUE') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des agents/inspecteurs
CREATE TABLE agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(50) NOT NULL UNIQUE,
    prenom VARCHAR(100) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    date_recrutement DATE NULL,
    structure_attache VARCHAR(255),
    domaine_activites VARCHAR(255),
    specialiste VARCHAR(255),
    grade ENUM('cadre_technique', 'agent_technique', 'inspecteur_stagiaire', 'inspecteur_titulaire', 'inspecteur_principal') NULL,
    -- Champs spécifiques pour inspecteur titulaire
    date_nomination DATE NULL,
    numero_badge VARCHAR(50) NULL,
    date_validite_badge DATE NULL,
    date_prestation_serment DATE NULL,
    photo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des diplômes académiques
CREATE TABLE diplomes_academiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    type_diplome VARCHAR(100) NOT NULL,
    etablissement VARCHAR(255),
    date_obtention DATE,
    fichier_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Table des diplômes (CV, diplômes, attestations) - Version améliorée
CREATE TABLE diplomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    type_diplome ENUM('cv', 'diplome', 'attestation', 'certificat', 'autre') NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    fichier_path VARCHAR(255) NOT NULL,
    nom_fichier_original VARCHAR(255),
    date_obtention DATE,
    etablissement VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Table des formations effectuées
CREATE TABLE formations_effectuees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    formation_id INT NOT NULL,
    centre_formation VARCHAR(255),
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    fichier_joint VARCHAR(255),
    statut ENUM('en_cours', 'termine', 'valide') DEFAULT 'termine',
    prochaine_echeance DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);

-- Table des formations agents (nouvelle structure)
CREATE TABLE formations_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    formation_id INT NOT NULL,
    centre_formation VARCHAR(255),
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    fichier_joint VARCHAR(255),
    statut ENUM('en_cours', 'termine', 'valide') DEFAULT 'valide',
    prochaine_echeance DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);

-- Table des formations non effectuées (besoins identifiés)
CREATE TABLE formations_non_effectuees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    formation_id INT NOT NULL,
    priorite ENUM('haute', 'moyenne', 'basse') DEFAULT 'moyenne',
    raison VARCHAR(255),
    date_identification DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);

-- Table du planning prévu
CREATE TABLE planning_formations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    formation_id INT NOT NULL,
    date_prevue_debut DATE NOT NULL,
    date_prevue_fin DATE NOT NULL,
    centre_formation_prevu VARCHAR(255),
    statut ENUM('planifie', 'confirme', 'reporte', 'annule') DEFAULT 'planifie',
    commentaires TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);

-- Table des centres de formation
CREATE TABLE centres_formation (
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

-- Table des fichiers agents (documents divers)
CREATE TABLE fichiers_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    type_fichier VARCHAR(100),
    chemin_fichier VARCHAR(255) NOT NULL,
    taille_fichier INT,
    date_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Insertion des formations selon votre liste
INSERT INTO formations (numero, intitule, code, ressource, periodicite_mois, categorie) VALUES
-- FAMILIARISATION
('0.1', 'Familiarisation avec l\'entreprise (ANACIM)', 'SUR-FAM-01', 'Interne', 564, 'FAMILIARISATION'),
('0.2', 'Familiarisation avec la Direction (DSF)', 'SUR-FAM-02', 'Interne', 564, 'FAMILIARISATION'),

-- FORMATION INITIALE
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

-- FORMATION EN COURS D'EMPLOI
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

-- FORMATION TECHNIQUE/SPECIALISEE
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
('3.21', 'Introduction à l\'analyse comportementale', 'SUR-INI-13', 'Interne/Externe', 36, 'FORMATION_TECHNIQUE');

-- Insertion des centres de formation
INSERT INTO centres_formation (nom, code) VALUES
('École Nationale de l\'Aviation Civile', 'ENAC'),
('École Régionale de Navigation Aérienne du Maghreb', 'ERNAM'),
('Institut de Technologie Aéronautique', 'ITAerea'),
('Institut de Formation Universitaire et de Recherche en Transport Aérien', 'IFURTA'),
('École Polytechnique de Thiès', 'EPT'),
('Institut de Formation de la Navigation et de la Pêche Continentale', 'IFNPC'),
('École de Maintenance Aéronautique et de Services', 'EMAERO');
