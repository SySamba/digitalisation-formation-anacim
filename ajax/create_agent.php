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

try {
    $database = new Database();
    $db = $database->getConnection();
    $agent = new Agent($db);

    // Validation des données
    $required_fields = ['matricule', 'prenom', 'nom', 'date_recrutement', 'grade'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est obligatoire.");
        }
    }

    // Validation spéciale pour inspecteur titulaire
    if ($_POST['grade'] === 'inspecteur_titulaire') {
        $errors = validateInspecteurTitulaireFields($_POST);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors));
        }
    }

    // Gestion de l'upload de photo
    $photo_filename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/photos';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $photo_filename = uploadFile($_FILES['photo'], $upload_dir, ['jpg', 'jpeg', 'png']);
    }

    // Préparation des données
    $agent_data = [
        'matricule' => sanitizeInput($_POST['matricule']),
        'prenom' => sanitizeInput($_POST['prenom']),
        'nom' => sanitizeInput($_POST['nom']),
        'date_recrutement' => $_POST['date_recrutement'],
        'structure_attache' => sanitizeInput($_POST['structure_attache'] ?? ''),
        'domaine_activites' => sanitizeInput($_POST['domaine_activites'] ?? ''),
        'specialite' => sanitizeInput($_POST['specialite'] ?? ''),
        'grade' => $_POST['grade'],
        'date_nomination' => $_POST['date_nomination'] ?? null,
        'numero_badge' => sanitizeInput($_POST['numero_badge'] ?? ''),
        'date_validite_badge' => $_POST['date_validite_badge'] ?? null,
        'date_prestation_serment' => $_POST['date_prestation_serment'] ?? null,
        'photo' => $photo_filename
    ];

    // Validation des dates
    if (!validateDate($agent_data['date_recrutement'])) {
        throw new Exception('Date de recrutement invalide.');
    }

    if ($agent_data['grade'] === 'inspecteur_titulaire') {
        if (!validateDate($agent_data['date_nomination']) || 
            !validateDate($agent_data['date_validite_badge']) || 
            !validateDate($agent_data['date_prestation_serment'])) {
            throw new Exception('Une ou plusieurs dates pour l\'inspecteur titulaire sont invalides.');
        }
    }

    // Création de l'agent
    if ($agent->create($agent_data)) {
        logActivity('CREATE_AGENT', "Nouvel agent créé: {$agent_data['matricule']} - {$agent_data['prenom']} {$agent_data['nom']}");
        echo json_encode(['success' => true, 'message' => 'Agent créé avec succès.']);
    } else {
        throw new Exception('Erreur lors de la création de l\'agent.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
