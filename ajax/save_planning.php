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
    
    // Récupérer les données du formulaire
    $agent_id = $_POST['agent_id'] ?? '';
    $formation_id = $_POST['formation_id'] ?? '';
    $centre_formation_prevu = $_POST['centre_formation_prevu'] ?? '';
    $date_prevue_debut = $_POST['date_prevue_debut'] ?? '';
    $date_prevue_fin = $_POST['date_prevue_fin'] ?? '';
    $statut = $_POST['statut'] ?? 'planifie';
    $commentaires = $_POST['commentaires'] ?? '';
    
    // Validation
    if (empty($agent_id) || empty($formation_id) || empty($centre_formation_prevu) || 
        empty($date_prevue_debut) || empty($date_prevue_fin)) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs obligatoires doivent être remplis']);
        exit;
    }
    
    // Vérifier que les dates sont cohérentes
    if (strtotime($date_prevue_fin) <= strtotime($date_prevue_debut)) {
        echo json_encode(['success' => false, 'message' => 'La date de fin doit être postérieure à la date de début']);
        exit;
    }
    
    // Vérifier que l'agent et la formation existent
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents WHERE id = ?");
    $stmt->execute([$agent_id]);
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Agent non trouvé']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM formations WHERE id = ?");
    $stmt->execute([$formation_id]);
    $formation = $stmt->fetch();
    if (!$formation) {
        echo json_encode(['success' => false, 'message' => 'Formation non trouvée']);
        exit;
    }
    
    // Vérifier qu'il n'y a pas déjà une planification pour cet agent et cette formation
    // SAUF pour les formations périodiques (SUR-FTS) qui peuvent être planifiées plusieurs fois
    $is_periodic = strpos($formation['code'], 'SUR-FTS') !== false;
    
    if (!$is_periodic) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM planning_formations 
            WHERE agent_id = ? AND formation_id = ? AND statut IN ('planifie', 'confirme')
        ");
        $stmt->execute([$agent_id, $formation_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Cette formation est déjà planifiée pour cet agent. Les formations non-périodiques ne peuvent être planifiées qu\'une seule fois. Seules les formations techniques/spécialisées (SUR-FTS) peuvent être re-planifiées.']);
            exit;
        }
    }
    
    // Insérer la planification
    $stmt = $pdo->prepare("
        INSERT INTO planning_formations 
        (agent_id, formation_id, date_prevue_debut, date_prevue_fin, centre_formation_prevu, statut, commentaires, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $agent_id,
        $formation_id,
        $date_prevue_debut,
        $date_prevue_fin,
        $centre_formation_prevu,
        $statut,
        $commentaires
    ]);
    
    // Log de l'activité
    $log_message = "Planification créée - Agent ID: $agent_id, Formation ID: $formation_id, Dates: $date_prevue_debut au $date_prevue_fin";
    error_log($log_message);
    
    echo json_encode([
        'success' => true,
        'message' => 'Formation planifiée avec succès',
        'planning_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    error_log("Erreur sauvegarde planning: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
}
?>
