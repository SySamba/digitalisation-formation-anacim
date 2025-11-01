<?php
// Générateur PDF simple et fiable

function generateSimplePDF($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    $filename = 'rapport_formations_' . $agent_data['matricule'] . '_' . date('Y-m-d') . '.html';
    
    // Headers pour forcer l'impression automatique
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Frame-Options: SAMEORIGIN');
    
    // Générer un HTML avec tableaux propres
    echo generateCleanHTML($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees);
}

function generatePDFContent($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Formations</title>
    <style>
        @media print {
            body { margin: 0; }
            @page { margin: 1.5cm; }
        }
        
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: white;
            color: black;
        }
        h1, h2, h3 { 
            color: #124c97; 
            page-break-after: avoid;
        }
        h1, h2 { text-align: center; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
            page-break-inside: avoid;
        }
        th, td { 
            border: 1px solid #333; 
            padding: 8px; 
            text-align: left;
            font-size: 12px;
        }
        th { 
            background-color: #124c97; 
            color: white; 
            font-weight: bold;
        }
        .info { 
            background-color: #f9f9f9; 
            padding: 15px; 
            margin: 20px 0; 
            border: 1px solid #ddd;
        }
    </style>
    <script>
        // Essayer auto-print une seule fois
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        📄 TÉLÉCHARGER EN PDF
    </button>
    
    <h1>RAPPORT DE FORMATIONS</h1>
    <h2>' . htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) . '</h2>
    
    <div class="info">
        <strong>Matricule:</strong> ' . htmlspecialchars($agent_data['matricule']) . '<br>
        <strong>Grade:</strong> ' . htmlspecialchars($agent_data['grade']) . '<br>
        <strong>Structure:</strong> ' . htmlspecialchars($agent_data['structure_attache'] ?? 'N/A') . '<br>
        <strong>Date de génération:</strong> ' . date('d/m/Y H:i') . '
    </div>
    
    <h3>FORMATIONS EFFECTUÉES (' . count($formations_effectuees) . ')</h3>
    <table>
        <tr>
            <th>Code</th>
            <th>Intitulé</th>
            <th>Centre</th>
            <th>Date Fin</th>
            <th>Statut</th>
        </tr>';
    
    foreach ($formations_effectuees as $fe) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fe['code']) . '</td>
            <td>' . htmlspecialchars($fe['intitule']) . '</td>
            <td>' . htmlspecialchars($fe['centre_formation']) . '</td>
            <td>' . date('d/m/Y', strtotime($fe['date_fin'])) . '</td>
            <td>' . htmlspecialchars($fe['statut']) . '</td>
        </tr>';
    }
    
    $html .= '</table>
    
    <h3>FORMATIONS PLANIFIÉES (' . count($formations_planifiees) . ')</h3>
    <table>
        <tr>
            <th>Code</th>
            <th>Intitulé</th>
            <th>Date Prévue</th>
            <th>Centre Prévu</th>
            <th>Statut</th>
        </tr>';
    
    foreach ($formations_planifiees as $fp) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fp['code']) . '</td>
            <td>' . htmlspecialchars($fp['intitule']) . '</td>
            <td>' . ($fp['date_prevue_debut'] ? date('d/m/Y', strtotime($fp['date_prevue_debut'])) : 'N/A') . '</td>
            <td>' . htmlspecialchars($fp['centre_formation_prevu'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($fp['statut']) . '</td>
        </tr>';
    }
    
    $html .= '</table>
    
    <h3>FORMATIONS NON EFFECTUÉES (' . count($formations_non_effectuees) . ')</h3>
    <table>
        <tr>
            <th>Code</th>
            <th>Intitulé</th>
            <th>Catégorie</th>
        </tr>';
    
    foreach ($formations_non_effectuees as $fne) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fne['code']) . '</td>
            <td>' . htmlspecialchars($fne['intitule']) . '</td>
            <td>' . htmlspecialchars($fne['categorie']) . '</td>
        </tr>';
    }
    
    $html .= '</table>
</body>
</html>';

    return $html;
}

