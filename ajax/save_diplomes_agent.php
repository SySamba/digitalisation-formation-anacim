<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

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
    
    // Créer le dossier uploads/diplomes s'il n'existe pas
    $upload_dir = '../uploads/diplomes/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $files_uploaded = [];
    $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    
    // Types de documents à traiter
    $document_types = ['cv', 'diplome', 'attestation'];
    
    foreach ($document_types as $type) {
        if (isset($_FILES[$type]) && $_FILES[$type]['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES[$type]['tmp_name'];
            $file_name = $_FILES[$type]['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Vérifier l'extension
            if (in_array($file_ext, $allowed_extensions)) {
                $new_filename = $type . '_' . $agent_id . '_' . uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Supprimer l'ancien fichier du même type s'il existe
                    $stmt = $pdo->prepare("SELECT fichier_path FROM diplomes WHERE agent_id = ? AND type_diplome = ?");
                    $stmt->execute([$agent_id, $type]);
                    $old_file = $stmt->fetch();
                    
                    if ($old_file && file_exists($upload_dir . $old_file['fichier_path'])) {
                        unlink($upload_dir . $old_file['fichier_path']);
                    }
                    
                    // Supprimer l'ancien enregistrement
                    $stmt = $pdo->prepare("DELETE FROM diplomes WHERE agent_id = ? AND type_diplome = ?");
                    $stmt->execute([$agent_id, $type]);
                    
                    // Insérer le nouveau document
                    $stmt = $pdo->prepare("
                        INSERT INTO diplomes (agent_id, type_diplome, fichier_path, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([$agent_id, $type, $new_filename]);
                    $files_uploaded[] = $type;
                } else {
                    throw new Exception("Erreur lors de l'upload du fichier " . $type);
                }
            } else {
                throw new Exception("Extension non autorisée pour " . $type . ". Extensions autorisées: " . implode(', ', $allowed_extensions));
            }
        }
    }
    
    if (empty($files_uploaded)) {
        echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner au moins un fichier à uploader']);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'Documents enregistrés avec succès: ' . implode(', ', $files_uploaded),
            'uploaded_files' => $files_uploaded
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur sauvegarde diplômes: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
}
?>
