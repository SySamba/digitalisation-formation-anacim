<?php
// Fonction simple pour générer un PDF à partir de HTML
function generatePDFFromHTML($html, $filename) {
    // Approche simple : utiliser mPDF si disponible, sinon fallback vers HTML
    if (class_exists('Mpdf\Mpdf')) {
        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 16,
                'margin_bottom' => 16,
                'margin_header' => 9,
                'margin_footer' => 9
            ]);
            
            $mpdf->WriteHTML($html);
            $mpdf->Output($filename, 'D');
            return true;
        } catch (Exception $e) {
            // Fallback si mPDF échoue
        }
    }
    
    // Fallback : générer un HTML stylé qui peut être imprimé en PDF par le navigateur
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $filename) . '"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Rapport de Formations</title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                line-height: 1.4;
            }
            h1, h2, h3 { 
                color: #124c97; 
                page-break-after: avoid;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20px; 
                page-break-inside: avoid;
            }
            th, td { 
                border: 1px solid #ddd; 
                padding: 8px; 
                text-align: left; 
                font-size: 12px;
            }
            th { 
                background-color: #124c97; 
                color: white; 
                font-weight: bold;
            }
            .header-info { 
                border: none; 
                margin-bottom: 30px;
            }
            .header-info td { 
                border: none; 
                padding: 5px 10px; 
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
            }
            .print-button:hover {
                background: #0a3570;
            }
        </style>
        <script>
            function printToPDF() {
                window.print();
            }
        </script>
    </head>
    <body>
        <button class="print-button no-print" onclick="printToPDF()">Imprimer en PDF</button>
        ' . $html . '
    </body>
    </html>';
    
    return true;
}
?>
