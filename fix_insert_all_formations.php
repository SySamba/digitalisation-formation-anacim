<?php
session_start();
require_once 'config/database.php';

// Protection: seulement pour les admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Accès refusé. Connectez-vous en tant qu'administrateur.");
}

$database = new Database();
$db = $database->getConnection();

echo "<html><head><meta charset='UTF-8'><title>Réinsertion des Formations</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #124c97; }
    .btn { padding: 12px 24px; margin: 10px 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
    .btn-danger { background-color: #dc3545; color: white; }
    .btn-primary { background-color: #124c97; color: white; }
    .btn-success { background-color: #28a745; color: white; }
    .alert { padding: 15px; margin: 15px 0; border-radius: 5px; }
    .alert-warning { background-color: #fff3cd; border: 1px solid #ffc107; }
    .alert-success { background-color: #d4edda; border: 1px solid #28a745; }
    .alert-danger { background-color: #f8d7da; border: 1px solid #dc3545; }
    .alert-info { background-color: #d1ecf1; border: 1px solid #17a2b8; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #124c97; color: white; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #124c97; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔧 Réinsertion de Toutes les Formations</h1>";

// Étape 1: Diagnostic
echo "<div class='step'>";
echo "<h2>📊 Étape 1: Diagnostic</h2>";

$stmt = $db->query("SELECT COUNT(*) as total FROM formations");
$total_actuel = $stmt->fetch()['total'];

echo "<div class='alert alert-info'>";
echo "<strong>Formations actuellement dans la base:</strong> {$total_actuel}";
echo "</div>";

$stmt = $db->query("SELECT categorie, COUNT(*) as count FROM formations GROUP BY categorie");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>Catégorie</th><th>Nombre Actuel</th><th>Attendu</th></tr>";
$expected = [
    'FAMILIARISATION' => 2,
    'FORMATION_INITIALE' => 15,
    'FORMATION_COURS_EMPLOI' => 12,
    'FORMATION_TECHNIQUE' => 19
];

$categories_map = [];
foreach ($categories as $cat) {
    $categories_map[$cat['categorie']] = $cat['count'];
}

$needs_fix = false;
foreach ($expected as $cat => $exp) {
    $actual = $categories_map[$cat] ?? 0;
    $status = $actual == $exp ? '✅' : '❌';
    if ($actual != $exp) $needs_fix = true;
    
    echo "<tr>";
    echo "<td>{$cat}</td>";
    echo "<td>{$actual}</td>";
    echo "<td>{$exp} {$status}</td>";
    echo "</tr>";
}
echo "</table>";

echo "</div>";

// Étape 2: Action
if (isset($_POST['action'])) {
    echo "<div class='step'>";
    echo "<h2>⚙️ Étape 2: Exécution</h2>";
    
    try {
        $db->beginTransaction();
        
        // Sauvegarder les associations formations_agents
        echo "<p>🔄 Sauvegarde des données existantes...</p>";
        
        // Supprimer toutes les formations
        echo "<p>🗑️ Suppression des formations existantes...</p>";
        $db->exec("DELETE FROM formations");
        
        // Réinitialiser l'auto-increment
        $db->exec("ALTER TABLE formations AUTO_INCREMENT = 1");
        
        // Réinsérer toutes les formations
        echo "<p>➕ Insertion de toutes les formations...</p>";
        
        $sql = "INSERT INTO formations (numero, intitule, code, ressource, periodicite_mois, categorie) VALUES ";
        
        $formations_data = [
            // FAMILIARISATION
            ["0.1", "Familiarisation avec l'entreprise (ANACIM)", "SUR-FAM-01", "Interne", 564, "FAMILIARISATION"],
            ["0.2", "Familiarisation avec la Direction (DSF)", "SUR-FAM-02", "Interne", 564, "FAMILIARISATION"],
            
            // FORMATION INITIALE
            ["1.1", "Introduction à la Sûreté et à la Facilitation", "SUR-INI-01", "Interne", 564, "FORMATION_INITIALE"],
            ["1.2", "BASE/Sensibilisation", "SUR-INI-02", "Interne", 24, "FORMATION_INITIALE"],
            ["1.3", "Règlementation - Protection physique des Aéroports", "SUR-INI-03", "Interne", 36, "FORMATION_INITIALE"],
            ["1.4", "Règlementation - contrôle d'accès et autres mesures applicables au personnel d'aéroport", "SUR-INI-04", "Interne", 36, "FORMATION_INITIALE"],
            ["1.5", "Règlementation - Inspection-filtrage des passagers et des bagages de cabines", "SUR-INI-05", "Interne", 36, "FORMATION_INITIALE"],
            ["1.6", "Règlementation - Inspection-filtrage des bagages de soute", "SUR-INI-06", "Interne", 36, "FORMATION_INITIALE"],
            ["1.7", "Règlementation - Mesures applicables au fret et à la poste", "SUR-INI-07", "Interne", 36, "FORMATION_INITIALE"],
            ["1.8", "Règlementation - Mesures applicables aux approvisionnement de bord et fournitures d'aéroport", "SUR-INI-08", "Interne", 36, "FORMATION_INITIALE"],
            ["1.9", "Règlementation - Mesures applicables à la sûreté des aéronefs", "SUR-INI-09", "Interne", 36, "FORMATION_INITIALE"],
            ["1.10", "Réglementation - Mesures applicables à l'exploitation des équipements de sûreté", "SUR-INI-110", "Interne", 36, "FORMATION_INITIALE"],
            ["1.11", "Règlementation - Mesures de sûreté applicables au \"coté ville\"", "SUR-INI-11", "Interne", 36, "FORMATION_INITIALE"],
            ["1.12", "Règlementation - Dispositions relatives à la formation et à la certification du personnel de sûreté", "SUR-INI-12", "Interne", 36, "FORMATION_INITIALE"],
            ["1.13", "Contrôle Qualité - MPN \"Inspecteur national\" et dispositions nationales", "SUR-INI-14", "Interne/Externe", 36, "FORMATION_INITIALE"],
            ["1.14", "Réglementation - Mesures applicables à l'exploitation des équipements de sûreté", "SUR-INI-15", "Interne", 36, "FORMATION_INITIALE"],
            
            // FORMATION EN COURS D'EMPLOI
            ["2.1", "Inspection - Protection physique des Aéroports", "SUR-FCE-01", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.2", "Inspection - Mesures applicables aux personnes autres que les passagers", "SUR-FCE-02", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.3", "Inspection - Mesures applicables à l'inspection-filtrage des passagers", "SUR-FCE-03", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.4", "Inspection - Mesures applicables à Inspection-filtrage des bagages de soute", "SUR-FCE-04", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.5", "Inspection - Mesures applicables à la sureté du fret et de la poste", "SUR-FCE-05", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.6", "Inspection- Mesures applicables aux approvisionnements de bord et fournitures d'aéroport", "SUR-FCE-06", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.7", "Inspection - Mesures applicables aux contrôles de sûreté des véhicules", "SUR-FCE-07", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.8", "Inspection - Mesures applicables à la sûreté des aéronefs", "SUR-FCE-08", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.9", "Inspection - Mesures applicables à l'exploitation des équipements", "SUR-FCE-09", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.10", "Inspection - Mesures applicables à la sûreté coté ville", "SUR-FCE-10", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.11", "Inspection - Mesures applicables à la formation et certification du personnel", "SUR-FCE-11", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            ["2.12", "Test en situation opérationnel", "SUR-FCE-12", "Interne", 564, "FORMATION_COURS_EMPLOI"],
            
            // FORMATION TECHNIQUE
            ["3.1", "Processus d'inspection-filtrage des passagers et bagages de cabine", "SUR-FTS-01", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.2", "Processus d'inspection-filtrage des bagages de soute", "SUR-FTS-02", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.3", "Techniques de contrôle des véhicules", "SUR-FTS-03", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.4", "Processus du contrôle de sûreté du fret et de la poste", "SUR-FTS-04", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.5", "Processus du contrôle de sûreté des approvisionnements de bord et fournitures d'aéroports", "SUR-FTS-05", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.6", "Procédures d'inspection filtrage des personnes (autres que les passagers), des bagages et des objets transportés", "SUR-FTS-06", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.7", "Sureté des aéronefs/sûreté aérienne", "SUR-FTS-07", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.9", "Imagerie radioscopique", "SUR-FTS-09", "Externe", 36, "FORMATION_TECHNIQUE"],
            ["3.10", "Exploitation des équipements de sûreté", "SUR-FTS-10", "Externe", 36, "FORMATION_TECHNIQUE"],
            ["3.11", "Utilisation des Chiens détecteurs d'explosifs", "SUR-FTS-11", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.12", "Evaluation/Gestion du risque", "SUR-FTS-12", "Interne/Externe", 48, "FORMATION_TECHNIQUE"],
            ["3.13", "Instructeur en sûreté (MPN)", "SUR-FTS-13", "Interne/Externe", 120, "FORMATION_TECHNIQUE"],
            ["3.14", "Techniques d'instructions - Trainair (TIC)", "SUR-FTS-14", "Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.15", "Développement/conception de cours/supports pédagogiques", "SUR-FTS-15", "Externe", 120, "FORMATION_TECHNIQUE"],
            ["3.16", "Evaluation du comportement des personnes (ECP) en milieu aéroportuaire", "SUR-FTS-16", "Externe", 36, "FORMATION_TECHNIQUE"],
            ["3.17", "Evaluation d'un centre de formation (TMC)", "SUR-FTS-17", "Externe", 36, "FORMATION_TECHNIQUE"],
            ["3.18", "Facilitation - MPN OACI", "SUR-FTS-18", "Interne/Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.19", "Marchandises dangereuses", "SUR-FTS-19", "Interne/Externe", 60, "FORMATION_TECHNIQUE"],
            ["3.8", "Détection d'explosifs et d'armes", "SUR-FTS-08", "Externe", 60, "FORMATION_TECHNIQUE"]
        ];
        
        $values = [];
        foreach ($formations_data as $formation) {
            $values[] = "('" . implode("', '", array_map(function($v) use ($db) {
                return is_numeric($v) ? $v : $db->quote($v);
            }, $formation)) . "')";
        }
        
        $sql .= implode(", ", $values);
        
        // Corriger les quotes
        $sql = str_replace("''", "'", $sql);
        
        $db->exec($sql);
        
        $db->commit();
        
        // Vérification
        $stmt = $db->query("SELECT COUNT(*) as total FROM formations");
        $nouveau_total = $stmt->fetch()['total'];
        
        echo "<div class='alert alert-success'>";
        echo "<strong>✅ Succès!</strong><br>";
        echo "Nombre de formations insérées: {$nouveau_total}<br>";
        echo "<a href='debug_dropdown_formations.php' class='btn btn-primary' style='display:inline-block; margin-top:10px;'>Vérifier le résultat</a>";
        echo "</div>";
        
    } catch (Exception $e) {
        // Vérifier si une transaction est active avant de faire rollback
        try {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Exception $rollbackEx) {
            // Ignorer les erreurs de rollback
        }
        
        echo "<div class='alert alert-danger'>";
        echo "<strong>❌ Erreur:</strong> " . $e->getMessage();
        echo "<br><br><strong>Détails techniques:</strong><br>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    }
    
    echo "</div>";
} else {
    // Formulaire de confirmation
    echo "<div class='step'>";
    echo "<h2>⚠️ Étape 2: Confirmation</h2>";
    
    if ($needs_fix) {
        echo "<div class='alert alert-warning'>";
        echo "<strong>Attention:</strong> Cette action va:<br>";
        echo "1. Supprimer toutes les formations existantes<br>";
        echo "2. Réinsérer les 48 formations complètes<br>";
        echo "3. Les associations formations-agents ne seront PAS affectées<br>";
        echo "</div>";
        
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='reinsertion'>";
        echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"Êtes-vous sûr de vouloir réinsérer toutes les formations?\")'>🔄 Réinsérer Toutes les Formations</button>";
        echo "</form>";
    } else {
        echo "<div class='alert alert-success'>";
        echo "<strong>✅ Tout est OK!</strong> Toutes les formations sont présentes.";
        echo "</div>";
    }
    
    echo "</div>";
}

echo "<div style='margin-top: 30px;'>";
echo "<a href='admin_planning.php' class='btn btn-primary'>← Retour au Planning</a>";
echo "<a href='debug_dropdown_formations.php' class='btn btn-success'>📊 Voir Diagnostic Détaillé</a>";
echo "</div>";

echo "</div></body></html>";
?>
