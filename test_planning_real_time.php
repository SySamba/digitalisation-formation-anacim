<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔍 Test Planning Temps Réel</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; }
    .btn { padding: 8px 16px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
    .btn-primary { background: #124c97; color: white; }
    .btn-danger { background: #dc3545; color: white; }
    .refresh { background: #28a745; color: white; }
</style>";

// Auto-refresh toutes les 5 secondes
echo "<script>
    let autoRefresh = false;
    function toggleAutoRefresh() {
        autoRefresh = !autoRefresh;
        const btn = document.getElementById('refreshBtn');
        if (autoRefresh) {
            btn.textContent = '⏸️ Arrêter Auto-Refresh';
            btn.className = 'btn btn-danger';
            setInterval(() => {
                if (autoRefresh) window.location.reload();
            }, 5000);
        } else {
            btn.textContent = '🔄 Auto-Refresh (5s)';
            btn.className = 'btn refresh';
        }
    }
</script>";

echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<button onclick='window.location.reload()' class='btn btn-primary'>🔄 Actualiser Maintenant</button>";
echo "<button id='refreshBtn' onclick='toggleAutoRefresh()' class='btn refresh'>🔄 Auto-Refresh (5s)</button>";
echo "<a href='admin.php' class='btn btn-primary'>← Admin</a>";
echo "</div>";

// 1. État actuel de la base
echo "<h3>📊 État Actuel de la Base</h3>";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM planning_formations");
$total_plannings = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM planning_formations WHERE commentaires NOT LIKE '%Test%'");
$real_plannings = $stmt->fetch()['total'];

echo "<div class='info'>";
echo "📈 Total plannings: <strong>{$total_plannings}</strong><br>";
echo "✅ Vrais plannings: <strong>{$real_plannings}</strong><br>";
echo "🧪 Plannings de test: <strong>" . ($total_plannings - $real_plannings) . "</strong>";
echo "</div>";

// 2. Derniers plannings créés
echo "<h3>🕒 Derniers Plannings Créés</h3>";
$stmt = $pdo->query("
    SELECT 
        pf.id,
        pf.agent_id,
        pf.formation_id,
        pf.created_at,
        pf.commentaires,
        a.nom,
        a.prenom,
        f.code,
        f.intitule
    FROM planning_formations pf
    JOIN agents a ON pf.agent_id = a.id
    JOIN formations f ON pf.formation_id = f.id
    ORDER BY pf.created_at DESC
    LIMIT 5
");
$recent_plannings = $stmt->fetchAll();

if (empty($recent_plannings)) {
    echo "<div class='warning'>⚠️ Aucun planning dans la base</div>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Agent</th><th>Formation</th><th>Créé le</th><th>Type</th></tr>";
    
    foreach ($recent_plannings as $p) {
        $is_test = strpos($p['commentaires'], 'Test') !== false;
        $type = $is_test ? "🧪 Test" : "✅ Réel";
        $row_style = $is_test ? 'style="background-color: #fff3cd;"' : 'style="background-color: #d4edda;"';
        
        echo "<tr {$row_style}>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['nom']} {$p['prenom']}</td>";
        echo "<td>{$p['code']} - " . substr($p['intitule'], 0, 30) . "...</td>";
        echo "<td>" . date('d/m/Y H:i:s', strtotime($p['created_at'])) . "</td>";
        echo "<td>{$type}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Test de détection pour agent spécifique
echo "<h3>🎯 Test Détection Agent 5 (BA Coutaille)</h3>";

$agent_id = 5;

// Formations effectuées
$stmt = $pdo->prepare("SELECT formation_id FROM formations_agents WHERE agent_id = ?");
$stmt->execute([$agent_id]);
$formations_effectuees_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Test de la requête exacte utilisée dans get_agent_details.php
$stmt = $pdo->prepare("
    SELECT f.id, f.code, f.intitule,
           (SELECT COUNT(*) FROM planning_formations pf 
            WHERE pf.agent_id = ? AND pf.formation_id = f.id 
            AND pf.statut IN ('planifie', 'confirme')) as est_planifie
    FROM formations f 
    WHERE f.id NOT IN (" . (empty($formations_effectuees_ids) ? "0" : implode(',', $formations_effectuees_ids)) . ")
    ORDER BY f.code
    LIMIT 10
");
$stmt->execute([$agent_id]);
$formations_test = $stmt->fetchAll();

echo "<div class='info'>🔍 Test sur les 10 premières formations non effectuées :</div>";

if (!empty($formations_test)) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Code</th><th>Intitulé</th><th>Planifiée?</th><th>Statut Attendu</th></tr>";
    
    foreach ($formations_test as $f) {
        $is_fts = strpos($f['code'], 'SUR-FTS') !== false;
        $est_planifie = $f['est_planifie'] > 0;
        
        if ($est_planifie && !$is_fts) {
            $statut_attendu = "🚫 Déjà planifié";
            $row_style = 'style="background-color: #f8d7da;"';
        } elseif ($est_planifie && $is_fts) {
            $statut_attendu = "⚠️ Re-planifier (SUR-FTS)";
            $row_style = 'style="background-color: #fff3cd;"';
        } else {
            $statut_attendu = "✅ Planifier";
            $row_style = 'style="background-color: #d4edda;"';
        }
        
        echo "<tr {$row_style}>";
        echo "<td>{$f['id']}</td>";
        echo "<td>{$f['code']}</td>";
        echo "<td>" . substr($f['intitule'], 0, 40) . "...</td>";
        echo "<td>" . ($est_planifie ? "OUI ({$f['est_planifie']})" : "NON") . "</td>";
        echo "<td>{$statut_attendu}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. Instructions pour test manuel
echo "<h3>📋 Instructions Test Manuel</h3>";
echo "<div class='warning'>";
echo "<strong>Pour tester le système :</strong><br>";
echo "1. Ouvrez <a href='admin.php' target='_blank'>admin.php</a> dans un nouvel onglet<br>";
echo "2. Cliquez sur 'Voir Plus' pour l'agent BA Coutaille<br>";
echo "3. Allez dans l'onglet 'Non Effectuées'<br>";
echo "4. Planifiez une formation (utilisez de vraies données, pas 'Test')<br>";
echo "5. Revenez ici et actualisez cette page<br>";
echo "6. Vérifiez que la formation apparaît dans le tableau ci-dessus avec 'OUI (1)'<br>";
echo "</div>";

echo "<div class='info'>";
echo "<strong>Heure actuelle :</strong> " . date('d/m/Y H:i:s') . "<br>";
echo "<strong>Dernière actualisation :</strong> <span id='lastUpdate'>" . date('H:i:s') . "</span>";
echo "</div>";

echo "<script>document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();</script>";
?>
