<?php
// Générateur de documents Word et PDF pour les rapports de formations

function generateWordDocument($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    $filename = 'rapport_formations_' . $agent_data['matricule'] . '_' . date('Y-m-d') . '.doc';
    
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    echo '<!DOCTYPE html>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
    <head>
        <meta charset="UTF-8">
        <title>Rapport de Formations</title>
        <!--[if gte mso 9]>
        <xml>
            <w:WordDocument>
                <w:View>Print</w:View>
                <w:Zoom>90</w:Zoom>
                <w:DoNotPromptForConvert/>
                <w:DoNotShowInsertionsAndDeletions/>
            </w:WordDocument>
        </xml>
        <![endif]-->
        <style>
            body { font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.4; }
            h1 { color: #124c97; text-align: center; font-size: 18pt; margin-bottom: 20pt; }
            h2 { color: #124c97; text-align: center; font-size: 16pt; margin-bottom: 15pt; }
            h3 { color: #124c97; font-size: 14pt; margin-top: 20pt; margin-bottom: 10pt; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15pt; }
            th, td { border: 1px solid #000; padding: 8pt; text-align: left; }
            th { background-color: #124c97; color: white; font-weight: bold; }
            .info-table { border: none; }
            .info-table td { border: none; padding: 5pt; }
        </style>
    </head>
    <body>
        <h1>RAPPORT DE FORMATIONS</h1>
        <h2>' . htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) . '</h2>
        
        <table class="info-table">
            <tr>
                <td><strong>Matricule:</strong></td>
                <td>' . htmlspecialchars($agent_data['matricule']) . '</td>
            </tr>
            <tr>
                <td><strong>Grade:</strong></td>
                <td>' . htmlspecialchars($agent_data['grade']) . '</td>
            </tr>
            <tr>
                <td><strong>Structure:</strong></td>
                <td>' . htmlspecialchars($agent_data['structure_attache'] ?? 'N/A') . '</td>
            </tr>
            <tr>
                <td><strong>Date de génération:</strong></td>
                <td>' . date('d/m/Y H:i') . '</td>
            </tr>
        </table>
        
        <h3>FORMATIONS EFFECTUÉES (' . count($formations_effectuees) . ')</h3>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Intitulé</th>
                    <th>Centre</th>
                    <th>Date Début</th>
                    <th>Date Fin</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($formations_effectuees as $fe) {
        echo '<tr>
            <td>' . htmlspecialchars($fe['code']) . '</td>
            <td>' . htmlspecialchars($fe['intitule']) . '</td>
            <td>' . htmlspecialchars($fe['centre_formation']) . '</td>
            <td>' . date('d/m/Y', strtotime($fe['date_debut'])) . '</td>
            <td>' . date('d/m/Y', strtotime($fe['date_fin'])) . '</td>
            <td>' . htmlspecialchars($fe['statut']) . '</td>
        </tr>';
    }
    
    echo '</tbody></table>
        
        <h3>FORMATIONS PLANIFIÉES (' . count($formations_planifiees) . ')</h3>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Intitulé</th>
                    <th>Date Prévue Début</th>
                    <th>Date Prévue Fin</th>
                    <th>Centre Prévu</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($formations_planifiees as $fp) {
        echo '<tr>
            <td>' . htmlspecialchars($fp['code']) . '</td>
            <td>' . htmlspecialchars($fp['intitule']) . '</td>
            <td>' . ($fp['date_prevue_debut'] ? date('d/m/Y', strtotime($fp['date_prevue_debut'])) : 'N/A') . '</td>
            <td>' . ($fp['date_prevue_fin'] ? date('d/m/Y', strtotime($fp['date_prevue_fin'])) : 'N/A') . '</td>
            <td>' . htmlspecialchars($fp['centre_formation_prevu'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($fp['statut']) . '</td>
        </tr>';
    }
    
    echo '</tbody></table>
        
        <h3>FORMATIONS NON EFFECTUÉES (' . count($formations_non_effectuees) . ')</h3>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Intitulé</th>
                    <th>Catégorie</th>
                    <th>Périodicité</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($formations_non_effectuees as $fne) {
        echo '<tr>
            <td>' . htmlspecialchars($fne['code']) . '</td>
            <td>' . htmlspecialchars($fne['intitule']) . '</td>
            <td>' . htmlspecialchars($fne['categorie']) . '</td>
            <td>' . ($fne['periodicite_mois'] ? $fne['periodicite_mois'] . ' mois' : 'N/A') . '</td>
        </tr>';
    }
    
    echo '</tbody></table>
    </body>
    </html>';
}

function generatePDFDocument($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    $filename = 'rapport_formations_' . $agent_data['matricule'] . '_' . date('Y-m-d') . '.pdf';
    
    // Générer un document HTML qui sera traité comme PDF par le navigateur
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $filename) . '"');
    
    $html = generatePDFHTML($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees);
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Rapport de Formations - ' . htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) . '</title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
                @page { margin: 1.5cm; }
            }
            body { 
                font-family: Arial, sans-serif; 
                font-size: 12pt; 
                line-height: 1.4;
                margin: 20px;
                background: white;
            }
            h1, h2, h3 { 
                color: #124c97; 
                page-break-after: avoid;
                margin-top: 20pt;
                margin-bottom: 10pt;
            }
            h1 { text-align: center; font-size: 18pt; }
            h2 { text-align: center; font-size: 16pt; }
            h3 { font-size: 14pt; }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20pt; 
                page-break-inside: avoid;
            }
            th, td { 
                border: 1px solid #333; 
                padding: 8pt; 
                font-size: 11pt;
                text-align: left;
            }
            th { 
                background-color: #124c97; 
                color: white;
                font-weight: bold;
            }
            .info-table { 
                border: none;
                margin-bottom: 30pt;
            }
            .info-table td { 
                border: none;
                padding: 5pt 10pt;
            }
            .print-button {
                position: fixed;
                top: 10px;
                right: 10px;
                background: #124c97;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                z-index: 1000;
            }
            .print-button:hover {
                background: #0a3570;
            }
        </style>
        <script>
            function printToPDF() {
                window.print();
            }
            // Auto-print after 2 seconds if PDF download
            setTimeout(function() {
                if (window.location.href.includes(\'format=pdf\')) {
                    window.print();
                }
            }, 1000);
        </script>
    </head>
    <body>
        <button class="print-button no-print" onclick="printToPDF()">
            <i class="fas fa-print"></i> Imprimer en PDF
        </button>
        ' . $html . '
        
        <div class="no-print" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
            <h4>Instructions pour sauvegarder en PDF :</h4>
            <ol>
                <li>Cliquez sur le bouton \"Imprimer en PDF\" ci-dessus</li>
                <li>Dans la boîte de dialogue d\'impression, choisissez \"Enregistrer au format PDF\"</li>
                <li>Cliquez sur \"Enregistrer\" pour télécharger le fichier PDF</li>
            </ol>
        </div>
    </body>
    </html>';
}

function generatePDFHTML($agent_data, $formations_effectuees, $formations_planifiees, $formations_non_effectuees) {
    $html = '
    <h1 style="text-align: center;">RAPPORT DE FORMATIONS</h1>
    <h2 style="text-align: center;">' . htmlspecialchars($agent_data['prenom'] . ' ' . $agent_data['nom']) . '</h2>
    
    <table class="info-table" style="margin-bottom: 20pt;">
        <tr>
            <td><strong>Matricule:</strong></td>
            <td>' . htmlspecialchars($agent_data['matricule']) . '</td>
        </tr>
        <tr>
            <td><strong>Grade:</strong></td>
            <td>' . htmlspecialchars($agent_data['grade']) . '</td>
        </tr>
        <tr>
            <td><strong>Structure:</strong></td>
            <td>' . htmlspecialchars($agent_data['structure_attache'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td><strong>Date de génération:</strong></td>
            <td>' . date('d/m/Y H:i') . '</td>
        </tr>
    </table>
    
    <h3>FORMATIONS EFFECTUÉES (' . count($formations_effectuees) . ')</h3>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Intitulé</th>
                <th>Centre</th>
                <th>Date Fin</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($formations_effectuees as $fe) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fe['code']) . '</td>
            <td>' . htmlspecialchars($fe['intitule']) . '</td>
            <td>' . htmlspecialchars($fe['centre_formation']) . '</td>
            <td>' . date('d/m/Y', strtotime($fe['date_fin'])) . '</td>
            <td>' . htmlspecialchars($fe['statut']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>
    
    <h3>FORMATIONS PLANIFIÉES (' . count($formations_planifiees) . ')</h3>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Intitulé</th>
                <th>Date Prévue</th>
                <th>Centre Prévu</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($formations_planifiees as $fp) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fp['code']) . '</td>
            <td>' . htmlspecialchars($fp['intitule']) . '</td>
            <td>' . ($fp['date_prevue_debut'] ? date('d/m/Y', strtotime($fp['date_prevue_debut'])) : 'N/A') . '</td>
            <td>' . htmlspecialchars($fp['centre_formation_prevu'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($fp['statut']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>
    
    <h3>FORMATIONS NON EFFECTUÉES (' . count($formations_non_effectuees) . ')</h3>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Intitulé</th>
                <th>Catégorie</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($formations_non_effectuees as $fne) {
        $html .= '<tr>
            <td>' . htmlspecialchars($fne['code']) . '</td>
            <td>' . htmlspecialchars($fne['intitule']) . '</td>
            <td>' . htmlspecialchars($fne['categorie']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    
    return $html;
}
?>
