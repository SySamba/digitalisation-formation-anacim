<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Debug Planning Simple</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; }
</style>";

// 1. Vérifier tous les plannings
echo "<h3>1️⃣ Tous les plannings dans la base</h3>";
$stmt = $pdo->query("
    SELECT 
        pf.id,
        pf.agent_id,
        pf.formation_id,
        pf.statut,
        pf.commentaires,
        pf.created_at,
        a.nom,
        a.prenom,
        f.code,
        f.intitule
    FROM planning_formations pf
    JOIN agents a ON pf.agent_id = a.id
    JOIN formations f ON pf.formation_id = f.id
    ORDER BY pf.created_at DESC
");
$all_plannings = $stmt->fetchAll();

echo "<div class='info'>📊 Total plannings: " . count($all_plannings) . "</div>";

if (!empty($all_plannings)) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Agent</th><th>Formation</th><th>Statut</th><th>Commentaires</th><th>Créé le</th></tr>";
    
    foreach ($all_plannings as $p) {
        $is_test = strpos($p['commentaires'], 'Test') !== false;
        $row_style = $is_test ? 'style="background-color: #fff3cd;"' : '';
        
        echo "<tr {$row_style}>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['nom']} {$p['prenom']}</td>";
        echo "<td>{$p['code']} - " . substr($p['intitule'], 0, 40) . "...</td>";
        echo "<td>{$p['statut']}</td>";
        echo "<td>" . ($p['commentaires'] ?: 'Aucun') . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($p['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<div class='info'>🟡 Les lignes jaunes sont des plannings de test</div>";
}

// 2. Test de la requête utilisée dans get_agent_details.php
echo "<h3>2️⃣ Test requête section 'Planning Existant'</h3>";

$stmt_planning_section = $pdo->prepare("
    SELECT 
        pf.*,
        a.matricule,
        a.prenom,
        a.nom,
        f.code,
        f.intitule,
        cf.nom as centre_nom,
        YEAR(pf.date_prevue_debut) as annee_formation
    FROM planning_formations pf
    JOIN agents a ON pf.agent_id = a.id
    JOIN formations f ON pf.formation_id = f.id
    LEFT JOIN centres_formation cf ON pf.centre_formation_prevu = cf.nom
    WHERE pf.statut != 'annule'
    ORDER BY a.nom, a.prenom, pf.date_prevue_debut ASC
");
$stmt_planning_section->execute();
$planning_section = $stmt_planning_section->fetchAll();

echo "<div class='info'>📊 Plannings pour section 'Planning Existant': " . count($planning_section) . "</div>";

if (!empty($planning_section)) {
    echo "<table>";
    echo "<tr><th>Agent</th><th>Formation</th><th>Centre</th><th>Dates</th><th>Statut</th></tr>";
    
    foreach ($planning_section as $p) {
        echo "<tr>";
        echo "<td>{$p['matricule']} - {$p['prenom']} {$p['nom']}</td>";
        echo "<td>{$p['code']} - " . substr($p['intitule'], 0, 30) . "...</td>";
        echo "<td>" . ($p['centre_nom'] ?: $p['centre_formation_prevu']) . "</td>";
        echo "<td>Du " . date('d/m/Y', strtotime($p['date_prevue_debut'])) . " au " . date('d/m/Y', strtotime($p['date_prevue_fin'])) . "</td>";
        echo "<td>{$p['statut']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Vérifier les centres de formation
echo "<h3>3️⃣ Problème avec les centres de formation</h3>";

$stmt = $pdo->query("SELECT DISTINCT centre_formation_prevu FROM planning_formations WHERE centre_formation_prevu IS NOT NULL");
$centres_in_planning = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("SELECT nom FROM centres_formation");
$centres_in_table = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<div class='info'>";
echo "<strong>Centres dans planning_formations:</strong><br>";
foreach ($centres_in_planning as $centre) {
    echo "- " . htmlspecialchars($centre) . "<br>";
}
echo "</div>";

echo "<div class='info'>";
echo "<strong>Centres dans table centres_formation:</strong><br>";
foreach ($centres_in_table as $centre) {
    echo "- " . htmlspecialchars($centre) . "<br>";
}
echo "</div>";

// 4. Test spécifique pour un agent
echo "<h3>4️⃣ Test pour agent ID 5 (BA Coutaille)</h3>";

$agent_id = 5;
$stmt = $pdo->prepare("
    SELECT pf.*, f.code, f.intitule 
    FROM planning_formations pf 
    JOIN formations f ON pf.formation_id = f.id 
    WHERE pf.agent_id = ? 
    ORDER BY pf.created_at DESC
");
$stmt->execute([$agent_id]);
$agent_plannings = $stmt->fetchAll();

echo "<div class='info'>📊 Plannings pour agent 5: " . count($agent_plannings) . "</div>";

if (!empty($agent_plannings)) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Formation</th><th>Dates</th><th>Statut</th><th>Commentaires</th></tr>";
    
    foreach ($agent_plannings as $p) {
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['code']} - " . substr($p['intitule'], 0, 40) . "...</td>";
        echo "<td>Du " . date('d/m/Y', strtotime($p['date_prevue_debut'])) . " au " . date('d/m/Y', strtotime($p['date_prevue_fin'])) . "</td>";
        echo "<td>{$p['statut']}</td>";
        echo "<td>" . ($p['commentaires'] ?: 'Aucun') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 5. Supprimer les plannings de test
echo "<h3>5️⃣ Nettoyer les plannings de test</h3>";

if ($_POST && isset($_POST['clean_test'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM planning_formations WHERE commentaires LIKE '%Test%'");
        $result = $stmt->execute();
        $deleted = $stmt->rowCount();
        
        echo "<div class='success'>✅ {$deleted} planning(s) de test supprimé(s)</div>";
        echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<form method='POST'>";
    echo "<button type='submit' name='clean_test' onclick='return confirm(\"Supprimer tous les plannings de test ?\")' style='padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px;'>🗑️ Supprimer les plannings de test</button>";
    echo "</form>";
}

echo "<br><br>";
echo "<a href='admin.php' style='display: inline-block; padding: 10px 20px; background: #124c97; color: white; text-decoration: none; border-radius: 5px;'>← Retour Admin</a>";
?>
