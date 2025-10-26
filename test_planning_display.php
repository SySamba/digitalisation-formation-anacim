<?php
// Test pour vérifier que les nouveaux champs s'affichent dans le planning
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer un planning pour tester
$stmt = $db->prepare("
    SELECT 
        pf.*,
        a.matricule,
        a.prenom,
        a.nom,
        f.code,
        f.intitule
    FROM planning_formations pf
    JOIN agents a ON pf.agent_id = a.id
    JOIN formations f ON pf.formation_id = f.id
    LIMIT 1
");
$stmt->execute();
$planning = $stmt->fetch();

echo "<h2>Test d'affichage des nouveaux champs du planning</h2>";

if ($planning) {
    echo "<h3>Planning trouvé :</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Champ</th><th>Valeur</th><th>Statut</th></tr>";
    
    $champs = [
        'agent_id' => 'Agent ID',
        'formation_id' => 'Formation ID',
        'matricule' => 'Matricule',
        'prenom' => 'Prénom',
        'nom' => 'Nom',
        'code' => 'Code Formation',
        'intitule' => 'Intitulé Formation',
        'centre_formation_prevu' => 'Centre Formation',
        'ville' => 'Ville (NOUVEAU)',
        'pays' => 'Pays (NOUVEAU)',
        'duree' => 'Durée (NOUVEAU)',
        'perdiem' => 'Perdiem (NOUVEAU)',
        'priorite' => 'Priorité (NOUVEAU)',
        'date_prevue_debut' => 'Date Début',
        'date_prevue_fin' => 'Date Fin',
        'statut' => 'Statut'
    ];
    
    foreach ($champs as $key => $label) {
        $valeur = $planning[$key] ?? 'NON DÉFINI';
        $statut = isset($planning[$key]) ? '✅ OK' : '❌ MANQUANT';
        
        // Vérifier si c'est un nouveau champ
        $is_new = in_array($key, ['ville', 'pays', 'duree', 'perdiem', 'priorite']);
        $style = $is_new ? 'background-color: #ffffcc;' : '';
        
        echo "<tr style='$style'>";
        echo "<td><strong>$label</strong></td>";
        echo "<td>" . htmlspecialchars($valeur) . "</td>";
        echo "<td>$statut</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3>Résumé :</h3>";
    $nouveaux_champs_ok = isset($planning['ville']) && isset($planning['pays']) && 
                          isset($planning['duree']) && isset($planning['priorite']);
    
    if ($nouveaux_champs_ok) {
        echo "<p style='color: green; font-weight: bold;'>✅ Tous les nouveaux champs sont présents dans la base de données !</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Les nouveaux champs ne sont pas présents. Vous devez exécuter le script SQL add_planning_fields.sql</p>";
        echo "<p>Commande à exécuter dans phpMyAdmin ou MySQL :</p>";
        echo "<pre>ALTER TABLE planning_formations 
ADD COLUMN ville VARCHAR(255) AFTER centre_formation_prevu,
ADD COLUMN pays VARCHAR(255) AFTER ville,
ADD COLUMN duree INT COMMENT 'Durée en jours' AFTER pays,
ADD COLUMN perdiem DECIMAL(10,2) AFTER duree,
ADD COLUMN priorite ENUM('1', '2', '3') DEFAULT '3' COMMENT '1=Très élevé, 2=Moyen, 3=Moins élevé' AFTER perdiem;</pre>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Aucun planning trouvé dans la base de données. Créez d'abord un planning pour tester.</p>";
}

// Vérifier la structure de la table
echo "<h3>Structure de la table planning_formations :</h3>";
$stmt = $db->prepare("DESCRIBE planning_formations");
$stmt->execute();
$columns = $stmt->fetchAll();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Défaut</th></tr>";
foreach ($columns as $col) {
    $is_new = in_array($col['Field'], ['ville', 'pays', 'duree', 'perdiem', 'priorite']);
    $style = $is_new ? 'background-color: #ffffcc; font-weight: bold;' : '';
    echo "<tr style='$style'>";
    echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
