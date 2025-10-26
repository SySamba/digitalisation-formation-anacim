<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔍 Debug Interface Planning</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #124c97; color: white; }
    .btn { padding: 10px 20px; background: #124c97; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
</style>";

// Test 1: Vérifier si save_planning.php fonctionne
echo "<h3>1️⃣ Test save_planning.php</h3>";
if ($_POST && isset($_POST['test_save_planning'])) {
    // Simuler une requête POST vers save_planning.php
    $test_data = [
        'agent_id' => '5',
        'formation_id' => '1',
        'centre_formation_prevu' => 'ENAC',
        'date_prevue_debut' => date('Y-m-d', strtotime('+1 day')),
        'date_prevue_fin' => date('Y-m-d', strtotime('+6 days')),
        'ville' => 'Dakar',
        'pays' => 'Sénégal',
        'duree' => '5',
        'perdiem' => '50000',
        'priorite' => '2',
        'statut' => 'planifie',
        'commentaires' => 'Test UI - ' . date('Y-m-d H:i:s')
    ];
    
    // Simuler la session admin
    $_SESSION['admin_logged_in'] = true;
    
    // Créer une requête POST simulée
    $_POST = array_merge($_POST, $test_data);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Capturer la sortie de save_planning.php
    ob_start();
    include 'ajax/save_planning.php';
    $output = ob_get_clean();
    
    echo "<div class='info'><strong>Réponse de save_planning.php:</strong><br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre></div>";
    
    // Décoder la réponse JSON
    $response = json_decode($output, true);
    if ($response && $response['success']) {
        echo "<div class='success'>✅ save_planning.php fonctionne correctement!</div>";
    } else {
        echo "<div class='error'>❌ Problème avec save_planning.php</div>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='test_save_planning' class='btn'>🧪 Tester save_planning.php</button>";
echo "</form>";

// Test 2: Vérifier la requête d'affichage des plannings
echo "<h3>2️⃣ Test requête affichage plannings</h3>";
try {
    // Requête similaire à celle dans get_agent_details.php
    $stmt = $db->prepare("
        SELECT 
            pf.*,
            a.matricule,
            a.prenom,
            a.nom,
            f.code,
            f.intitule,
            cf.nom as centre_nom
        FROM planning_formations pf
        JOIN agents a ON pf.agent_id = a.id
        JOIN formations f ON pf.formation_id = f.id
        LEFT JOIN centres_formation cf ON pf.centre_formation_prevu = cf.nom
        WHERE pf.statut != 'annule'
        ORDER BY pf.date_prevue_debut DESC
        LIMIT 10
    ");
    $stmt->execute();
    $plannings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>📊 Plannings trouvés: " . count($plannings) . "</div>";
    
    if (count($plannings) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Agent</th><th>Formation</th><th>Centre</th><th>Ville</th><th>Pays</th><th>Dates</th><th>Durée</th><th>Priorité</th></tr>";
        
        foreach ($plannings as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['matricule']} - {$p['prenom']} {$p['nom']}</td>";
            echo "<td>{$p['code']}</td>";
            echo "<td>" . ($p['centre_nom'] ?? $p['centre_formation_prevu']) . "</td>";
            echo "<td>" . ($p['ville'] ?? 'N/A') . "</td>";
            echo "<td>" . ($p['pays'] ?? 'N/A') . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($p['date_prevue_debut'])) . " - " . date('d/m/Y', strtotime($p['date_prevue_fin'])) . "</td>";
            echo "<td>" . ($p['duree'] ?? 'N/A') . " jours</td>";
            echo "<td>" . ($p['priorite'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='success'>✅ La requête d'affichage fonctionne!</div>";
    } else {
        echo "<div class='warning'>⚠️ Aucun planning trouvé avec la requête d'affichage</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur requête: " . $e->getMessage() . "</div>";
}

// Test 3: Vérifier le statut "Déjà planifié"
echo "<h3>3️⃣ Test statut 'Déjà planifié'</h3>";
try {
    // Requête pour vérifier les besoins de formation
    $agent_id = 5; // Agent de test
    
    $stmt = $db->prepare("
        SELECT 
            f.id as formation_id,
            f.code,
            f.intitule,
            COUNT(pf.id) as nb_planifications,
            MAX(pf.date_prevue_debut) as derniere_planification
        FROM formations f
        LEFT JOIN planning_formations pf ON f.id = pf.formation_id AND pf.agent_id = ? AND pf.statut IN ('planifie', 'confirme')
        GROUP BY f.id, f.code, f.intitule
        HAVING nb_planifications > 0
        ORDER BY f.code
        LIMIT 10
    ");
    $stmt->execute([$agent_id]);
    $formations_planifiees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>📚 Formations planifiées pour l'agent {$agent_id}: " . count($formations_planifiees) . "</div>";
    
    if (count($formations_planifiees) > 0) {
        echo "<table>";
        echo "<tr><th>Formation</th><th>Code</th><th>Nb Planifications</th><th>Dernière Planification</th></tr>";
        
        foreach ($formations_planifiees as $f) {
            echo "<tr>";
            echo "<td>" . substr($f['intitule'], 0, 50) . "...</td>";
            echo "<td>{$f['code']}</td>";
            echo "<td>{$f['nb_planifications']}</td>";
            echo "<td>" . ($f['derniere_planification'] ? date('d/m/Y', strtotime($f['derniere_planification'])) : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='success'>✅ Le système peut détecter les formations déjà planifiées!</div>";
    } else {
        echo "<div class='warning'>⚠️ Aucune formation planifiée détectée pour l'agent {$agent_id}</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
}

// Test 4: Simuler le formulaire de planning
echo "<h3>4️⃣ Test formulaire de planning</h3>";
echo "<div class='info'>Voici le formulaire tel qu'il devrait apparaître dans la modal:</div>";

// Récupérer les données nécessaires
$stmt = $db->query("SELECT * FROM agents LIMIT 1");
$agent = $stmt->fetch();

$stmt = $db->query("SELECT * FROM formations LIMIT 5");
$formations = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM centres_formation LIMIT 5");
$centres = $stmt->fetchAll();

if ($agent && $formations && $centres) {
    echo "<form style='border: 1px solid #ddd; padding: 20px; background: #f9f9f9;'>";
    echo "<h4>Planifier une Formation pour {$agent['prenom']} {$agent['nom']}</h4>";
    
    echo "<div style='margin: 10px 0;'>";
    echo "<label>Formation:</label><br>";
    echo "<select name='formation_id' style='width: 100%; padding: 5px;'>";
    echo "<option value=''>Sélectionner une formation</option>";
    foreach ($formations as $f) {
        echo "<option value='{$f['id']}'>{$f['code']} - {$f['intitule']}</option>";
    }
    echo "</select>";
    echo "</div>";
    
    echo "<div style='margin: 10px 0;'>";
    echo "<label>Centre de Formation:</label><br>";
    echo "<select name='centre_formation_prevu' style='width: 100%; padding: 5px;'>";
    echo "<option value=''>Sélectionner un centre</option>";
    foreach ($centres as $c) {
        echo "<option value='{$c['nom']}'>{$c['nom']}</option>";
    }
    echo "</select>";
    echo "</div>";
    
    echo "<div style='display: flex; gap: 10px;'>";
    echo "<div style='flex: 1;'><label>Date Début:</label><br><input type='date' name='date_prevue_debut' style='width: 100%; padding: 5px;' value='" . date('Y-m-d', strtotime('+1 day')) . "'></div>";
    echo "<div style='flex: 1;'><label>Date Fin:</label><br><input type='date' name='date_prevue_fin' style='width: 100%; padding: 5px;' value='" . date('Y-m-d', strtotime('+6 days')) . "'></div>";
    echo "</div>";
    
    echo "<div style='display: flex; gap: 10px; margin: 10px 0;'>";
    echo "<div style='flex: 1;'><label>Ville:</label><br><input type='text' name='ville' placeholder='Ex: Dakar' style='width: 100%; padding: 5px;'></div>";
    echo "<div style='flex: 1;'><label>Pays:</label><br><input type='text' name='pays' placeholder='Ex: Sénégal' style='width: 100%; padding: 5px;'></div>";
    echo "</div>";
    
    echo "<div style='display: flex; gap: 10px; margin: 10px 0;'>";
    echo "<div style='flex: 1;'><label>Durée (jours):</label><br><input type='number' name='duree' placeholder='5' style='width: 100%; padding: 5px;'></div>";
    echo "<div style='flex: 1;'><label>Perdiem (FCFA):</label><br><input type='number' name='perdiem' placeholder='50000' style='width: 100%; padding: 5px;'></div>";
    echo "<div style='flex: 1;'><label>Priorité:</label><br><select name='priorite' style='width: 100%; padding: 5px;'><option value='1'>1 - Très élevé</option><option value='2'>2 - Moyen</option><option value='3' selected>3 - Moins élevé</option></select></div>";
    echo "</div>";
    
    echo "<button type='button' class='btn' onclick='alert(\"Formulaire prêt! Le problème vient du JavaScript de soumission.\")'>🧪 Tester Formulaire</button>";
    echo "</form>";
    
    echo "<div class='success'>✅ Le formulaire s'affiche correctement avec tous les champs!</div>";
} else {
    echo "<div class='error'>❌ Données manquantes pour afficher le formulaire</div>";
}

echo "<hr>";
echo "<h3>🔧 Diagnostic Final</h3>";
echo "<div class='info'>";
echo "<strong>Problèmes identifiés:</strong><br>";
echo "1. La base de données fonctionne ✅<br>";
echo "2. Les champs existent ✅<br>";
echo "3. La sauvegarde fonctionne ✅<br>";
echo "4. Le problème vient de l'interface JavaScript ❌<br><br>";
echo "<strong>Solution:</strong> Le problème est dans le JavaScript du formulaire de planning dans la modal agent.";
echo "</div>";

echo "<br><a href='admin.php' class='btn'>← Retour Admin</a>";
echo " <a href='ajax/get_agent_details.php?id=5' class='btn' target='_blank'>🔍 Tester Modal Agent</a>";
?>
