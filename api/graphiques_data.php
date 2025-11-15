<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once '../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Type de données demandé
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';

    $response = [];

    if ($type === 'agents' || $type === 'all') {
        // Données pour le graphique de répartition des formations par agent
        $query_agents_stats = "
            SELECT 
                a.id,
                CONCAT(a.prenom, ' ', a.nom) as nom_complet,
                a.matricule,
                
                -- Formations effectuées
                (SELECT COUNT(*) FROM formations_effectuees fe WHERE fe.agent_id = a.id AND fe.statut IN ('termine', 'valide')) +
                (SELECT COUNT(*) FROM formations_agents fa WHERE fa.agent_id = a.id AND fa.statut IN ('termine', 'valide')) as formations_effectuees,
                
                -- Formations non effectuées
                (SELECT COUNT(*) FROM formations f WHERE f.id NOT IN (
                    SELECT DISTINCT fe.formation_id FROM formations_effectuees fe WHERE fe.agent_id = a.id AND fe.statut IN ('termine', 'valide')
                    UNION
                    SELECT DISTINCT fa.formation_id FROM formations_agents fa WHERE fa.agent_id = a.id AND fa.statut IN ('termine', 'valide')
                )) as formations_non_effectuees,
                
                -- Formations à renouveler
                (SELECT COUNT(*) FROM formations_effectuees fe 
                 JOIN formations f ON fe.formation_id = f.id 
                 WHERE fe.agent_id = a.id AND fe.statut IN ('termine', 'valide') 
                 AND f.periodicite_mois > 0 
                 AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)) +
                (SELECT COUNT(*) FROM formations_agents fa 
                 JOIN formations f ON fa.formation_id = f.id 
                 WHERE fa.agent_id = a.id AND fa.statut IN ('termine', 'valide') 
                 AND f.periodicite_mois > 0 
                 AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)) as formations_a_renouveler
                 
            FROM agents a
            ORDER BY a.nom, a.prenom
        ";

        $stmt_agents = $pdo->prepare($query_agents_stats);
        $stmt_agents->execute();
        $agents_stats = $stmt_agents->fetchAll(PDO::FETCH_ASSOC);

        $response['agents'] = [
            'labels' => [],
            'effectuees' => [],
            'non_effectuees' => [],
            'a_renouveler' => [],
            'raw_data' => $agents_stats
        ];

        foreach ($agents_stats as $agent) {
            $response['agents']['labels'][] = $agent['nom_complet'] . ' (' . $agent['matricule'] . ')';
            $response['agents']['effectuees'][] = (int)$agent['formations_effectuees'];
            $response['agents']['non_effectuees'][] = (int)$agent['formations_non_effectuees'];
            $response['agents']['a_renouveler'][] = (int)$agent['formations_a_renouveler'];
        }
    }

    if ($type === 'types' || $type === 'all') {
        // Données pour le graphique de répartition par type de formation
        $query_types_stats = "
            SELECT 
                f.categorie,
                COUNT(DISTINCT f.id) as total_formations,
                
                -- Formations effectuées par catégorie
                (SELECT COUNT(*) FROM formations_effectuees fe 
                 JOIN formations f2 ON fe.formation_id = f2.id 
                 WHERE f2.categorie = f.categorie AND fe.statut IN ('termine', 'valide')) +
                (SELECT COUNT(*) FROM formations_agents fa 
                 JOIN formations f3 ON fa.formation_id = f3.id 
                 WHERE f3.categorie = f.categorie AND fa.statut IN ('termine', 'valide')) as effectuees,
                
                -- Formations non effectuées par catégorie
                (SELECT COUNT(DISTINCT f4.id) FROM formations f4 
                 WHERE f4.categorie = f.categorie 
                 AND f4.id NOT IN (
                    SELECT DISTINCT fe.formation_id FROM formations_effectuees fe WHERE fe.statut IN ('termine', 'valide')
                    UNION
                    SELECT DISTINCT fa.formation_id FROM formations_agents fa WHERE fa.statut IN ('termine', 'valide')
                 )) as non_effectuees
                 
            FROM formations f
            GROUP BY f.categorie
            ORDER BY f.categorie
        ";

        $stmt_types = $pdo->prepare($query_types_stats);
        $stmt_types->execute();
        $types_stats = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

        $response['types'] = [
            'labels' => [],
            'effectuees' => [],
            'non_effectuees' => [],
            'raw_data' => $types_stats
        ];

        foreach ($types_stats as $type_data) {
            $type_label = '';
            switch ($type_data['categorie']) {
                case 'FAMILIARISATION':
                    $type_label = 'Familiarisation';
                    break;
                case 'FORMATION_INITIALE':
                    $type_label = 'Formation Initiale';
                    break;
                case 'FORMATION_COURS_EMPLOI':
                    $type_label = 'Formation en Cours d\'Emploi';
                    break;
                case 'FORMATION_TECHNIQUE':
                    $type_label = 'Formation Technique';
                    break;
                default:
                    $type_label = $type_data['categorie'];
            }
            
            $response['types']['labels'][] = $type_label;
            $response['types']['effectuees'][] = (int)$type_data['effectuees'];
            $response['types']['non_effectuees'][] = (int)$type_data['non_effectuees'];
        }
    }

    if ($type === 'stats' || $type === 'all') {
        // Statistiques globales
        $query_global_stats = "
            SELECT 
                (SELECT COUNT(*) FROM agents) as total_agents,
                (SELECT COUNT(*) FROM formations) as total_formations,
                (SELECT COUNT(*) FROM formations_effectuees WHERE statut IN ('termine', 'valide')) +
                (SELECT COUNT(*) FROM formations_agents WHERE statut IN ('termine', 'valide')) as total_formations_effectuees,
                (SELECT COUNT(DISTINCT f.id) FROM formations f WHERE f.id NOT IN (
                    SELECT DISTINCT fe.formation_id FROM formations_effectuees fe WHERE fe.statut IN ('termine', 'valide')
                    UNION
                    SELECT DISTINCT fa.formation_id FROM formations_agents fa WHERE fa.statut IN ('termine', 'valide')
                )) as total_formations_non_effectuees
        ";

        $stmt_global = $pdo->prepare($query_global_stats);
        $stmt_global->execute();
        $global_stats = $stmt_global->fetch(PDO::FETCH_ASSOC);

        $response['global_stats'] = $global_stats;
    }

    // Ajouter des métadonnées
    $response['metadata'] = [
        'generated_at' => date('Y-m-d H:i:s'),
        'type_requested' => $type,
        'success' => true
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage(),
        'generated_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
