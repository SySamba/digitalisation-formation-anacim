<?php
// Générateur PDF ultra-minimal

function generateMinimalPDF($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    $filename = 'rapport_formations_' . $agent_data['matricule'] . '_' . date('Y-m-d') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Formations</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            line-height: 1.4;
        }
        h1, h2, h3 { 
            color: #124c97; 
        }
        h1, h2 { text-align: center; }
        p { margin: 5px 0; }
        ul { margin: 10px 0; }
        li { margin: 3px 0; }
        @media print {
            body { margin: 0; }
            @page { margin: 1.5cm; }
        }
    </style>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</head>
<body>
    <h1>RAPPORT DE FORMATIONS</h1>
    <h2>' . htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) . '</h2>
    
    <p><strong>Matricule:</strong> ' . htmlspecialchars($agent_data['matricule']) . '</p>
    <p><strong>Grade:</strong> ' . htmlspecialchars($agent_data['grade']) . '</p>
    <p><strong>Structure:</strong> ' . htmlspecialchars($agent_data['structure_attache'] ?? 'N/A') . '</p>
    <p><strong>Date:</strong> ' . date('d/m/Y H:i') . '</p>
    
    <h3>FORMATIONS EFFECTUÉES (' . count($formations_effectuees) . ')</h3>';
    
    if (!empty($formations_effectuees)) {
        echo '<ul>';
        foreach ($formations_effectuees as $fe) {
            echo '<li>' . htmlspecialchars($fe['code']) . ' - ' . htmlspecialchars($fe['intitule']) . ' (' . date('d/m/Y', strtotime($fe['date_fin'])) . ')</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Aucune formation effectuée</p>';
    }
    
    echo '<h3>FORMATIONS PLANIFIÉES (' . count($formations_planifiees) . ')</h3>';
    
    if (!empty($formations_planifiees)) {
        echo '<ul>';
        foreach ($formations_planifiees as $fp) {
            echo '<li>' . htmlspecialchars($fp['code']) . ' - ' . htmlspecialchars($fp['intitule']) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Aucune formation planifiée</p>';
    }
    
    echo '<h3>FORMATIONS NON EFFECTUÉES (' . count($formations_non_effectuees) . ')</h3>';
    
    if (!empty($formations_non_effectuees)) {
        echo '<ul>';
        foreach ($formations_non_effectuees as $fne) {
            echo '<li>' . htmlspecialchars($fne['code']) . ' - ' . htmlspecialchars($fne['intitule']) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Toutes les formations ont été effectuées</p>';
    }
    
    echo '</body></html>';
}
?>
