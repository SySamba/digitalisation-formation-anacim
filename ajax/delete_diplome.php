<?php
session_start();
require_once __DIR__ . '/../config/database.php';
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    $diplome_id = $input['diplome_id'] ?? null;
    
    if (!$diplome_id) {
        throw new Exception('ID diplôme manquant.');
    }

    $agent_id = $_SESSION['agent_id'];
    
    // Vérifier que le diplôme appartient à l'agent connecté
    $query = "SELECT * FROM diplomes WHERE id = ? AND agent_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$diplome_id, $agent_id]);
    $diplome = $stmt->fetch();
    
    if (!$diplome) {
        throw new Exception('Diplôme non trouvé ou non autorisé.');
    }

    // Supprimer le fichier s'il existe
    if ($diplome['fichier_path']) {
        $file_path = __DIR__ . '/../uploads/diplomes/' . $diplome['fichier_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Supprimer l'enregistrement
    $delete_query = "DELETE FROM diplomes WHERE id = ? AND agent_id = ?";
    $delete_stmt = $db->prepare($delete_query);
    
    if ($delete_stmt->execute([$diplome_id, $agent_id])) {
        echo json_encode(['success' => true, 'message' => 'Document supprimé avec succès.']);
    } else {
        throw new Exception('Erreur lors de la suppression.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
