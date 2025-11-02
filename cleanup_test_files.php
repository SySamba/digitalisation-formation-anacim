<?php
// Script pour nettoyer les fichiers de test créés

$files_to_delete = [
    'debug_formations_test.php',
    'test_corrections.php',
    'test_agent_details.php',
    'test_statistiques_agent.php',
    'test_formations_techniques.php',
    'debug_agent_specific.php',
    'test_mise_a_jour_kpi.php',
    'test_detail_agent_debug.php',
    'debug_samba_sy.php',
    'debug_formations_samba_simple.php',
    'test_samba_direct.php',
    'mes_formations_a_jour.php', // Si vous ne voulez plus cette fonctionnalité
    'ajax/update_formation_status.php' // Si vous ne voulez plus cette fonctionnalité
];

echo "<h2>Nettoyage des fichiers de test</h2>";

foreach ($files_to_delete as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "✅ Fichier supprimé: $file<br>";
        } else {
            echo "❌ Erreur lors de la suppression: $file<br>";
        }
    } else {
        echo "ℹ️ Fichier non trouvé: $file<br>";
    }
}

echo "<br><strong>Nettoyage terminé !</strong>";
?>
