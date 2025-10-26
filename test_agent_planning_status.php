<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Test Statut Planification Agent</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #f5c6cb; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ffeaa7; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #bee5eb; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .highlight { background-color: #ffeb3b !important; }
    .btn { padding: 8px 16px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
    .btn-primary { background: #124c97; color: white; }
    .btn-secondary { background: #6c757d; color: white; }
    .btn-warning { background: #ffc107; color: black; }
</style>";

// Sélectionner un agent pour le test
$agent_id = $_GET['agent_id'] ?? null;

if (!$agent_id) {
    echo "<h3>Sélectionner un agent pour le test</h3>";
    $stmt = $pdo->query("SELECT id, nom, prenom FROM agents ORDER BY nom, prenom");
    $agents = $stmt->fetchAll();
    
    echo "<div class='info'>";
    foreach ($agents as $agent) {
        echo "<a href='?agent_id={$agent['id']}' class='btn btn-primary'>";
        echo "Agent {$agent['id']}: {$agent['nom']} {$agent['prenom']}</a> ";
    }
    echo "</div>";
    exit;
}

// Récupérer les infos de l'agent
$stmt = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
$stmt->execute([$agent_id]);
$agent = $stmt->fetch();

if (!$agent) {
    echo "<div class='error'>❌ Agent non trouvé</div>";
    exit;
}

echo "<h3>Agent testé: {$agent['nom']} {$agent['prenom']} (ID: {$agent_id})</h3>";

// 1. Test de la requête SQL exacte utilisée dans get_agent_details.php
echo "<h4>1️⃣ Test de la requête SQL pour formations non effectuées</h4>";

$stmt_all_formations = $pdo->prepare("
    SELECT f.*, 
           (SELECT COUNT(*) FROM planning_formations pf 
            WHERE pf.agent_id = ? AND pf.formation_id = f.id 
            AND pf.statut IN ('planifie', 'confirme')) as est_planifie
    FROM formations f 
    ORDER BY f.code
");
$stmt_all_formations->execute([$agent_id]);
$all_formations = $stmt_all_formations->fetchAll();

echo "<div class='info'>📊 Total formations: " . count($all_formations) . "</div>";

// Récupérer les formations effectuées
$stmt_formations = $pdo->prepare("SELECT fa.*, f.code, f.intitule FROM formations_agents fa JOIN formations f ON fa.formation_id = f.id WHERE fa.agent_id = ? ORDER BY fa.created_at DESC");
$stmt_formations->execute([$agent_id]);
$formations_effectuees = $stmt_formations->fetchAll();

$formations_effectuees_ids = array_column($formations_effectuees, 'formation_id');

// Filtrer les formations non effectuées
$formations_non_effectuees = array_filter($all_formations, function($formation) use ($formations_effectuees_ids) {
    return !in_array($formation['id'], $formations_effectuees_ids);
});

echo "<div class='info'>📊 Formations effectuées: " . count($formations_effectuees) . "</div>";
echo "<div class='info'>📊 Formations non effectuées: " . count($formations_non_effectuees) . "</div>";

// 2. Afficher les formations avec leur statut de planification
echo "<h4>2️⃣ Formations non effectuées avec statut de planification</h4>";

if (empty($formations_non_effectuees)) {
    echo "<div class='success'>✅ Toutes les formations ont été effectuées par cet agent</div>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th><th>Est Planifié</th><th>Type</th><th>Action Recommandée</th></tr>";
    
    foreach ($formations_non_effectuees as $formation) {
        $is_fts = strpos($formation['code'], 'SUR-FTS') !== false;
        $est_planifie = isset($formation['est_planifie']) && $formation['est_planifie'] > 0;
        
        $action = '';
        if ($est_planifie && !$is_fts) {
            $action = '<span class="btn btn-secondary">Déjà planifié</span>';
        } elseif ($est_planifie && $is_fts) {
            $action = '<span class="btn btn-warning">Re-planifier (SUR-FTS)</span>';
        } else {
            $action = '<span class="btn btn-primary">Planifier</span>';
        }
        
        $row_class = $est_planifie ? 'class="highlight"' : '';
        
        echo "<tr {$row_class}>";
        echo "<td>{$formation['id']}</td>";
        echo "<td>{$formation['code']}</td>";
        echo "<td>" . substr($formation['intitule'], 0, 50) . "...</td>";
        echo "<td>" . ($est_planifie ? "OUI ({$formation['est_planifie']})" : "NON") . "</td>";
        echo "<td>" . ($is_fts ? "SUR-FTS" : "Autre") . "</td>";
        echo "<td>{$action}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Vérifier les planifications existantes pour cet agent
echo "<h4>3️⃣ Planifications existantes pour cet agent</h4>";

$stmt = $pdo->prepare("
    SELECT pf.*, f.code, f.intitule 
    FROM planning_formations pf 
    JOIN formations f ON pf.formation_id = f.id 
    WHERE pf.agent_id = ? 
    ORDER BY pf.created_at DESC
");
$stmt->execute([$agent_id]);
$planifications = $stmt->fetchAll();

if (empty($planifications)) {
    echo "<div class='warning'>⚠️ Aucune planification trouvée pour cet agent</div>";
} else {
    echo "<div class='success'>✅ " . count($planifications) . " planification(s) trouvée(s)</div>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Formation</th><th>Dates</th><th>Statut</th><th>Créé le</th></tr>";
    
    foreach ($planifications as $p) {
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['code']} - " . substr($p['intitule'], 0, 40) . "...</td>";
        echo "<td>Du " . date('d/m/Y', strtotime($p['date_prevue_debut'])) . " au " . date('d/m/Y', strtotime($p['date_prevue_fin'])) . "</td>";
        echo "<td>{$p['statut']}</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($p['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. Test de création d'une planification pour voir l'effet
echo "<h4>4️⃣ Test de planification</h4>";

if ($_POST && isset($_POST['create_planning'])) {
    $formation_id = $_POST['formation_id'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO planning_formations 
            (agent_id, formation_id, date_prevue_debut, date_prevue_fin, centre_formation_prevu, 
             ville, pays, duree, perdiem, priorite, statut, commentaires, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $agent_id,
            $formation_id,
            date('Y-m-d', strtotime('+10 days')),
            date('Y-m-d', strtotime('+15 days')),
            'Test Centre',
            'Test Ville',
            'Test Pays',
            5,
            50000,
            '2',
            'planifie',
            'Test planification - ' . date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            echo "<div class='success'>✅ Planification créée avec succès! Rechargez la page pour voir l'effet.</div>";
            echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
    }
} else {
    if (!empty($formations_non_effectuees)) {
        echo "<form method='POST'>";
        echo "<label>Créer une planification test pour:</label><br>";
        echo "<select name='formation_id' required>";
        echo "<option value=''>Sélectionner une formation</option>";
        foreach (array_slice($formations_non_effectuees, 0, 5) as $formation) {
            $est_planifie = isset($formation['est_planifie']) && $formation['est_planifie'] > 0;
            $status = $est_planifie ? " (Déjà planifiée)" : "";
            echo "<option value='{$formation['id']}'>{$formation['code']} - " . substr($formation['intitule'], 0, 50) . "{$status}</option>";
        }
        echo "</select><br><br>";
        echo "<button type='submit' name='create_planning' class='btn btn-primary'>🧪 Créer planification test</button>";
        echo "</form>";
    }
}

echo "<br><br>";
echo "<a href='admin.php' class='btn btn-primary'>← Retour Admin</a> ";
echo "<a href='test_planning_debug.php' class='btn btn-secondary'>← Diagnostic Principal</a> ";
echo "<a href='?agent_id=' class='btn btn-secondary'>Changer d'agent</a>";
?>