function generateCleanPDF($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    // Générer un PDF simple et propre
    $pdf_content = "%PDF-1.4\n";
    $pdf_content .= "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
    $pdf_content .= "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
    $pdf_content .= "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n>>\n>>\n>>\nendobj\n";
    
    // Contenu du rapport
    $text_content = "RAPPORT DE FORMATIONS\\n\\n";
    $text_content .= "Agent: " . $agent_data['prenom'] . " " . $agent_data['nom'] . "\\n";
    $text_content .= "Matricule: " . $agent_data['matricule'] . "\\n";
    $text_content .= "Grade: " . $agent_data['grade'] . "\\n";
    $text_content .= "Structure: " . ($agent_data['structure_attache'] ?? 'N/A') . "\\n";
    $text_content .= "Date: " . date('d/m/Y H:i') . "\\n\\n";
    
    // Formations effectuées
    $text_content .= "FORMATIONS EFFECTUEES (" . count($formations_effectuees) . "):\\n";
    foreach ($formations_effectuees as $fe) {
        $text_content .= "- " . $fe['code'] . ": " . $fe['intitule'] . "\\n";
    }
    
    // Formations planifiées
    $text_content .= "\\nFORMATIONS PLANIFIEES (" . count($formations_planifiees) . "):\\n";
    foreach ($formations_planifiees as $fp) {
        $text_content .= "- " . $fp['code'] . ": " . $fp['intitule'] . "\\n";
    }
    
    // Formations non effectuées
    $text_content .= "\\nFORMATIONS NON EFFECTUEES (" . count($formations_non_effectuees) . "):\\n";
    foreach ($formations_non_effectuees as $fne) {
        $text_content .= "- " . $fne['code'] . ": " . $fne['intitule'] . "\\n";
    }
    
    // Nettoyer le contenu
    $clean_content = str_replace(['\\n'], [' '], $text_content);
    $clean_content = preg_replace('/[^\x20-\x7E]/', '', $clean_content);
    
    $stream = "BT\\n/F1 12 Tf\\n50 750 Td\\n(" . addslashes($clean_content) . ") Tj\\nET";
    $stream_length = strlen($stream);
    
    $pdf_content .= "4 0 obj\n<<\n/Length $stream_length\n>>\nstream\n$stream\nendstream\nendobj\n";
    $pdf_content .= "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";
    
    $xref_pos = strlen($pdf_content);
    $pdf_content .= "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000074 00000 n \n0000000120 00000 n \n0000000179 00000 n \n";
    $pdf_content .= sprintf("%010d 00000 n \n", $xref_pos - 50);
    $pdf_content .= "trailer\n<<\n/Size 6\n/Root 1 0 R\n>>\nstartxref\n$xref_pos\n%%EOF";
    
    echo $pdf_content;
}

function generateCleanHTML($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Formations</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: white;
            color: black;
        }
        h1, h2, h3 { 
            color: #124c97; 
            text-align: center;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
        }
        th, td { 
            border: 1px solid #333; 
            padding: 8px; 
            text-align: left;
            font-size: 12px;
        }
        th { 
            background-color: #124c97; 
            color: white; 
            font-weight: bold;
        }
        .info { 
            background-color: #f9f9f9; 
            padding: 15px; 
            margin: 20px 0; 
            border: 1px solid #ddd;
        }
        .print-button {
            background: #124c97;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px auto;
            display: block;
            width: 300px;
        }
        .print-button:hover {
            background: #0a3570;
        }
        @media print {
            .print-button { display: none !important; }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        📄 TÉLÉCHARGER EN PDF
    </button>
    
    <h1>RAPPORT DE FORMATIONS</h1>
    <h2>' . htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) . '</h2>
    
    <div class="info">
        <strong>Matricule:</strong> ' . htmlspecialchars($agent_data['matricule']) . '<br>
        <strong>Grade:</strong> ' . htmlspecialchars($agent_data['grade']) . '<br>
        <strong>Structure:</strong> ' . htmlspecialchars($agent_data['structure_attache'] ?? 'N/A') . '<br>
        <strong>Date de génération:</strong> ' . date('d/m/Y H:i') . '
    </div>
    
    <h3>FORMATIONS EFFECTUÉES (' . count($formations_effectuees) . ')</h3>
    <table>
        <tr>
            <th>Code</th>
            <th>Intitulé</th>
            <th>Centre</th>
            <th>Date Fin</th>
            <th>Statut</th>
        </tr>';
    
    foreach ($formations_effectuees as $fe) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fe['code']) . '</td>
            <td>' . htmlspecialchars($fe['intitule']) . '</td>
            <td>' . htmlspecialchars($fe['centre_formation']) . '</td>
            <td>' . date('d/m/Y', strtotime($fe['date_fin'])) . '</td>
            <td>' . htmlspecialchars($fe['statut']) . '</td>
        </tr>';
    }
    
    $html .= '</table>
    
    <h3>FORMATIONS PLANIFIÉES (' . count($formations_planifiees) . ')</h3>
    <table>
        <tr>
            <th>Code</th>
            <th>Intitulé</th>
            <th>Date Prévue</th>
            <th>Centre Prévu</th>
            <th>Statut</th>
        </tr>';
    
    foreach ($formations_planifiees as $fp) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fp['code']) . '</td>
            <td>' . htmlspecialchars($fp['intitule']) . '</td>
            <td>' . ($fp['date_prevue_debut'] ? date('d/m/Y', strtotime($fp['date_prevue_debut'])) : 'N/A') . '</td>
            <td>' . htmlspecialchars($fp['centre_formation_prevu'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($fp['statut']) . '</td>
        </tr>';
    }
    
    $html .= '</table>
    
    <h3>FORMATIONS NON EFFECTUÉES (' . count($formations_non_effectuees) . ')</h3>
    <table>
        <tr>
            <th>Code</th>
            <th>Intitulé</th>
            <th>Catégorie</th>
        </tr>';
    
    foreach ($formations_non_effectuees as $fne) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fne['code']) . '</td>
            <td>' . htmlspecialchars($fne['intitule']) . '</td>
            <td>' . htmlspecialchars($fne['categorie']) . '</td>
        </tr>';
    }
    
    $html .= '</table>
