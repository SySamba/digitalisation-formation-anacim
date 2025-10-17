<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de planification manquant']);
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
    
    $stmt = $pdo->prepare("
        SELECT 
            pf.*,
            a.matricule,
            a.prenom,
            a.nom,
            f.code,
            f.intitule
        FROM planning_formations pf
        JOIN agents a ON pf.agent_id = a.id
        JOIN formations f ON pf.formation_id = f.id
        WHERE pf.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $planning = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$planning) {
        echo json_encode(['success' => false, 'message' => 'Planification non trouvée']);
        exit;
    }
    
    echo json_encode(['success' => true, 'data' => $planning]);
    
} catch (Exception $e) {
    error_log("Erreur récupération planning: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
