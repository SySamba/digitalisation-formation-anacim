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
    
    // Traitement des fichiers multiples avec titres
    if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        $file_count = count($_FILES['documents']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['documents']['tmp_name'][$i];
                $file_name = $_FILES['documents']['name'][$i];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Récupérer les données du formulaire
                $type_diplome = $_POST['type_diplome'][$i] ?? 'autre';
                
                // Vérifier l'extension
                if (in_array($file_ext, $allowed_extensions)) {
                    $new_filename = $type_diplome . '_' . $agent_id . '_' . uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Insérer le nouveau document avec toutes les informations
                        // Préparer les données pour l'insertion
                        $titre = isset($_POST['titre'][$i]) ? trim($_POST['titre'][$i]) : null;
                        
                        // Pour les diplômes et attestations, le titre est requis
                        if (($type_diplome === 'diplome' || $type_diplome === 'attestation') && empty($titre)) {
                            throw new Exception("Le titre est requis pour les diplômes et attestations.");
                        }
                        
                        // Vérifier si la colonne titre existe
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO diplomes (agent_id, type_diplome, titre, fichier_path, created_at) 
                                VALUES (?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $agent_id, 
                                $type_diplome,
                                $titre,
                                $new_filename
                            ]);
                        } catch (PDOException $e) {
                            // Si la colonne titre n'existe pas, utiliser l'ancienne structure
                            if (strpos($e->getMessage(), 'titre') !== false) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO diplomes (agent_id, type_diplome, fichier_path, created_at) 
                                    VALUES (?, ?, ?, NOW())
                                ");
                                $stmt->execute([
                                    $agent_id, 
                                    $type_diplome,
                                    $new_filename
                                ]);
                            } else {
                                throw $e;
                            }
                        }
                        
                        $files_uploaded[] = $type_diplome;
                    } else {
                        throw new Exception("Erreur lors de l'upload du fichier " . $type_diplome);
                    }
                } else {
                    throw new Exception("Extension non autorisée pour " . $type_diplome . ". Extensions autorisées: " . implode(', ', $allowed_extensions));
                }
            }
        }
    }
    
    if (empty($files_uploaded)) {
        echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner au moins un fichier à uploader']);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'Documents enregistrés avec succès: ' . implode(', ', $files_uploaded),
            'uploaded_files' => $files_uploaded,
            'count' => count($files_uploaded)
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur sauvegarde diplômes: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
}
?>
