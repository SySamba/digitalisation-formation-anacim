<?php
session_start();
require_once 'config/database.php';

// Agent spécifique avec le code 2017081JD
$agent_code = '2017081JD';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Debug pour l'agent avec le code: $agent_code</h2>";

// Trouver l'agent par son matricule/code
$stmt_agent = $pdo->prepare("SELECT id, matricule, prenom, nom, email FROM agents WHERE matricule = ? OR email = 'sambasy837@gmail.com'");
$stmt_agent->execute([$agent_code]);
$agent = $stmt_agent->fetch();

if (!$agent) {
    echo "<p style='color: red;'>❌ Agent non trouvé avec le code $agent_code</p>";
    exit;
}

$agent_id = $agent['id'];
echo "<p><strong>Agent trouvé:</strong> {$agent['prenom']} {$agent['nom']} (ID: $agent_id, Email: {$agent['email']})</p>";

// Test 1: Toutes les formations effectuées par cet agent
echo "<h3>1. 📋 Toutes les formations effectuées</h3>";
$stmt_effectuees = $pdo->prepare("
    (SELECT fe.*, f.intitule, f.code, f.periodicite_mois,
            DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
            DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
            'formations_effectuees' as source_table
     FROM formations_effectuees fe
     JOIN formations f ON fe.formation_id = f.id
     WHERE fe.agent_id = ?)
    
    UNION ALL
    
    (SELECT fa.*, f.intitule, f.code, f.periodicite_mois,
            DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance,
            DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
            'formations_agents' as source_table
     FROM formations_agents fa
     JOIN formations f ON fa.formation_id = f.id
     WHERE fa.agent_id = ?)
    
    ORDER BY date_fin DESC
");
$stmt_effectuees->execute([$agent_id, $agent_id]);
$formations_effectuees = $stmt_effectuees->fetchAll();

if (empty($formations_effectuees)) {
    echo "<p style='color: orange;'>⚠️ Aucune formation effectuée trouvée</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Périodicité</th><th>Prochaine Échéance</th><th>Jours Restants</th><th>Source</th></tr>";
    foreach ($formations_effectuees as $formation) {
        $couleur = $formation['jours_restants'] <= 730 ? 'red' : ($formation['jours_restants'] <= 1095 ? 'orange' : 'green');
        echo "<tr style='color: $couleur;'>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 50) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . $formation['periodicite_mois'] . " mois</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['prochaine_echeance'])) . "</td>";
        echo "<td><strong>" . $formation['jours_restants'] . " jours</strong></td>";
        echo "<td>" . $formation['source_table'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 2: Formations à renouveler avec la logique actuelle (2 ans)
echo "<h3>2. 🔄 Formations à renouveler (logique actuelle - 2 ans)</h3>";
$stmt_renouveler_2ans = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_effectuees' as source_table
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ? 
    AND fe.statut IN ('termine', 'valide')
    AND f.periodicite_mois > 0
    AND (
        (f.code LIKE 'SUR-FTS-%' AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 24 MONTH))
        OR
        (f.code NOT LIKE 'SUR-FTS-%' AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH))
    )
    
    UNION ALL
    
    SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_agents' as source_table
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ? 
    AND fa.statut IN ('termine', 'valide')
    AND f.periodicite_mois > 0
    AND (
        (f.code LIKE 'SUR-FTS-%' AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 24 MONTH))
        OR
        (f.code NOT LIKE 'SUR-FTS-%' AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH))
    )
    
    ORDER BY jours_restants ASC
");
$stmt_renouveler_2ans->execute([$agent_id, $agent_id]);
$formations_renouveler_2ans = $stmt_renouveler_2ans->fetchAll();

echo "<p><strong>Nombre trouvé avec logique 2 ans: " . count($formations_renouveler_2ans) . "</strong></p>";

