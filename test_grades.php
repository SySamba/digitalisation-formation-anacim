<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Vérifier la structure de la colonne grade
$query = "SHOW COLUMNS FROM agents WHERE Field = 'grade'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Structure actuelle de la colonne 'grade':</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";

// Tester l'insertion d'un agent avec les nouveaux grades
echo "<h3>Test des nouveaux grades:</h3>";

$grades_to_test = ['verificateur_stagiaire', 'verificateur_titulaire'];

foreach ($grades_to_test as $grade) {
    echo "<p>Test du grade: <strong>$grade</strong> - ";
    
    try {
        $test_query = "INSERT INTO agents (matricule, grade, prenom, nom, date_recrutement) 
                       VALUES (?, ?, 'Test', 'Agent', CURDATE())";
        $test_stmt = $db->prepare($test_query);
        $test_matricule = 'TEST_' . strtoupper($grade) . '_' . time();
        
        if ($test_stmt->execute([$test_matricule, $grade])) {
            echo "<span style='color: green;'>✅ SUCCÈS</span>";
            
            // Supprimer l'agent de test
            $delete_query = "DELETE FROM agents WHERE matricule = ?";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->execute([$test_matricule]);
        } else {
            echo "<span style='color: red;'>❌ ÉCHEC</span>";
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>❌ ERREUR: " . $e->getMessage() . "</span>";
    }
    echo "</p>";
}

// Afficher tous les grades disponibles
echo "<h3>Tous les grades disponibles:</h3>";
$grades_query = "SELECT DISTINCT grade FROM agents WHERE grade IS NOT NULL ORDER BY grade";
$grades_stmt = $db->prepare($grades_query);
$grades_stmt->execute();
$existing_grades = $grades_stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<ul>";
foreach ($existing_grades as $grade) {
    echo "<li><strong>$grade</strong> - " . getGradeLabel($grade) . "</li>";
}
echo "</ul>";

// Inclure les fonctions pour getGradeLabel
require_once 'includes/functions.php';

function getGradeLabel($grade) {
    $grades = [
        'cadre_technique' => 'Cadre Technique',
        'agent_technique' => 'Agent Technique',
        'inspecteur_stagiaire' => 'Inspecteur Stagiaire',
        'inspecteur_titulaire' => 'Inspecteur Titulaire',
        'inspecteur_principal' => 'Inspecteur Principal',
        'verificateur_stagiaire' => 'Vérificateur Stagiaire',
        'verificateur_titulaire' => 'Vérificateur Titulaire'
    ];
    
    return $grades[$grade] ?? $grade;
}
?>
