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

// Fonctions pour récupérer les données - VERSION AMÉLIORÉE
function getFormationsEffectueesByAgent($db, $agent_id) {
    // Récupérer toutes les formations effectuées depuis toutes les tables
    $query = "
        (SELECT fe.id, fe.agent_id, fe.formation_id, fe.centre_formation, 
                fe.date_debut, fe.date_fin, fe.fichier_joint, fe.statut, 
                fe.prochaine_echeance, fe.created_at,
                f.intitule, f.code, f.categorie, f.periodicite_mois,
                'formations_effectuees' as source_table
         FROM formations_effectuees fe
         JOIN formations f ON fe.formation_id = f.id
         WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide'))
        
        UNION ALL
        
        (SELECT fa.id, fa.agent_id, fa.formation_id, fa.centre_formation,
                fa.date_debut, fa.date_fin, fa.fichier_joint, fa.statut,
                fa.prochaine_echeance, fa.created_at,
                f.intitule, f.code, f.categorie, f.periodicite_mois,
                'formations_agents' as source_table
         FROM formations_agents fa
         JOIN formations f ON fa.formation_id = f.id
         WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide'))
        
        ORDER BY date_fin DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id, $agent_id]);
    return $stmt->fetchAll();
}

function getFormationsNonEffectueesByAgent($db, $agent_id) {
    // Récupérer les formations non effectuées en excluant celles déjà faites
    $query = "
        SELECT DISTINCT f.id, f.intitule, f.code, f.categorie, f.periodicite_mois,
               fne.priorite, fne.raison, fne.date_identification
        FROM formations f
        LEFT JOIN formations_non_effectuees fne ON f.id = fne.formation_id AND fne.agent_id = ?
        WHERE f.id NOT IN (
            -- Exclure les formations déjà effectuées
            SELECT DISTINCT fe.formation_id 
            FROM formations_effectuees fe 
            WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide')
            
            UNION
            
            SELECT DISTINCT fa.formation_id 
            FROM formations_agents fa 
            WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide')
        )
        AND f.id NOT IN (
            -- Exclure les formations déjà planifiées
            SELECT DISTINCT pf.formation_id 
            FROM planning_formations pf 
            WHERE pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme')
        )
        ORDER BY f.categorie, f.code";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id, $agent_id, $agent_id, $agent_id]);
    return $stmt->fetchAll();
}

function getFormationsPlanifieesByAgent($db, $agent_id) {
    // Récupérer toutes les formations planifiées
    $query = "SELECT pf.*, f.intitule, f.code, f.categorie, f.periodicite_mois
              FROM planning_formations pf
              JOIN formations f ON pf.formation_id = f.id
              WHERE pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme')
              ORDER BY pf.date_prevue_debut ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id]);
    return $stmt->fetchAll();
}

// Nouvelle fonction pour obtenir les formations nécessitant une mise à jour
function getFormationsAMettreAJour($db, $agent_id) {
    // VERSION SIMPLIFIÉE - Approche en étapes pour éviter les problèmes SQL complexes
    // Pour les formations techniques (SUR-FTS), on regarde dans les 3 ans (36 mois)
    // Pour les autres formations, on regarde dans les 6 mois (180 jours)
    
    // Étape 1: Récupérer toutes les formations effectuées avec périodicité
    $query = "
        SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
               DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
               DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
               'formations_effectuees' as source_table
        FROM formations_effectuees fe
        JOIN formations f ON fe.formation_id = f.id
        WHERE fe.agent_id = ? AND fe.statut IN ('termine', 'valide') AND f.periodicite_mois > 0
        
        UNION ALL
        
        SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
               DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
               DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
               'formations_agents' as source_table
        FROM formations_agents fa
        JOIN formations f ON fa.formation_id = f.id
        WHERE fa.agent_id = ? AND fa.statut IN ('termine', 'valide') AND f.periodicite_mois > 0
        
        ORDER BY formation_id, date_fin DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$agent_id, $agent_id]);
    $all_formations_temp = $stmt->fetchAll();
    
    // Étape 2: Traitement PHP pour garder seulement la formation la plus récente par formation_id
    $formations_par_id = [];
    foreach ($all_formations_temp as $formation) {
        $formation_id = $formation['formation_id'];
        if (!isset($formations_par_id[$formation_id]) || 
            strtotime($formation['date_fin']) > strtotime($formations_par_id[$formation_id]['date_fin'])) {
            $formations_par_id[$formation_id] = $formation;
        }
    }
    
    // Étape 3: Filtrer les formations à renouveler selon les critères d'échéance
    $formations_a_renouveler = [];
    foreach ($formations_par_id as $formation) {
        $a_renouveler = false;
        
        if (strpos($formation['code'], 'SUR-FTS') !== false) {
            // Formations techniques SUR-FTS : échéance dans les 3 ans (1095 jours)
            $a_renouveler = $formation['jours_restants'] <= 1095;
        } else {
            // Autres formations : échéance dans les 6 mois (180 jours)
            $a_renouveler = $formation['jours_restants'] <= 180;
        }
        
        if ($a_renouveler) {
            $formations_a_renouveler[] = $formation;
        }
    }
    
    // Trier par jours restants
    usort($formations_a_renouveler, function($a, $b) {
        return $a['jours_restants'] - $b['jours_restants'];
    });
    
    return $formations_a_renouveler;
}

// Récupérer les données
$formations_effectuees = getFormationsEffectueesByAgent($db, $agent_id);
$formations_non_effectuees = getFormationsNonEffectueesByAgent($db, $agent_id);
$formations_planifiees = getFormationsPlanifieesByAgent($db, $agent_id);
$formations_a_mettre_a_jour = getFormationsAMettreAJour($db, $agent_id);

// Générer le document selon le format demandé
if ($format === 'word') {
    require_once __DIR__ . '/../includes/document_generator.php';
    generateWordDocument($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees, $formations_a_mettre_a_jour);
} elseif ($format === 'pdf') {
    generateSimplePDF($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees, $formations_a_mettre_a_jour);
} else {
    http_response_code(400);
    echo 'Format non supporté';
}
?>
