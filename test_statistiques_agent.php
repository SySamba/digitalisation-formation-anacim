<?php
session_start();
require_once 'config/database.php';

$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 1;

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>Test des statistiques pour l'agent ID: $agent_id</h2>";

// Test 1: Formations effectuées
echo "<h3>1. Formations effectuées</h3>";
$stmt_formations = $pdo->prepare("
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
    
    ORDER BY date_fin DESC
");
$stmt_formations->execute([$agent_id, $agent_id]);
$formations_effectuees = $stmt_formations->fetchAll();
echo "✅ Formations effectuées: " . count($formations_effectuees) . "<br>";

// Test 2: Formations non effectuées
echo "<h3>2. Formations non effectuées</h3>";
$stmt_nf = $pdo->prepare("
    SELECT DISTINCT f.id, f.intitule, f.code, f.categorie, f.periodicite_mois
    FROM formations f
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
    ORDER BY f.categorie, f.code
");
$stmt_nf->execute([$agent_id, $agent_id, $agent_id]);
$formations_non_effectuees = $stmt_nf->fetchAll();
echo "✅ Formations non effectuées: " . count($formations_non_effectuees) . "<br>";

// Test 3: Formations à renouveler
echo "<h3>3. Formations à renouveler</h3>";
$stmt_renouveler = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ? 
    AND fe.statut IN ('termine', 'valide')
    AND f.periodicite_mois > 0
    AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    
    UNION ALL
    
    SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ? 
    AND fa.statut IN ('termine', 'valide')
    AND f.periodicite_mois > 0
    AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    
    ORDER BY jours_restants ASC
");
$stmt_renouveler->execute([$agent_id, $agent_id]);
$formations_a_renouveler = $stmt_renouveler->fetchAll();
echo "✅ Formations à renouveler: " . count($formations_a_renouveler) . "<br>";

// Test 4: Formations planifiées
echo "<h3>4. Formations planifiées</h3>";
$stmt_pf = $pdo->prepare("SELECT pf.*, f.code, f.intitule, f.categorie, f.periodicite_mois FROM planning_formations pf JOIN formations f ON pf.formation_id = f.id WHERE pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme') ORDER BY pf.date_prevue_debut ASC");
$stmt_pf->execute([$agent_id]);
$formations_planifiees = $stmt_pf->fetchAll();
echo "✅ Formations planifiées: " . count($formations_planifiees) . "<br>";

// Affichage des statistiques comme dans le rapport
echo "<hr>";
echo "<h3>📊 Statistiques finales (comme dans le rapport détail agent)</h3>";
echo "<div style='display: flex; gap: 20px; flex-wrap: wrap;'>";

echo "<div style='border: 2px solid #28a745; padding: 15px; text-align: center; min-width: 150px;'>";
echo "<i class='fas fa-check-circle' style='color: #28a745; font-size: 2em;'></i><br>";
echo "<h4 style='color: #28a745; margin: 10px 0;'>" . count($formations_effectuees) . "</h4>";
echo "<small>Formations Effectuées</small>";
echo "</div>";

echo "<div style='border: 2px solid #dc3545; padding: 15px; text-align: center; min-width: 150px;'>";
echo "<i class='fas fa-times-circle' style='color: #dc3545; font-size: 2em;'></i><br>";
echo "<h4 style='color: #dc3545; margin: 10px 0;'>" . count($formations_non_effectuees) . "</h4>";
echo "<small>Non Effectuées</small>";
echo "</div>";

echo "<div style='border: 2px solid #ffc107; padding: 15px; text-align: center; min-width: 150px;'>";
echo "<i class='fas fa-exclamation-triangle' style='color: #ffc107; font-size: 2em;'></i><br>";
echo "<h4 style='color: #ffc107; margin: 10px 0;'>" . count($formations_a_renouveler) . "</h4>";
echo "<small>À Renouveler</small>";
echo "</div>";

echo "<div style='border: 2px solid #17a2b8; padding: 15px; text-align: center; min-width: 150px;'>";
echo "<i class='fas fa-calendar' style='color: #17a2b8; font-size: 2em;'></i><br>";
echo "<h4 style='color: #17a2b8; margin: 10px 0;'>" . count($formations_planifiees) . "</h4>";
echo "<small>Planifiées</small>";
echo "</div>";

echo "</div>";

// Détail des formations à renouveler
if (!empty($formations_a_renouveler)) {
    echo "<hr>";
    echo "<h3>🔄 Détail des formations à renouveler</h3>";
    foreach ($formations_a_renouveler as $formation) {
        $priorite = $formation['jours_restants'] <= 0 ? 'URGENT' : ($formation['jours_restants'] <= 30 ? 'HAUTE' : 'MOYENNE');
        $couleur = $formation['jours_restants'] <= 0 ? 'red' : ($formation['jours_restants'] <= 30 ? 'orange' : 'blue');
        echo "<p style='color: $couleur;'><strong>$priorite:</strong> " . $formation['code'] . " - " . $formation['intitule'] . " (Échéance dans " . $formation['jours_restants'] . " jours)</p>";
    }
}

echo "<hr>";
echo "<h3>🔗 Liens de test</h3>";
echo "<a href='admin.php' target='_blank'>Retour à admin.php</a><br>";
echo "<a href='ajax/get_agent_details.php?id=$agent_id' target='_blank'>Tester get_agent_details.php directement</a><br>";

// Test avec différents agents
echo "<hr>";
echo "<h3>👥 Tester avec d'autres agents</h3>";
$query_agents = "SELECT id, prenom, nom, matricule FROM agents LIMIT 5";
$stmt_agents = $pdo->prepare($query_agents);
$stmt_agents->execute();
$agents_list = $stmt_agents->fetchAll();

foreach ($agents_list as $agent) {
    echo "<a href='?agent_id=" . $agent['id'] . "'>" . $agent['prenom'] . " " . $agent['nom'] . " (ID: " . $agent['id'] . ")</a><br>";
}
?>
