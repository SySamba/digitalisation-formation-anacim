<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Agent.php';
require_once __DIR__ . '/../includes/simple_pdf_generator.php';

if (!isset($_GET['agent_id']) || !isset($_GET['format'])) {
    http_response_code(400);
    echo 'Paramètres manquants';
    exit;
}

$agent_id = $_GET['agent_id'];
$format = $_GET['format']; // 'pdf' ou 'word'

$database = new Database();
$db = $database->getConnection();
$agent = new Agent($db);

$agent_data = $agent->readOne($agent_id);
if (!$agent_data) {
    http_response_code(404);
    echo 'Agent non trouvé';
    exit;
}

// Fonctions pour récupérer les données
function getFormationsEffectueesByAgent($db, $agent_id) {
    $query = "SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois
              FROM formations_effectuees fe
              JOIN formations f ON fe.formation_id = f.id
              WHERE fe.agent_id = ?
              ORDER BY fe.date_fin DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id]);
    return $stmt->fetchAll();
}

function getFormationsNonEffectueesByAgent($db, $agent_id) {
    $query = "SELECT f.id, f.intitule, f.code, f.categorie, f.periodicite_mois
              FROM formations f
              WHERE f.id NOT IN (
                  SELECT DISTINCT fe.formation_id 
                  FROM formations_effectuees fe 
                  WHERE fe.agent_id = ?
              )
              ORDER BY f.categorie, f.code";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id]);
    return $stmt->fetchAll();
}

function getFormationsPlanifieesByAgent($db, $agent_id) {
    $query = "SELECT pf.*, f.intitule, f.code, f.categorie
              FROM planning_formations pf
              JOIN formations f ON pf.formation_id = f.id
              WHERE pf.agent_id = ? AND pf.statut = 'planifie'
              ORDER BY pf.date_prevue_debut ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id]);
    return $stmt->fetchAll();
}

// Récupérer les données
$formations_effectuees = getFormationsEffectueesByAgent($db, $agent_id);
$formations_non_effectuees = getFormationsNonEffectueesByAgent($db, $agent_id);
$formations_planifiees = getFormationsPlanifieesByAgent($db, $agent_id);

// Générer le document selon le format demandé
if ($format === 'word') {
    require_once __DIR__ . '/../includes/document_generator.php';
    generateWordDocument($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees);
} elseif ($format === 'pdf') {
    generateSimplePDF($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees);
} else {
    http_response_code(400);
    echo 'Format non supporté';
}
?>
