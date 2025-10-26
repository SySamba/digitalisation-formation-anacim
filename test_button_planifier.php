<?php
// Test direct du bouton Planifier
session_start();
$_SESSION['admin_logged_in'] = true;

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer un agent et une formation pour le test
$stmt = $db->prepare("SELECT id, matricule, prenom, nom FROM agents LIMIT 1");
$stmt->execute();
$agent = $stmt->fetch();

$stmt = $db->prepare("SELECT id, code, intitule FROM formations WHERE NOT EXISTS (SELECT 1 FROM formations_agents WHERE agent_id = ? AND formation_id = formations.id) LIMIT 1");
$stmt->execute([$agent['id']]);
$formation = $stmt->fetch();

if (!$formation) {
    // Si toutes les formations sont faites, prendre n'importe quelle formation
    $stmt = $db->prepare("SELECT id, code, intitule FROM formations LIMIT 1");
    $stmt->execute();
    $formation = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Bouton Planifier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 40px; max-width: 800px; margin: 0 auto; }
        .test-box { border: 2px solid #124c97; padding: 20px; margin: 20px 0; border-radius: 10px; }
        h2 { color: #124c97; }
    </style>
</head>
<body>
    <h2><i class="fas fa-flask"></i> Test du Bouton "Planifier"</h2>
    
    <div class="test-box">
        <h4>Agent de test :</h4>
        <p><strong><?= htmlspecialchars($agent['matricule']) ?> - <?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?></strong></p>
        
        <h4>Formation non effectuée :</h4>
        <p><strong><?= htmlspecialchars($formation['code']) ?> - <?= htmlspecialchars($formation['intitule']) ?></strong></p>
        
        <hr>
        
        <h4>Simulation du bouton "Planifier" :</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Formation</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($formation['intitule']) ?></td>
                    <td>
                        <span class="badge bg-warning">
                            <i class="fas fa-exclamation-triangle"></i> Non effectuée
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" 
                                onclick="window.location.href='admin_planning.php?section=planifier&agent_id=<?= $agent['id'] ?>&formation_id=<?= $formation['id'] ?>'">
                            <i class="fas fa-calendar-plus"></i> Planifier
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="alert alert-info mt-3">
            <strong><i class="fas fa-info-circle"></i> Instructions :</strong>
            <ol class="mb-0">
                <li>Cliquez sur le bouton "Planifier" ci-dessus</li>
                <li>Vous devriez être redirigé vers admin_planning.php</li>
                <li>L'agent "<strong><?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?></strong>" doit être pré-sélectionné</li>
                <li>La formation "<strong><?= htmlspecialchars($formation['code']) ?></strong>" doit être pré-sélectionnée</li>
                <li>Un message bleu doit s'afficher : "Pré-sélection active"</li>
            </ol>
        </div>
        
        <div class="alert alert-warning mt-3">
            <strong><i class="fas fa-link"></i> URL qui sera générée :</strong><br>
            <code>admin_planning.php?section=planifier&agent_id=<?= $agent['id'] ?>&formation_id=<?= $formation['id'] ?></code>
        </div>
    </div>
    
    <div class="test-box">
        <h4>Vérifications à faire après le clic :</h4>
        <ul>
            <li>✅ La page admin_planning.php s'ouvre</li>
            <li>✅ La section "Planifier une Formation" est active (pas "Besoins de Formation")</li>
            <li>✅ Un message bleu "Pré-sélection active" s'affiche en haut du formulaire</li>
            <li>✅ Le dropdown "Agent" affiche : <strong><?= htmlspecialchars($agent['matricule'] . ' - ' . $agent['prenom'] . ' ' . $agent['nom']) ?></strong></li>
            <li>✅ Le dropdown "Formation" affiche : <strong><?= htmlspecialchars($formation['code'] . ' - ' . $formation['intitule']) ?></strong></li>
            <li>✅ Les champs Ville, Pays, Durée, Perdiem, Priorité sont visibles</li>
        </ul>
    </div>
</body>
</html>
