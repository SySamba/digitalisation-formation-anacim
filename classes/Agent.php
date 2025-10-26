<?php
require_once __DIR__ . '/../config/database.php';

class Agent {
    private $conn;
    private $table_name = "agents";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (matricule, prenom, nom, date_recrutement, structure_attache, 
                   domaine_activites, specialite, grade, date_nomination, 
                   numero_badge, date_validite_badge, date_prestation_serment, photo) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $data['matricule'],
            $data['prenom'],
            $data['nom'],
            $data['date_recrutement'],
            $data['structure_attache'] ?? null,
            $data['domaine_activites'] ?? null,
            $data['specialite'] ?? null,
            $data['grade'],
            $data['date_nomination'] ?? null,
            $data['numero_badge'] ?? null,
            $data['date_validite_badge'] ?? null,
            $data['date_prestation_serment'] ?? null,
            $data['photo'] ?? null
        ]);
    }

    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY nom, prenom";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function readOne($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET matricule=?, prenom=?, nom=?, date_recrutement=?, 
                      structure_attache=?, domaine_activites=?, specialite=?, 
                      grade=?, date_nomination=?, numero_badge=?, 
                      date_validite_badge=?, date_prestation_serment=?, photo=?
                  WHERE id=?";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $data['matricule'],
            $data['prenom'],
            $data['nom'],
            $data['date_recrutement'],
            $data['structure_attache'] ?? null,
            $data['domaine_activites'] ?? null,
            $data['specialite'] ?? null,
            $data['grade'],
            $data['date_nomination'] ?? null,
            $data['numero_badge'] ?? null,
            $data['date_validite_badge'] ?? null,
            $data['date_prestation_serment'] ?? null,
            $data['photo'] ?? null,
            $id
        ]);
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }

    public function getFormationsEffectuees($agent_id) {
        $query = "SELECT fe.*, f.intitule, f.code, f.periodicite_mois, f.categorie
                  FROM formations_effectuees fe
                  JOIN formations f ON fe.formation_id = f.id
                  WHERE fe.agent_id = ?
                  ORDER BY fe.date_fin DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$agent_id]);
        return $stmt->fetchAll();
    }

    public function getFormationsARenouveler($agent_id) {
        $query = "SELECT fe.*, f.intitule, f.code, f.periodicite_mois, f.categorie,
                         DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
                         DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
                  FROM formations_effectuees fe
                  JOIN formations f ON fe.formation_id = f.id
                  WHERE fe.agent_id = ? 
                  AND f.categorie = 'FORMATION_TECHNIQUE'
                  AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                  ORDER BY prochaine_echeance ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$agent_id]);
        return $stmt->fetchAll();
    }

    public function getDiplomesAcademiques($agent_id) {
        $query = "SELECT * FROM diplomes_academiques WHERE agent_id = ? ORDER BY date_obtention DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$agent_id]);
        return $stmt->fetchAll();
    }

    public function getFichiersAgent($agent_id) {
        $query = "SELECT * FROM fichiers_agents WHERE agent_id = ? ORDER BY date_upload DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$agent_id]);
        return $stmt->fetchAll();
    }
}
?>
