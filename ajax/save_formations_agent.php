<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Debug: Log all received data
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));
error_log("Session data: " . print_r($_SESSION, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérifier si l'agent est connecté
if (!isset($_SESSION['agent_logged_in']) || $_SESSION['agent_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $agent_id = $_SESSION['agent_id'];
    
    // Récupérer les formations sélectionnées
    $formations_effectuees = $_POST['formations_effectuees'] ?? [];
    $centres_formation = $_POST['centre_formation'] ?? [];
    $dates_debut = $_POST['date_debut'] ?? [];
    $dates_fin = $_POST['date_fin'] ?? [];
    
    // Si aucune formation sélectionnée, supprimer toutes les formations existantes
    if (empty($formations_effectuees)) {
        $stmt = $pdo->prepare("DELETE FROM formations_agents WHERE agent_id = ?");
        $stmt->execute([$agent_id]);
        echo json_encode(['success' => true, 'message' => 'Formations mises à jour (aucune formation sélectionnée)']);
        exit;
    }
    
    // Supprimer toutes les formations existantes de cet agent
    $stmt = $pdo->prepare("DELETE FROM formations_agents WHERE agent_id = ?");
    $stmt->execute([$agent_id]);
    
    // Créer le dossier uploads/formations s'il n'existe pas
    $upload_dir = '../uploads/formations/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Insérer les nouvelles formations
    foreach ($formations_effectuees as $formation_id) {
        $centre = $centres_formation[$formation_id] ?? '';
        $date_debut = $dates_debut[$formation_id] ?? null;
        $date_fin = $dates_fin[$formation_id] ?? null;
        
        // Validation des champs obligatoires
        if (empty($date_debut) || empty($date_fin)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir les dates de début et fin pour toutes les formations sélectionnées']);
            exit;
        }
        
        $fichier_joint = null;
        
        // Gérer l'upload du certificat
        if (isset($_FILES['certificat']['name'][$formation_id]) && $_FILES['certificat']['error'][$formation_id] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['certificat']['tmp_name'][$formation_id];
            $file_name = $_FILES['certificat']['name'][$formation_id];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Vérifier l'extension
            $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            if (in_array($file_ext, $allowed_extensions)) {
                $new_filename = uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $fichier_joint = $new_filename;
                }
            }
        }
        
        // Calculer la prochaine échéance
        $stmt_formation = $pdo->prepare("SELECT periodicite_mois FROM formations WHERE id = ?");
        $stmt_formation->execute([$formation_id]);
        $formation_data = $stmt_formation->fetch();
        
        $prochaine_echeance = null;
        if ($formation_data && $formation_data['periodicite_mois'] > 0) {
            $prochaine_echeance = date('Y-m-d', strtotime($date_fin . ' + ' . $formation_data['periodicite_mois'] . ' months'));
        }
        
        // Insérer la formation
        $stmt = $pdo->prepare("
            INSERT INTO formations_agents 
            (agent_id, formation_id, centre_formation, date_debut, date_fin, fichier_joint, statut, prochaine_echeance, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'valide', ?, NOW())
        ");
        
        $stmt->execute([
            $agent_id,
            $formation_id,
            $centre,
            $date_debut,
            $date_fin,
            $fichier_joint,
            $prochaine_echeance
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Formations enregistrées avec succès']);
    
} catch (Exception $e) {
    error_log("Erreur sauvegarde formations: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
}
?>
