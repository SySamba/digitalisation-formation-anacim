-- Créer les tables manquantes pour le système de formation ANACIM

-- Table des diplômes (CV, diplômes, attestations)
CREATE TABLE IF NOT EXISTS diplomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    type_diplome ENUM('cv', 'diplome', 'attestation') NOT NULL,
    fichier_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Table des formations agents (nouvelle structure)
CREATE TABLE IF NOT EXISTS formations_agents (
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