</body>
</html>';

    return $html;
}

function generateBasicPDF($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees, $filename) {
    // Générer un PDF basique mais valide
    $pdf_content = "%PDF-1.4\n";
    $pdf_content .= "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
    $pdf_content .= "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
    $pdf_content .= "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n>>\n>>\n>>\nendobj\n";
    
    $text_content = "RAPPORT DE FORMATIONS\\n";
    $text_content .= $agent_data['prenom'] . " " . $agent_data['nom'] . "\\n\\n";
    $text_content .= "Matricule: " . $agent_data['matricule'] . "\\n";
    $text_content .= "Grade: " . $agent_data['grade'] . "\\n";
    $text_content .= "Date: " . date('d/m/Y H:i') . "\\n\\n";
    
    // Formations effectuées
    $text_content .= "FORMATIONS EFFECTUEES: " . count($formations_effectuees) . "\\n";
    if (!empty($formations_effectuees)) {
        foreach ($formations_effectuees as $fe) {
            $code = isset($fe['code']) ? $fe['code'] : 'N/A';
            $intitule = isset($fe['intitule']) ? $fe['intitule'] : 'N/A';
            $text_content .= "- " . $code . ": " . $intitule . "\\n";
        }
    } else {
        $text_content .= "Aucune formation effectuee\\n";
    }
    
    // Formations planifiées
    $text_content .= "\\nFORMATIONS PLANIFIEES: " . count($formations_planifiees) . "\\n";
    if (!empty($formations_planifiees)) {
        foreach ($formations_planifiees as $fp) {
            $code = isset($fp['code']) ? $fp['code'] : 'N/A';
            $intitule = isset($fp['intitule']) ? $fp['intitule'] : 'N/A';
            $text_content .= "- " . $code . ": " . $intitule . "\\n";
        }
    } else {
        $text_content .= "Aucune formation planifiee\\n";
    }
    
    // Formations non effectuées
    $text_content .= "\\nFORMATIONS NON EFFECTUEES: " . count($formations_non_effectuees) . "\\n";
    if (!empty($formations_non_effectuees)) {
        foreach ($formations_non_effectuees as $fne) {
            $code = isset($fne['code']) ? $fne['code'] : 'N/A';
            $intitule = isset($fne['intitule']) ? $fne['intitule'] : 'N/A';
            $text_content .= "- " . $code . ": " . $intitule . "\\n";
        }
    } else {
        $text_content .= "Toutes les formations ont ete effectuees\\n";
    }
    
    // Nettoyer le contenu pour PDF
    $clean_content = str_replace(['\\n'], [' '], $text_content);
    $clean_content = preg_replace('/[^\x20-\x7E]/', '', $clean_content);
    $stream = "BT\\n/F1 10 Tf\\n50 750 Td\\n(" . addslashes($clean_content) . ") Tj\\nET";
    $stream_length = strlen($stream);
    
    $pdf_content .= "4 0 obj\n<<\n/Length $stream_length\n>>\nstream\n$stream\nendstream\nendobj\n";
    $pdf_content .= "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";
    $pdf_content .= "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000074 00000 n \n0000000120 00000 n \n0000000179 00000 n \n0000000364 00000 n \n";
    $pdf_content .= "trailer\n<<\n/Size 6\n/Root 1 0 R\n>>\nstartxref\n" . strlen($pdf_content) . "\n%%EOF";
    
    echo $pdf_content;
}

function generateMinimalPDF($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    generateBasicPDF($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees, '');
}
?>
