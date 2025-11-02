<?php
session_start();
require_once 'config/database.php';

$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 1;

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>Test des corrections pour l'agent ID: $agent_id</h2>";

// Test 1: Formations effectuées (nouvelle requête UNION)
echo "<h3>1. Formations effectuées (nouvelle requête UNION)</h3>";
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

echo "Nombre de formations effectuées: " . count($formations_effectuees) . "<br>";
foreach ($formations_effectuees as $formation) {
    echo "- " . $formation['code'] . ": " . $formation['intitule'] . " (Source: " . $formation['source_table'] . ")<br>";
}

// Test 2: Formations planifiées
echo "<h3>2. Formations planifiées</h3>";
$stmt_pf = $pdo->prepare("SELECT pf.*, f.code, f.intitule, f.categorie, f.periodicite_mois FROM planning_formations pf JOIN formations f ON pf.formation_id = f.id WHERE pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme') ORDER BY pf.date_prevue_debut ASC");
$stmt_pf->execute([$agent_id]);
$formations_planifiees = $stmt_pf->fetchAll();

echo "Nombre de formations planifiées: " . count($formations_planifiees) . "<br>";
foreach ($formations_planifiees as $formation) {
    echo "- " . $formation['code'] . ": " . $formation['intitule'] . " (Statut: " . $formation['statut'] . ")<br>";
}

// Test 3: Formations non effectuées (nouvelle requête)
echo "<h3>3. Formations non effectuées (nouvelle requête)</h3>";
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

echo "Nombre de formations non effectuées: " . count($formations_non_effectuees) . "<br>";
foreach ($formations_non_effectuees as $formation) {
    echo "- " . $formation['code'] . ": " . $formation['intitule'] . "<br>";
}

// Résumé
echo "<hr>";
echo "<h3>Résumé pour l'agent $agent_id</h3>";
echo "<p><strong>Formations effectuées:</strong> " . count($formations_effectuees) . "</p>";
echo "<p><strong>Formations planifiées:</strong> " . count($formations_planifiees) . "</p>";
echo "<p><strong>Formations non effectuées:</strong> " . count($formations_non_effectuees) . "</p>";

echo "<hr>";
echo "<h3>Test des liens</h3>";
echo "<a href='ajax/generate_rapport_agent.php?agent_id=$agent_id&format=pdf' target='_blank'>Générer rapport PDF</a><br>";
echo "<a href='admin.php' target='_blank'>Retour à admin.php</a><br>";

// Test avec différents agents
echo "<hr>";
echo "<h3>Tester avec d'autres agents</h3>";
$query_agents = "SELECT id, prenom, nom, matricule FROM agents LIMIT 5";
$stmt_agents = $pdo->prepare($query_agents);
$stmt_agents->execute();
$agents_list = $stmt_agents->fetchAll();

foreach ($agents_list as $agent) {
    echo "<a href='?agent_id=" . $agent['id'] . "'>" . $agent['prenom'] . " " . $agent['nom'] . " (ID: " . $agent['id'] . ")</a><br>";
}
?>