// Test 3: Formations à renouveler avec logique étendue (3 ans)
echo "<h3>3. 🔄 Formations à renouveler (logique étendue - 3 ans)</h3>";
$stmt_renouveler_3ans = $pdo->prepare("
    SELECT fe.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_effectuees' as source_table
    FROM formations_effectuees fe
    JOIN formations f ON fe.formation_id = f.id
    WHERE fe.agent_id = ? 
    AND fe.statut IN ('termine', 'valide')
    AND f.periodicite_mois > 0
    AND (
        (f.code LIKE 'SUR-FTS-%' AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH))
        OR
        (f.code NOT LIKE 'SUR-FTS-%' AND DATE_ADD(fe.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
    )
    
    UNION ALL
    
    SELECT fa.*, f.intitule, f.code, f.categorie, f.periodicite_mois,
           DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) as prochaine_echeance_calculee,
           DATEDIFF(DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH), CURDATE()) as jours_restants,
           'formations_agents' as source_table
    FROM formations_agents fa
    JOIN formations f ON fa.formation_id = f.id
    WHERE fa.agent_id = ? 
    AND fa.statut IN ('termine', 'valide')
    AND f.periodicite_mois > 0
    AND (
        (f.code LIKE 'SUR-FTS-%' AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH))
        OR
        (f.code NOT LIKE 'SUR-FTS-%' AND DATE_ADD(fa.date_fin, INTERVAL f.periodicite_mois MONTH) <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
    )
    
    ORDER BY jours_restants ASC
");
$stmt_renouveler_3ans->execute([$agent_id, $agent_id]);
$formations_renouveler_3ans = $stmt_renouveler_3ans->fetchAll();

echo "<p><strong>Nombre trouvé avec logique 3 ans: " . count($formations_renouveler_3ans) . "</strong></p>";

if (!empty($formations_renouveler_3ans)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Code</th><th>Intitulé</th><th>Date Fin</th><th>Prochaine Échéance</th><th>Jours Restants</th><th>Priorité</th></tr>";
    foreach ($formations_renouveler_3ans as $formation) {
        $priorite = $formation['jours_restants'] <= 365 ? 'URGENT' : ($formation['jours_restants'] <= 730 ? 'HAUTE' : 'MOYENNE');
        $couleur = $formation['jours_restants'] <= 365 ? 'red' : ($formation['jours_restants'] <= 730 ? 'orange' : 'blue');
        echo "<tr style='color: $couleur;'>";
        echo "<td><strong>" . $formation['code'] . "</strong></td>";
        echo "<td>" . substr($formation['intitule'], 0, 40) . "...</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['date_fin'])) . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($formation['prochaine_echeance_calculee'])) . "</td>";
        echo "<td><strong>" . $formation['jours_restants'] . " jours</strong></td>";
        echo "<td><strong>$priorite</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Calculs de dates pour comprendre
echo "<h3>4. 📅 Calculs de dates</h3>";
$aujourd_hui = date('Y-m-d');
$dans_2_ans = date('Y-m-d', strtotime('+24 months'));
$dans_3_ans = date('Y-m-d', strtotime('+36 months'));

echo "<p><strong>Aujourd'hui:</strong> " . date('d/m/Y', strtotime($aujourd_hui)) . "</p>";
echo "<p><strong>Dans 2 ans (24 mois):</strong> " . date('d/m/Y', strtotime($dans_2_ans)) . "</p>";
echo "<p><strong>Dans 3 ans (36 mois):</strong> " . date('d/m/Y', strtotime($dans_3_ans)) . "</p>";
echo "<p><strong>Formation expire le:</strong> 12/09/2028</p>";
echo "<p><strong>1044 jours = </strong>" . round(1044/365, 1) . " ans</p>";

echo "<hr>";
echo "<h3>🔗 Liens de test</h3>";
echo "<a href='admin.php' target='_blank'>Retour à admin.php</a><br>";
echo "<a href='ajax/get_agent_details.php?id=$agent_id' target='_blank'>Voir détail agent</a><br>";
echo "<a href='test_formations_techniques.php?agent_id=$agent_id' target='_blank'>Test formations techniques</a><br>";
?>
