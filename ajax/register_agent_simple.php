<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Récupérer les données du formulaire
    $matricule = trim($_POST['matricule'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($matricule) || empty($prenom) || empty($nom) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
        exit;
    }
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format d\'email invalide']);
        exit;
    }
    
    // Vérifier si le matricule existe déjà
    $stmt = $pdo->prepare("SELECT id FROM agents WHERE matricule = ?");
    $stmt->execute([$matricule]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ce matricule existe déjà']);
        exit;
    }
    
    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT id FROM agents WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
        exit;
    }
    
    // Insérer le nouvel agent avec les informations de base seulement
    $stmt = $pdo->prepare("
        INSERT INTO agents (matricule, prenom, nom, email) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([$matricule, $prenom, $nom, $email]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Agent inscrit avec succès',
        'agent_id' => $pdo->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur inscription agent: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'inscription']);
}
?>
