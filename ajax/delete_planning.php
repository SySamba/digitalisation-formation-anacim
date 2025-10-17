<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Récupérer les données JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $planning_id = $input['planning_id'] ?? '';
    
    if (empty($planning_id)) {
        echo json_encode(['success' => false, 'message' => 'ID de planification manquant']);
        exit;
    }
    
    // Vérifier que la planification existe
    $stmt = $pdo->prepare("SELECT * FROM planning_formations WHERE id = ?");
    $stmt->execute([$planning_id]);
    $planning = $stmt->fetch();
    
    if (!$planning) {
        echo json_encode(['success' => false, 'message' => 'Planification non trouvée']);
        exit;
    }
    
    // Supprimer la planification (ou marquer comme annulée)
    $stmt = $pdo->prepare("UPDATE planning_formations SET statut = 'annule', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$planning_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Planification supprimée avec succès'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur suppression planning: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
}
?>
