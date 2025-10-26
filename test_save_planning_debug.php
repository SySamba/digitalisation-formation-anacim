<?php
session_start();
require_once 'config/database.php';

// Simuler une session admin
$_SESSION['admin_logged_in'] = true;

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🔧 Debug Sauvegarde Planning</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
    .btn-primary { background: #124c97; color: white; }
    .btn-danger { background: #dc3545; color: white; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>";

// Test 1: Vérifier la structure de la table
echo "<h3>1️⃣ Vérification Table planning_formations</h3>";
try {
    $stmt = $pdo->query("DESCRIBE planning_formations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_fields = ['agent_id', 'formation_id', 'date_prevue_debut', 'date_prevue_fin', 
                       'centre_formation_prevu', 'ville', 'pays', 'duree', 'perdiem', 'priorite'];
    
    $existing_fields = array_column($columns, 'Field');
    $missing_fields = array_diff($required_fields, $existing_fields);
    
    if (empty($missing_fields)) {
        echo "<div class='success'>✅ Tous les champs requis existent</div>";
    } else {
        echo "<div class='error'>❌ Champs manquants: " . implode(', ', $missing_fields) . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur table: " . $e->getMessage() . "</div>";
}

// Test 2: Simuler exactement ce que fait save_planning.php
echo "<h3>2️⃣ Test Simulation save_planning.php</h3>";

if ($_POST && isset($_POST['test_save'])) {
    echo "<div class='info'>📝 Données reçues via POST:</div>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    try {
        // Récupérer les données comme dans save_planning.php
        $agent_id = $_POST['agent_id'] ?? '';
        $formation_id = $_POST['formation_id'] ?? '';
        $centre_formation_prevu = $_POST['centre_formation_prevu'] ?? '';
        $date_prevue_debut = $_POST['date_prevue_debut'] ?? '';
        $date_prevue_fin = $_POST['date_prevue_fin'] ?? '';
        $ville = $_POST['ville'] ?? '';
        $pays = $_POST['pays'] ?? '';
        $duree = $_POST['duree'] ?? '';
        $perdiem = $_POST['perdiem'] ?? null;
        $priorite = $_POST['priorite'] ?? '3';
        $statut = $_POST['statut'] ?? 'planifie';
        $commentaires = $_POST['commentaires'] ?? '';
        
        echo "<div class='info'>🔍 Validation des données:</div>";
        
        // Validation exacte comme dans save_planning.php
        if (empty($agent_id) || empty($formation_id) || empty($centre_formation_prevu) || 
            empty($date_prevue_debut) || empty($date_prevue_fin) || empty($ville) || 
            empty($pays) || empty($duree) || empty($priorite)) {
            echo "<div class='error'>❌ Validation échouée - champs manquants</div>";
            echo "<ul>";
            if (empty($agent_id)) echo "<li>agent_id manquant</li>";
            if (empty($formation_id)) echo "<li>formation_id manquant</li>";
            if (empty($centre_formation_prevu)) echo "<li>centre_formation_prevu manquant</li>";
            if (empty($date_prevue_debut)) echo "<li>date_prevue_debut manquant</li>";
            if (empty($date_prevue_fin)) echo "<li>date_prevue_fin manquant</li>";
            if (empty($ville)) echo "<li>ville manquant</li>";
            if (empty($pays)) echo "<li>pays manquant</li>";
            if (empty($duree)) echo "<li>duree manquant</li>";
            if (empty($priorite)) echo "<li>priorite manquant</li>";
            echo "</ul>";
        } else {
            echo "<div class='success'>✅ Validation réussie</div>";
            
            // Test de la requête INSERT
            $sql = "INSERT INTO planning_formations 
                    (agent_id, formation_id, date_prevue_debut, date_prevue_fin, centre_formation_prevu, 
                     ville, pays, duree, perdiem, priorite, statut, commentaires, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            echo "<div class='info'>📝 Requête SQL:</div>";
            echo "<pre>" . $sql . "</pre>";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $agent_id,
                $formation_id,
                $date_prevue_debut,
                $date_prevue_fin,
                $centre_formation_prevu,
                $ville,
                $pays,
                $duree,
                $perdiem,
                $priorite,
                $statut,
                $commentaires
            ]);
            
            if ($result) {
                $planning_id = $pdo->lastInsertId();
                echo "<div class='success'>✅ Planning sauvegardé avec ID: {$planning_id}</div>";
                
                // Vérifier que c'est bien dans la base
                $stmt = $pdo->prepare("SELECT * FROM planning_formations WHERE id = ?");
                $stmt->execute([$planning_id]);
                $saved_planning = $stmt->fetch();
                
                if ($saved_planning) {
                    echo "<div class='success'>✅ Vérification: Planning trouvé dans la base</div>";
                    echo "<pre>" . print_r($saved_planning, true) . "</pre>";
                } else {
                    echo "<div class='error'>❌ Planning non trouvé après sauvegarde!</div>";
                }
                
            } else {
                echo "<div class='error'>❌ Échec de la sauvegarde</div>";
                $errorInfo = $stmt->errorInfo();
                echo "<pre>Erreur SQL: " . print_r($errorInfo, true) . "</pre>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
} else {
    // Formulaire de test
    echo "<form method='POST'>";
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px; max-width: 800px;'>";
    
    echo "<div>";
    echo "<label>Agent ID:</label><br>";
    echo "<input type='number' name='agent_id' value='5' required style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Formation ID:</label><br>";
    echo "<input type='number' name='formation_id' value='2' required style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Centre Formation:</label><br>";
    echo "<input type='text' name='centre_formation_prevu' value='ENAC' required style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Date Début:</label><br>";
    echo "<input type='date' name='date_prevue_debut' value='" . date('Y-m-d', strtotime('+7 days')) . "' required style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Date Fin:</label><br>";
    echo "<input type='date' name='date_prevue_fin' value='" . date('Y-m-d', strtotime('+12 days')) . "' required style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Ville:</label><br>";
    echo "<input type='text' name='ville' value='Dakar' required style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Pays:</label><br>";
    echo "<input type='text' name='pays' value='Sénégal' required style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Durée (jours):</label><br>";
    echo "<input type='number' name='duree' value='5' required style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Perdiem:</label><br>";
    echo "<input type='number' name='perdiem' value='50000' style='width: 100%; padding: 8px;'>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Priorité:</label><br>";
    echo "<select name='priorite' required style='width: 100%; padding: 8px;'>";
    echo "<option value='1'>1 - Très élevé</option>";
    echo "<option value='2' selected>2 - Moyen</option>";
    echo "<option value='3'>3 - Moins élevé</option>";
    echo "</select>";
    echo "</div>";
    
    echo "<div>";
    echo "<label>Statut:</label><br>";
    echo "<select name='statut' style='width: 100%; padding: 8px;'>";
    echo "<option value='planifie' selected>Planifié</option>";
    echo "<option value='confirme'>Confirmé</option>";
    echo "</select>";
    echo "</div>";
    
    echo "<div style='grid-column: 1 / -1;'>";
    echo "<label>Commentaires:</label><br>";
    echo "<textarea name='commentaires' style='width: 100%; padding: 8px;' rows='3'>Test debug sauvegarde - " . date('Y-m-d H:i:s') . "</textarea>";
    echo "</div>";
    
    echo "</div>";
    echo "<br><button type='submit' name='test_save' class='btn btn-primary'>🧪 Tester Sauvegarde</button>";
    echo "</form>";
}

// Test 3: Vérifier les logs d'erreur PHP
echo "<h3>3️⃣ Logs d'Erreur PHP</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $recent_errors = array_slice(file($error_log), -10);
    if (!empty($recent_errors)) {
        echo "<div class='warning'>📋 10 dernières erreurs PHP:</div>";
        echo "<pre>" . implode('', $recent_errors) . "</pre>";
    } else {
        echo "<div class='success'>✅ Aucune erreur récente dans les logs</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Logs d'erreur non configurés ou non accessibles</div>";
}

echo "<br><a href='admin.php' class='btn btn-primary'>← Retour Admin</a>";
echo "<a href='test_planning_real_time.php' class='btn btn-primary'>Test Temps Réel</a>";
?>
