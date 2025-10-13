<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Formation.php';
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
    $formationEffectuee = new FormationEffectuee($db);

    $agent_id = $_SESSION['agent_id'];
    
    // Validation des données
    $required_fields = ['formation_id', 'date_debut', 'date_fin'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est obligatoire.");
        }
    }

    // Gestion de l'upload du certificat
    $certificat_filename = null;
    if (isset($_FILES['certificat']) && $_FILES['certificat']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/formations';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $certificat_filename = uploadFile($_FILES['certificat'], $upload_dir, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
    }

    // Créer l'enregistrement de formation effectuée
    $formation_data = [
        'agent_id' => $agent_id,
        'formation_id' => $_POST['formation_id'],
        'centre_formation' => sanitizeInput($_POST['centre_formation'] ?? ''),
        'date_debut' => $_POST['date_debut'],
        'date_fin' => $_POST['date_fin'],
        'fichier_joint' => $certificat_filename,
        'statut' => 'valide'
    ];

    if ($formationEffectuee->create($formation_data)) {
        logActivity('ADD_FORMATION_SELF', "Agent a ajouté une formation: formation_id={$_POST['formation_id']}", $agent_id);
        echo json_encode(['success' => true, 'message' => 'Formation ajoutée avec succès.']);
    } else {
        throw new Exception('Erreur lors de l\'ajout de la formation.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
