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
    $planning_id = $_POST['planning_id'] ?? '';
    $centre_formation_prevu = $_POST['centre_formation_prevu'] ?? '';
    $date_prevue_debut = $_POST['date_prevue_debut'] ?? '';
    $date_prevue_fin = $_POST['date_prevue_fin'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $pays = $_POST['pays'] ?? '';
    $duree = $_POST['duree'] ?? '';
    $perdiem = $_POST['perdiem'] ?? null;
    $priorite = $_POST['priorite'] ?? '3';
    $statut = $_POST['statut'] ?? 'planifie';
    $commentaires = $_POST['commentaires'] ?? '';
    
    // Validation
    if (empty($planning_id) || empty($centre_formation_prevu) || 
        empty($date_prevue_debut) || empty($date_prevue_fin) ||
        empty($ville) || empty($pays) || empty($duree) || empty($priorite)) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs obligatoires doivent être remplis']);
        exit;
    }
    
    // Vérifier que les dates sont cohérentes
    if (strtotime($date_prevue_fin) <= strtotime($date_prevue_debut)) {
        echo json_encode(['success' => false, 'message' => 'La date de fin doit être postérieure à la date de début']);
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
    
    // Mettre à jour la planification
    $stmt = $pdo->prepare("
        UPDATE planning_formations 
        SET date_prevue_debut = ?, 
            date_prevue_fin = ?, 
            centre_formation_prevu = ?,
            ville = ?,
            pays = ?,
            duree = ?,
            perdiem = ?,
            priorite = ?,
            statut = ?, 
            commentaires = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $date_prevue_debut,
        $date_prevue_fin,
        $centre_formation_prevu,
        $ville,
        $pays,
        $duree,
        $perdiem,
        $priorite,
        $statut,
        $commentaires,
        $planning_id
    ]);
    
    // Log de l'activité
    $log_message = "Planification modifiée - Planning ID: $planning_id, Dates: $date_prevue_debut au $date_prevue_fin";
    error_log($log_message);
    
    echo json_encode([
        'success' => true,
        'message' => 'Planification modifiée avec succès'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur modification planning: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification: ' . $e->getMessage()]);
}
?>
