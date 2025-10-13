<?php
require_once __DIR__ . '/../config/database.php';

class Formation {
    private $conn;
    private $table_name = "formations";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY categorie, numero";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function readByCategorie($categorie) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE categorie = ? ORDER BY numero";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$categorie]);
        return $stmt->fetchAll();
    }

    public function readOne($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function calculerProchainRenouvellement($date_fin, $periodicite_mois) {
        // Pour les formations techniques/spécialisées, calculer la périodicité
        if ($periodicite_mois <= 120) { // Formations périodiques
            $frequence_annees = $periodicite_mois / 12;
            return date('Y-m-d', strtotime($date_fin . ' + ' . $periodicite_mois . ' months'));
        }
        return null; // Pas de renouvellement pour les formations longues
    }

    public function getFormationsExpireesOuAExpirer($jours_alerte = 90) {
        $query = "SELECT fe.*, f.intitule, f.code, f.periodicite_mois, f.categorie,
                         a.matricule, a.prenom, a.nom,
                         DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
                         DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
                  FROM formations_effectuees fe
                  JOIN formations f ON fe.formation_id = f.id
                  JOIN agents a ON fe.agent_id = a.id
                  WHERE f.categorie = 'FORMATION_TECHNIQUE'
                  AND f.periodicite_mois <= 120
                  AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                  ORDER BY prochaine_echeance ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$jours_alerte]);
        return $stmt->fetchAll();
    }
}

class FormationEffectuee {
    private $conn;
    private $table_name = "formations_effectuees";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (agent_id, formation_id, centre_formation, date_debut, date_fin, fichier_joint, statut) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        
        $result = $stmt->execute([
            $data['agent_id'],
            $data['formation_id'],
            $data['centre_formation'],
            $data['date_debut'],
            $data['date_fin'],
            $data['fichier_joint'] ?? null,
            $data['statut'] ?? 'termine'
        ]);

        // Calculer la prochaine échéance pour les formations techniques
        if ($result) {
            $this->calculerProchaineEcheance($this->conn->lastInsertId(), $data['formation_id'], $data['date_fin']);
        }

        return $result;
    }

    private function calculerProchaineEcheance($formation_effectuee_id, $formation_id, $date_fin) {
        // Récupérer les infos de la formation
        $query = "SELECT periodicite_mois, categorie FROM formations WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$formation_id]);
        $formation = $stmt->fetch();

        if ($formation && $formation['categorie'] === 'FORMATION_TECHNIQUE' && $formation['periodicite_mois'] <= 120) {
            $prochaine_echeance = date('Y-m-d', strtotime($date_fin . ' + ' . $formation['periodicite_mois'] . ' months'));
            
            $update_query = "UPDATE " . $this->table_name . " SET prochaine_echeance = ? WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->execute([$prochaine_echeance, $formation_effectuee_id]);
        }
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET centre_formation=?, date_debut=?, date_fin=?, fichier_joint=?, statut=?
                  WHERE id=?";

        $stmt = $this->conn->prepare($query);
        
        $result = $stmt->execute([
            $data['centre_formation'],
            $data['date_debut'],
            $data['date_fin'],
            $data['fichier_joint'] ?? null,
            $data['statut'] ?? 'termine',
            $id
        ]);

        // Recalculer la prochaine échéance
        if ($result) {
            $formation_query = "SELECT formation_id FROM " . $this->table_name . " WHERE id = ?";
            $formation_stmt = $this->conn->prepare($formation_query);
            $formation_stmt->execute([$id]);
            $formation_data = $formation_stmt->fetch();
            
            if ($formation_data) {
                $this->calculerProchaineEcheance($id, $formation_data['formation_id'], $data['date_fin']);
            }
        }

        return $result;
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
}

class PlanningFormation {
    private $conn;
    private $table_name = "planning_formations";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (agent_id, formation_id, date_prevue_debut, date_prevue_fin, 
                   centre_formation_prevu, statut, commentaires) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $data['agent_id'],
            $data['formation_id'],
            $data['date_prevue_debut'],
            $data['date_prevue_fin'],
            $data['centre_formation_prevu'] ?? null,
            $data['statut'] ?? 'planifie',
            $data['commentaires'] ?? null
        ]);
    }

    public function getPlanning($agent_id = null) {
        if ($agent_id) {
            $query = "SELECT pf.*, f.intitule, f.code, a.matricule, a.prenom, a.nom
                      FROM " . $this->table_name . " pf
                      JOIN formations f ON pf.formation_id = f.id
                      JOIN agents a ON pf.agent_id = a.id
                      WHERE pf.agent_id = ?
                      ORDER BY pf.date_prevue_debut ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$agent_id]);
        } else {
            $query = "SELECT pf.*, f.intitule, f.code, a.matricule, a.prenom, a.nom
                      FROM " . $this->table_name . " pf
                      JOIN formations f ON pf.formation_id = f.id
                      JOIN agents a ON pf.agent_id = a.id
                      ORDER BY pf.date_prevue_debut ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }

    public function updateStatut($id, $statut, $commentaires = null) {
        $query = "UPDATE " . $this->table_name . " SET statut = ?, commentaires = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$statut, $commentaires, $id]);
    }
}
?>
