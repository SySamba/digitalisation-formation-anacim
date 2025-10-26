<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Agent.php';
require_once __DIR__ . '/../includes/functions.php';

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
    $db = $database->getConnection();
    $agent = new Agent($db);

    $agent_id = $_SESSION['agent_id'];
    
    // Récupérer les données actuelles
    $current_agent = $agent->readOne($agent_id);
    if (!$current_agent) {
        throw new Exception('Agent non trouvé.');
    }

    // Gestion de l'upload de photo
    $photo_filename = $current_agent['photo'];
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/photos/';
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['photo']['name']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            throw new Exception('Format de photo non autorisé. Utilisez JPG, PNG ou GIF.');
        }
        
        // Vérifier la taille du fichier (max 2MB)
        if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            throw new Exception('La photo est trop volumineuse. Taille maximum: 2MB.');
        }
        
        // Supprimer l'ancienne photo si elle existe
        if ($current_agent['photo'] && file_exists($upload_dir . $current_agent['photo'])) {
            unlink($upload_dir . $current_agent['photo']);
        }
        
        // Générer un nom de fichier unique
        $photo_filename = 'photo_' . $agent_id . '_' . uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $file_info['extension'];
        
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo_filename)) {
            throw new Exception('Erreur lors de l\'upload de la photo.');
        }
    }

    // Préparation des données (seuls certains champs peuvent être modifiés)
    $agent_data = [
        'matricule' => $current_agent['matricule'], // Non modifiable
        'prenom' => sanitizeInput($_POST['prenom']),
        'nom' => sanitizeInput($_POST['nom']),
        'email' => $current_agent['email'], // Non modifiable après inscription
        'date_recrutement' => sanitizeInput($_POST['date_recrutement'] ?? $current_agent['date_recrutement']),
        'structure_attache' => sanitizeInput($_POST['structure_attache'] ?? ''),
        'domaine_activites' => sanitizeInput($_POST['domaine_activites'] ?? ''),
        'specialite' => sanitizeInput($_POST['specialite'] ?? ''),
        'grade' => sanitizeInput($_POST['grade'] ?? $current_agent['grade']),
        'date_nomination' => sanitizeInput($_POST['date_nomination'] ?? $current_agent['date_nomination']),
        'numero_badge' => sanitizeInput($_POST['numero_badge'] ?? $current_agent['numero_badge']),
        'date_validite_badge' => sanitizeInput($_POST['date_validite_badge'] ?? $current_agent['date_validite_badge']),
        'date_prestation_serment' => sanitizeInput($_POST['date_prestation_serment'] ?? $current_agent['date_prestation_serment']),
        'photo' => $photo_filename
    ];

    // Debug: Log des données avant mise à jour
    error_log("Photo filename: " . $photo_filename);
    error_log("Agent data: " . print_r($agent_data, true));
    
    // Mise à jour de l'agent
    if ($agent->update($agent_id, $agent_data)) {
        // Mettre à jour la session si le nom a changé
        $_SESSION['agent_nom'] = $agent_data['prenom'] . ' ' . $agent_data['nom'];
        
        logActivity('UPDATE_AGENT_SELF', "Agent auto-modifié: {$agent_data['matricule']} - {$agent_data['prenom']} {$agent_data['nom']}", $agent_id);
        
        $response = [
            'success' => true, 
            'message' => 'Informations mises à jour avec succès.',
            'photo' => $photo_filename,
            'debug' => [
                'photo_uploaded' => isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK,
                'photo_filename' => $photo_filename
            ]
        ];
        echo json_encode($response);
    } else {
        throw new Exception('Erreur lors de la mise à jour.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
