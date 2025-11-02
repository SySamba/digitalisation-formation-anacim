<?php
session_start();
require_once 'config/database.php';

// Test avec un agent spécifique - remplacez par un ID d'agent existant
$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 1; // Changez cet ID selon vos données

$database = new Database();
$db = $database->getConnection();

echo "<h2>Debug des formations pour l'agent ID: $agent_id</h2>";

// Test 1: Vérifier les formations effectuées
echo "<h3>1. Formations effectuées (table formations_effectuees)</h3>";
$query1 = "SELECT fe.*, f.intitule, f.code FROM formations_effectuees fe JOIN formations f ON fe.formation_id = f.id WHERE fe.agent_id = ?";
$stmt1 = $db->prepare($query1);
$stmt1->execute([$agent_id]);
$result1 = $stmt1->fetchAll();
echo "Nombre trouvé: " . count($result1) . "<br>";
foreach ($result1 as $row) {
    echo "- " . $row['code'] . ": " . $row['intitule'] . " (Date fin: " . $row['date_fin'] . ")<br>";
}

// Test 2: Vérifier les formations agents
echo "<h3>2. Formations effectuées (table formations_agents)</h3>";
$query2 = "SELECT fa.*, f.intitule, f.code FROM formations_agents fa JOIN formations f ON fa.formation_id = f.id WHERE fa.agent_id = ?";
$stmt2 = $db->prepare($query2);
$stmt2->execute([$agent_id]);
$result2 = $stmt2->fetchAll();
echo "Nombre trouvé: " . count($result2) . "<br>";
foreach ($result2 as $row) {
    echo "- " . $row['code'] . ": " . $row['intitule'] . " (Date fin: " . $row['date_fin'] . ")<br>";
}

// Test 3: Vérifier la requête UNION (comme dans le code)
echo "<h3>3. Requête UNION complète</h3>";
$query3 = "
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

$stmt3 = $db->prepare($query3);
$stmt3->execute([$agent_id, $agent_id]);
$result3 = $stmt3->fetchAll();
echo "Nombre total trouvé: " . count($result3) . "<br>";
foreach ($result3 as $row) {
    echo "- " . $row['code'] . ": " . $row['intitule'] . " (Source: " . $row['source_table'] . ", Date fin: " . $row['date_fin'] . ")<br>";
}

// Test 4: Vérifier les formations planifiées
echo "<h3>4. Formations planifiées</h3>";
$query4 = "SELECT pf.*, f.intitule, f.code FROM planning_formations pf JOIN formations f ON pf.formation_id = f.id WHERE pf.agent_id = ?";
$stmt4 = $db->prepare($query4);
$stmt4->execute([$agent_id]);
$result4 = $stmt4->fetchAll();
echo "Nombre trouvé: " . count($result4) . "<br>";
foreach ($result4 as $row) {
    echo "- " . $row['code'] . ": " . $row['intitule'] . " (Statut: " . $row['statut'] . ")<br>";
}

// Test 5: Vérifier toutes les formations disponibles
echo "<h3>5. Toutes les formations disponibles</h3>";
$query5 = "SELECT COUNT(*) as total FROM formations";
$stmt5 = $db->prepare($query5);
$stmt5->execute();
$result5 = $stmt5->fetch();
echo "Total formations dans la base: " . $result5['total'] . "<br>";

// Test 6: Vérifier les agents
echo "<h3>6. Vérifier l'agent</h3>";
$query6 = "SELECT * FROM agents WHERE id = ?";
$stmt6 = $db->prepare($query6);
$stmt6->execute([$agent_id]);
$result6 = $stmt6->fetch();
if ($result6) {
    echo "Agent trouvé: " . $result6['prenom'] . " " . $result6['nom'] . " (Matricule: " . $result6['matricule'] . ")<br>";
} else {
    echo "Agent non trouvé !<br>";
}

// Test 7: Tester les nouvelles fonctions améliorées
echo "<h3>7. Test des nouvelles fonctions améliorées (copie des fonctions)</h3>";

// Copie des fonctions améliorées pour test
function getFormationsEffectueesByAgent_Test($db, $agent_id) {
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

$formations_effectuees_ameliorees = getFormationsEffectueesByAgent_Test($db, $agent_id);
echo "Nombre de formations effectuées (fonction améliorée): " . count($formations_effectuees_ameliorees) . "<br>";
foreach ($formations_effectuees_ameliorees as $row) {
    echo "- " . $row['code'] . ": " . $row['intitule'] . " (Source: " . $row['source_table'] . ")<br>";
}

echo "<hr>";
echo "<h3>Test du générateur de rapport</h3>";
echo "<a href='ajax/generate_rapport_agent.php?agent_id=$agent_id&format=pdf' target='_blank'>Générer rapport PDF</a><br>";
echo "<a href='ajax/generate_rapport_agent.php?agent_id=$agent_id&format=word' target='_blank'>Générer rapport Word</a><br>";
echo "<a href='rapports_formations.php?action=view_agent&agent_id=$agent_id' target='_blank'>Voir rapport via rapports_formations.php</a><br>";

// Test avec différents agents
echo "<hr>";
echo "<h3>Tester avec d'autres agents</h3>";
$query_agents = "SELECT id, prenom, nom, matricule FROM agents LIMIT 5";
$stmt_agents = $db->prepare($query_agents);
$stmt_agents->execute();
$agents_list = $stmt_agents->fetchAll();

foreach ($agents_list as $agent) {
    echo "<a href='?agent_id=" . $agent['id'] . "'>" . $agent['prenom'] . " " . $agent['nom'] . " (ID: " . $agent['id'] . ")</a><br>";
}
?>
