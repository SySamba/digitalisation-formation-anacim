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

    // Préparation des données (seuls certains champs peuvent être modifiés)
    $agent_data = [
        'matricule' => $current_agent['matricule'], // Non modifiable
        'prenom' => sanitizeInput($_POST['prenom']),
        'nom' => sanitizeInput($_POST['nom']),
        'email' => $current_agent['email'], // Non modifiable après inscription
        'date_recrutement' => sanitizeInput($_POST['date_recrutement'] ?? $current_agent['date_recrutement']),
        'structure_attache' => sanitizeInput($_POST['structure_attache'] ?? ''),
        'domaine_activites' => sanitizeInput($_POST['domaine_activites'] ?? ''),
        'specialiste' => sanitizeInput($_POST['specialiste'] ?? ''),
        'grade' => sanitizeInput($_POST['grade'] ?? $current_agent['grade']),
        'date_nomination' => sanitizeInput($_POST['date_nomination'] ?? $current_agent['date_nomination']),
        'numero_badge' => sanitizeInput($_POST['numero_badge'] ?? $current_agent['numero_badge']),
        'date_validite_badge' => sanitizeInput($_POST['date_validite_badge'] ?? $current_agent['date_validite_badge']),
        'date_prestation_serment' => sanitizeInput($_POST['date_prestation_serment'] ?? $current_agent['date_prestation_serment']),
        'photo' => $current_agent['photo']
    ];

    // Mise à jour de l'agent
    if ($agent->update($agent_id, $agent_data)) {
        // Mettre à jour la session si le nom a changé
        $_SESSION['agent_nom'] = $agent_data['prenom'] . ' ' . $agent_data['nom'];
        
        logActivity('UPDATE_AGENT_SELF', "Agent auto-modifié: {$agent_data['matricule']} - {$agent_data['prenom']} {$agent_data['nom']}", $agent_id);
        echo json_encode(['success' => true, 'message' => 'Informations mises à jour avec succès.']);
    } else {
        throw new Exception('Erreur lors de la mise à jour.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
