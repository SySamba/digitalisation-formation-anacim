<?php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'classes/Agent.php';
require_once 'classes/Formation.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer les agents
$stmt = $db->prepare("SELECT * FROM agents ORDER BY nom, prenom");
$stmt->execute();
$agents = $stmt->fetchAll();

// Récupérer les formations
$stmt = $db->prepare("SELECT id, code, intitule, categorie, periodicite_mois FROM formations ORDER BY categorie, intitule");
$stmt->execute();
$formations = $stmt->fetchAll();

// Récupérer les centres de formation (avec fallback si la table n'existe pas)
try {
    $stmt = $db->prepare("SELECT * FROM centres_formation ORDER BY nom");
    $stmt->execute();
    $centres_formation = $stmt->fetchAll();
} catch (PDOException $e) {
    // Si la table n'existe pas, utiliser une liste par défaut
    $centres_formation = [
        ['id' => 1, 'nom' => 'ENAC'],
        ['id' => 2, 'nom' => 'ERNAM'],
        ['id' => 3, 'nom' => 'ITAerea'],
        ['id' => 4, 'nom' => 'IFURTA'],
        ['id' => 5, 'nom' => 'EPT'],
        ['id' => 6, 'nom' => 'IFNPC'],
        ['id' => 7, 'nom' => 'EMAERO services']
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning des Formations - ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Test Formation Dropdown - Version Nouvelle</h2>
        
        <div class="card">
            <div class="card-header">
                <h5>Planifier une Formation</h5>
            </div>
            <div class="card-body">
                <form>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Agent *</label>
                            <select class="form-select" name="agent_id" required>
                                <option value="">Sélectionner un agent...</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo $agent['id']; ?>">
                                        <?php echo $agent['matricule'] . ' - ' . $agent['prenom'] . ' ' . $agent['nom']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Formation *</label>
                            <select class="form-select" name="formation_id" required>
                                <option value="">Sélectionner une formation...</option>
                                <?php 
                                foreach ($formations as $formation) {
                                    $text = $formation['code'] . ' - ' . $formation['intitule'];
                                    echo '<option value="' . $formation['id'] . '">' . $text . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Centre de Formation *</label>
                            <select class="form-select" name="centre_formation_prevu" required>
                                <option value="">Sélectionner un centre...</option>
                                <?php foreach ($centres_formation as $centre): ?>
                                    <option value="<?php echo $centre['nom']; ?>">
                                        <?php echo $centre['nom']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="mt-4">
            <h4>Debug - Formations disponibles:</h4>
            <ul>
            <?php foreach ($formations as $formation): ?>
                <li><?php echo $formation['code'] . ' - ' . $formation['intitule']; ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>
