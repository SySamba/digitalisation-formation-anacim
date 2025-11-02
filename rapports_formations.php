<?php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'classes/Agent.php';
require_once 'classes/Formation.php';

$database = new Database();
$db = $database->getConnection();

$agent = new Agent($db);
$formation = new Formation($db);

// Récupérer tous les agents
$agents = $agent->read();
$formations = $formation->read();

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

// Fonction pour obtenir les formations planifiées par agent
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

// Traitement des actions
$action = $_GET['action'] ?? '';
$agent_id = $_GET['agent_id'] ?? '';

if ($action === 'view_agent' && $agent_id) {
    // Afficher le rapport pour un agent spécifique
    $agent_data = $agent->readOne($agent_id);
    if ($agent_data) {
        $formations_effectuees = getFormationsEffectueesByAgent($db, $agent_id);
        $formations_non_effectuees = getFormationsNonEffectueesByAgent($db, $agent_id);
        $formations_planifiees = getFormationsPlanifieesByAgent($db, $agent_id);
        $formations_a_mettre_a_jour = getFormationsAMettreAJour($db, $agent_id);
        
        // Afficher la page de prévisualisation
        $show_preview = true;
    }
} elseif ($action === 'download_agent' && $agent_id) {
    // Télécharger le rapport pour un agent spécifique
    $agent_data = $agent->readOne($agent_id);
    if ($agent_data) {
        $formations_effectuees = getFormationsEffectueesByAgent($db, $agent_id);
        $formations_non_effectuees = getFormationsNonEffectueesByAgent($db, $agent_id);
        $formations_planifiees = getFormationsPlanifieesByAgent($db, $agent_id);
        $formations_a_mettre_a_jour = getFormationsAMettreAJour($db, $agent_id);
        
        // Générer le fichier CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rapport_formations_' . $agent_data['matricule'] . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM pour UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // En-tête du rapport
        fputcsv($output, ['RAPPORT DE FORMATIONS - ' . $agent_data['prenom'] . ' ' . $agent_data['nom']], ';');
        fputcsv($output, ['Matricule: ' . $agent_data['matricule']], ';');
        fputcsv($output, ['Grade: ' . $agent_data['grade']], ';');
        fputcsv($output, ['Date de génération: ' . date('d/m/Y H:i')], ';');
        fputcsv($output, [''], ';');
        
        // Formations effectuées
        fputcsv($output, ['=== FORMATIONS EFFECTUÉES ==='], ';');
        fputcsv($output, ['Code Formation', 'Intitulé', 'Catégorie', 'Centre Formation', 'Date Début', 'Date Fin', 'Statut'], ';');
        
        foreach ($formations_effectuees as $fe) {
            fputcsv($output, [
                $fe['code'],
                $fe['intitule'],
                $fe['categorie'],
                $fe['centre_formation'],
                date('d/m/Y', strtotime($fe['date_debut'])),
                date('d/m/Y', strtotime($fe['date_fin'])),
                $fe['statut']
            ], ';');
        }
        
        fputcsv($output, [''], ';');
        
        // Formations planifiées
        fputcsv($output, ['=== FORMATIONS PLANIFIÉES ==='], ';');
        fputcsv($output, ['Code Formation', 'Intitulé', 'Catégorie', 'Date Prévue Début', 'Date Prévue Fin', 'Centre Prévu', 'Statut'], ';');
        
        foreach ($formations_planifiees as $fp) {
            fputcsv($output, [
                $fp['code'],
                $fp['intitule'],
                $fp['categorie'],
                $fp['date_prevue_debut'] ? date('d/m/Y', strtotime($fp['date_prevue_debut'])) : '',
                $fp['date_prevue_fin'] ? date('d/m/Y', strtotime($fp['date_prevue_fin'])) : '',
                $fp['centre_formation_prevu'],
                $fp['statut']
            ], ';');
        }
        
        fputcsv($output, [''], ';');
        
        // Formations non effectuées
        fputcsv($output, ['=== FORMATIONS NON EFFECTUÉES ==='], ';');
        fputcsv($output, ['Code Formation', 'Intitulé', 'Catégorie', 'Périodicité (mois)'], ';');
        
        foreach ($formations_non_effectuees as $fne) {
            fputcsv($output, [
                $fne['code'],
                $fne['intitule'],
                $fne['categorie'],
                $fne['periodicite_mois']
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}

if ($action === 'download_pdf_agent' && $agent_id) {
    // Télécharger le rapport PDF pour un agent spécifique
    $agent_data = $agent->readOne($agent_id);
    if ($agent_data) {
        $formations_effectuees = getFormationsEffectueesByAgent($db, $agent_id);
        $formations_non_effectuees = getFormationsNonEffectueesByAgent($db, $agent_id);
        $formations_planifiees = getFormationsPlanifieesByAgent($db, $agent_id);
        $formations_a_mettre_a_jour = getFormationsAMettreAJour($db, $agent_id);
        
        // Générer le PDF avec une approche simplifiée
        require_once 'includes/pdf_generator.php';
        
        // Configuration du PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="rapport_formations_' . $agent_data['matricule'] . '_' . date('Y-m-d') . '.pdf"');
        
        // Contenu HTML du PDF
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2, h3 { color: #124c97; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #124c97; color: white; }
                .header-info { border: none; }
                .header-info td { border: none; padding: 5px 10px; }
            </style>
        </head>
        <body>
        <h1 style="text-align: center;">RAPPORT DE FORMATIONS</h1>
        <h2 style="text-align: center;">' . htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) . '</h2>
        
        <table class="header-info">
            <tr>
                <td><strong>Matricule:</strong></td>
                <td>' . htmlspecialchars($agent_data['matricule']) . '</td>
            </tr>
            <tr>
                <td><strong>Grade:</strong></td>
                <td>' . htmlspecialchars($agent_data['grade']) . '</td>
            </tr>
            <tr>
                <td><strong>Structure:</strong></td>
                <td>' . htmlspecialchars($agent_data['structure_attache'] ?? 'N/A') . '</td>
            </tr>
            <tr>
                <td><strong>Date de génération:</strong></td>
                <td>' . date('d/m/Y H:i') . '</td>
            </tr>
        </table>
        
        <h3>FORMATIONS EFFECTUÉES (' . count($formations_effectuees) . ')</h3>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Intitulé</th>
                    <th>Centre</th>
                    <th>Date Fin</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($formations_effectuees as $fe) {
            $html .= '<tr>
                <td>' . htmlspecialchars($fe['code']) . '</td>
                <td>' . htmlspecialchars($fe['intitule']) . '</td>
                <td>' . htmlspecialchars($fe['centre_formation']) . '</td>
                <td>' . date('d/m/Y', strtotime($fe['date_fin'])) . '</td>
                <td>' . htmlspecialchars($fe['statut']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table><br><br>';
        
        $html .= '<h3>FORMATIONS PLANIFIÉES (' . count($formations_planifiees) . ')</h3>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Intitulé</th>
                    <th>Date Prévue</th>
                    <th>Centre Prévu</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($formations_planifiees as $fp) {
            $html .= '<tr>
                <td>' . htmlspecialchars($fp['code']) . '</td>
                <td>' . htmlspecialchars($fp['intitule']) . '</td>
                <td>' . ($fp['date_prevue_debut'] ? date('d/m/Y', strtotime($fp['date_prevue_debut'])) : 'N/A') . '</td>
                <td>' . htmlspecialchars($fp['centre_formation_prevu'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($fp['statut']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table><br><br>';
        
        $html .= '<h3>FORMATIONS NON EFFECTUÉES (' . count($formations_non_effectuees) . ')</h3>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Intitulé</th>
                    <th>Catégorie</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($formations_non_effectuees as $fne) {
            $html .= '<tr>
                <td>' . htmlspecialchars($fne['code']) . '</td>
                <td>' . htmlspecialchars($fne['intitule']) . '</td>
                <td>' . htmlspecialchars($fne['categorie']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table></body></html>';
        
        // Générer le PDF avec wkhtmltopdf ou une approche simple
        generatePDFFromHTML($html, 'rapport_formations_' . $agent_data['matricule'] . '_' . date('Y-m-d') . '.pdf');
        exit;
    }
}

if ($action === 'download_all') {
    // Télécharger le rapport pour tous les agents
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_formations_tous_agents_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-tête du rapport global
    fputcsv($output, ['RAPPORT GLOBAL DES FORMATIONS - TOUS LES AGENTS'], ';');
    fputcsv($output, ['Date de génération: ' . date('d/m/Y H:i')], ';');
    fputcsv($output, ['Nombre total d\'agents: ' . count($agents)], ';');
    fputcsv($output, [''], ';');
    
    foreach ($agents as $agent_data) {
        $formations_effectuees = getFormationsEffectueesByAgent($db, $agent_data['id']);
        $formations_non_effectuees = getFormationsNonEffectueesByAgent($db, $agent_data['id']);
        $formations_planifiees = getFormationsPlanifieesByAgent($db, $agent_data['id']);
        $formations_a_mettre_a_jour = getFormationsAMettreAJour($db, $agent_data['id']);
        
        // Informations de l'agent
        fputcsv($output, ['=== AGENT: ' . $agent_data['prenom'] . ' ' . $agent_data['nom'] . ' (' . $agent_data['matricule'] . ') ==='], ';');
        fputcsv($output, ['Grade: ' . $agent_data['grade']], ';');
        fputcsv($output, [''], ';');
        
        // Statistiques
        fputcsv($output, ['Formations effectuées: ' . count($formations_effectuees)], ';');
        fputcsv($output, ['Formations planifiées: ' . count($formations_planifiees)], ';');
        fputcsv($output, ['Formations non effectuées: ' . count($formations_non_effectuees)], ';');
        fputcsv($output, [''], ';');
        
        // Formations effectuées
        if (!empty($formations_effectuees)) {
            fputcsv($output, ['Formations Effectuées:'], ';');
            fputcsv($output, ['Code', 'Intitulé', 'Date Fin', 'Statut'], ';');
            foreach ($formations_effectuees as $fe) {
                fputcsv($output, [
                    $fe['code'],
                    $fe['intitule'],
                    date('d/m/Y', strtotime($fe['date_fin'])),
                    $fe['statut']
                ], ';');
            }
            fputcsv($output, [''], ';');
        }
        
        // Formations planifiées
        if (!empty($formations_planifiees)) {
            fputcsv($output, ['Formations Planifiées:'], ';');
            fputcsv($output, ['Code', 'Intitulé', 'Date Prévue Début'], ';');
            foreach ($formations_planifiees as $fp) {
                fputcsv($output, [
                    $fp['code'],
                    $fp['intitule'],
                    $fp['date_prevue_debut'] ? date('d/m/Y', strtotime($fp['date_prevue_debut'])) : ''
                ], ';');
            }
            fputcsv($output, [''], ';');
        }
        
        fputcsv($output, ['---'], ';');
        fputcsv($output, [''], ';');
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports de Formations - ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #124c97;
            --danger-color: #ff011e;
            --warning-color: #f5df35;
            --dark-primary: #0a3570;
        }
        
        .navbar-custom {
            background-color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-primary);
            border-color: var(--dark-primary);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
        }
        
        .stats-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .agent-card {
            transition: transform 0.2s;
        }
        
        .agent-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-graduation-cap me-2"></i>
                ANACIM - Rapports de Formations
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin.php">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour à l'administration
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Rapports de Formations par Agent
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Générez et téléchargez les rapports de formations effectuées et non effectuées pour chaque agent ou pour tous les agents.</p>
                        
                        <!-- Bouton de téléchargement global -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card stats-card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">
                                            <i class="fas fa-download me-2"></i>
                                            Téléchargement Global
                                        </h5>
                                        <p class="card-text">Télécharger le rapport complet pour tous les agents (<?= count($agents) ?> agents)</p>
                                        <a href="?action=download_all" class="btn btn-primary btn-lg">
                                            <i class="fas fa-file-csv me-2"></i>
                                            Télécharger Rapport Global (CSV)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des agents -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            Rapports Individuels par Agent
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($agents as $agent_data): ?>
                                <?php
                                $formations_effectuees = getFormationsEffectueesByAgent($db, $agent_data['id']);
                                $formations_non_effectuees = getFormationsNonEffectueesByAgent($db, $agent_data['id']);
                                $formations_planifiees = getFormationsPlanifieesByAgent($db, $agent_data['id']);
                                $formations_a_mettre_a_jour = getFormationsAMettreAJour($db, $agent_data['id']);
                                ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card agent-card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-user me-2"></i>
                                                <?= htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) ?>
                                            </h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <strong>Matricule:</strong> <?= htmlspecialchars($agent_data['matricule']) ?><br>
                                                    <strong>Grade:</strong> <?= htmlspecialchars($agent_data['grade']) ?>
                                                </small>
                                            </p>
                                            
                                            <!-- Statistiques -->
                                            <div class="row text-center mb-3">
                                                <div class="col-3">
                                                    <div class="text-success">
                                                        <i class="fas fa-check-circle"></i><br>
                                                        <strong><?= count($formations_effectuees) ?></strong><br>
                                                        <small>Effectuées</small>
                                                    </div>
                                                </div>
                                                <div class="col-3">
                                                    <div class="text-danger">
                                                        <i class="fas fa-times-circle"></i><br>
                                                        <strong><?= count($formations_non_effectuees) ?></strong><br>
                                                        <small>Non effectuées</small>
                                                    </div>
                                                </div>
                                                <div class="col-3">
                                                    <div class="text-warning">
                                                        <i class="fas fa-exclamation-triangle"></i><br>
                                                        <strong><?= count($formations_a_mettre_a_jour) ?></strong><br>
                                                        <small>À Renouveler</small>
                                                    </div>
                                                </div>
                                                <div class="col-3">
                                                    <div class="text-info">
                                                        <i class="fas fa-calendar"></i><br>
                                                        <strong><?= count($formations_planifiees) ?></strong><br>
                                                        <small>Planifiées</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Boutons d'actions -->
                                            <div class="d-grid gap-2">
                                                <a href="?action=view_agent&agent_id=<?= $agent_data['id'] ?>" 
                                                   class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye me-1"></i>
                                                    Voir le rapport
                                                </a>
                                                <div class="btn-group" role="group">
                                                    <a href="?action=download_agent&agent_id=<?= $agent_data['id'] ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-file-csv me-1"></i>
                                                        CSV
                                                    </a>
                                                    <a href="?action=download_pdf_agent&agent_id=<?= $agent_data['id'] ?>" 
                                                       class="btn btn-outline-danger btn-sm">
                                                        <i class="fas fa-file-pdf me-1"></i>
                                                        PDF
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($show_preview) && $show_preview): ?>
    <!-- Modal de prévisualisation -->
    <div class="modal fade show" id="previewModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>
                        Rapport de Formations - <?= htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) ?>
                    </h5>
                    <a href="rapports_formations.php" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <!-- Informations de l'agent -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Informations de l'Agent</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3"><strong>Matricule:</strong> <?= htmlspecialchars($agent_data['matricule']) ?></div>
                                <div class="col-md-3"><strong>Grade:</strong> <?= htmlspecialchars($agent_data['grade']) ?></div>
                                <div class="col-md-3"><strong>Structure:</strong> <?= htmlspecialchars($agent_data['structure_attache'] ?? 'N/A') ?></div>
                                <div class="col-md-3"><strong>Date:</strong> <?= date('d/m/Y H:i') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Formations effectuées -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                Formations Effectuées (<?= count($formations_effectuees) ?>)
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($formations_effectuees)): ?>
                                <p class="text-muted">Aucune formation effectuée.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Intitulé</th>
                                                <th>Centre</th>
                                                <th>Date Début</th>
                                                <th>Date Fin</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($formations_effectuees as $fe): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($fe['code']) ?></td>
                                                    <td><?= htmlspecialchars($fe['intitule']) ?></td>
                                                    <td><?= htmlspecialchars($fe['centre_formation']) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($fe['date_debut'])) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($fe['date_fin'])) ?></td>
                                                    <td><span class="badge bg-success"><?= htmlspecialchars($fe['statut']) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Formations planifiées -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-clock me-2"></i>
                                Formations Planifiées (<?= count($formations_planifiees) ?>)
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($formations_planifiees)): ?>
                                <p class="text-muted">Aucune formation planifiée.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Intitulé</th>
                                                <th>Date Prévue Début</th>
                                                <th>Date Prévue Fin</th>
                                                <th>Centre Prévu</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($formations_planifiees as $fp): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($fp['code']) ?></td>
                                                    <td><?= htmlspecialchars($fp['intitule']) ?></td>
                                                    <td><?= $fp['date_prevue_debut'] ? date('d/m/Y', strtotime($fp['date_prevue_debut'])) : 'N/A' ?></td>
                                                    <td><?= $fp['date_prevue_fin'] ? date('d/m/Y', strtotime($fp['date_prevue_fin'])) : 'N/A' ?></td>
                                                    <td><?= htmlspecialchars($fp['centre_formation_prevu'] ?? 'N/A') ?></td>
                                                    <td><span class="badge bg-warning"><?= htmlspecialchars($fp['statut']) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Formations non effectuées -->
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-times-circle me-2"></i>
                                Formations Non Effectuées (<?= count($formations_non_effectuees) ?>)
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($formations_non_effectuees)): ?>
                                <p class="text-success">Toutes les formations ont été effectuées !</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Intitulé</th>
                                                <th>Catégorie</th>
                                                <th>Périodicité</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($formations_non_effectuees as $fne): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($fne['code']) ?></td>
                                                    <td><?= htmlspecialchars($fne['intitule']) ?></td>
                                                    <td><?= htmlspecialchars($fne['categorie']) ?></td>
                                                    <td><?= $fne['periodicite_mois'] ? $fne['periodicite_mois'] . ' mois' : 'N/A' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="rapports_formations.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Retour
                    </a>
                    <a href="?action=download_agent&agent_id=<?= $agent_data['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-file-csv me-1"></i>
                        Télécharger CSV
                    </a>
                    <a href="?action=download_pdf_agent&agent_id=<?= $agent_data['id'] ?>" class="btn btn-danger">
                        <i class="fas fa-file-pdf me-1"></i>
                        Télécharger PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
