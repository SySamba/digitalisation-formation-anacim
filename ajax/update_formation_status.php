<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['agent_logged_in']) || $_SESSION['agent_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$agent_id = $_SESSION['agent_id'];
$formation_id = $_POST['formation_id'] ?? null;
$action = $_POST['action'] ?? null; // 'planifier' ou 'effectuee'
$date_prevue_debut = $_POST['date_prevue_debut'] ?? null;
$date_prevue_fin = $_POST['date_prevue_fin'] ?? null;
$centre_formation_prevu = $_POST['centre_formation_prevu'] ?? null;
$date_debut = $_POST['date_debut'] ?? null;
$date_fin = $_POST['date_fin'] ?? null;
$centre_formation = $_POST['centre_formation'] ?? null;

if (!$formation_id || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    $db->beginTransaction();
    
    if ($action === 'planifier') {
        // Ajouter à la table planning_formations
        $stmt = $db->prepare("
            INSERT INTO planning_formations 
            (agent_id, formation_id, date_prevue_debut, date_prevue_fin, centre_formation_prevu, statut, created_at) 
            VALUES (?, ?, ?, ?, ?, 'planifie', NOW())
            ON DUPLICATE KEY UPDATE 
            date_prevue_debut = VALUES(date_prevue_debut),
            date_prevue_fin = VALUES(date_prevue_fin),
            centre_formation_prevu = VALUES(centre_formation_prevu),
            statut = 'planifie',
            updated_at = NOW()
        ");
        
        $stmt->execute([
            $agent_id, 
            $formation_id, 
            $date_prevue_debut, 
            $date_prevue_fin, 
            $centre_formation_prevu
        ]);
        
        $message = 'Formation planifiée avec succès';
        
    } elseif ($action === 'effectuee') {
        // Ajouter à la table formations_effectuees
        $stmt = $db->prepare("
            INSERT INTO formations_effectuees 
            (agent_id, formation_id, date_debut, date_fin, centre_formation, statut, created_at) 
            VALUES (?, ?, ?, ?, ?, 'termine', NOW())
        ");
        
        $stmt->execute([
            $agent_id, 
            $formation_id, 
            $date_debut, 
            $date_fin, 
            $centre_formation
        ]);
        
        // Supprimer de planning_formations si elle était planifiée
        $stmt_delete = $db->prepare("
            DELETE FROM planning_formations 
            WHERE agent_id = ? AND formation_id = ?
        ");
        $stmt_delete->execute([$agent_id, $formation_id]);
        
        $message = 'Formation marquée comme effectuée avec succès';
    }
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Erreur update_formation_status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}
?>
