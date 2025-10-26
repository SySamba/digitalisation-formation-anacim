<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Ultra Simple</title>
</head>
<body style="padding: 40px; font-family: Arial;">
    <h1>Test de Sélection Formation</h1>
    
    <h3>Dropdown avec formation ID=4 pré-sélectionnée :</h3>
    
    <select id="test" style="width: 500px; padding: 10px; font-size: 16px;">
        <option value="">Sélectionner...</option>
        <option value="1">Formation 1</option>
        <option value="2">Formation 2</option>
        <option value="3">Formation 3</option>
        <option value="4" selected style="background-color: yellow; font-weight: bold;">Formation 4 - CELLE-CI DOIT ÊTRE SÉLECTIONNÉE</option>
        <option value="5">Formation 5</option>
    </select>
    
    <hr>
    
    <button onclick="check()" style="padding: 15px 30px; font-size: 16px; background: #124c97; color: white; border: none; border-radius: 5px; cursor: pointer;">
        Vérifier la sélection
    </button>
    
    <div id="result" style="margin-top: 20px; padding: 20px; border: 2px solid #ccc; background: #f9f9f9;"></div>
    
    <script>
    function check() {
        const select = document.getElementById('test');
        const value = select.value;
        const text = select.options[select.selectedIndex].text;
        
        document.getElementById('result').innerHTML = `
            <h3>Résultat :</h3>
            <p><strong>Valeur sélectionnée :</strong> ${value}</p>
            <p><strong>Texte :</strong> ${text}</p>
            <p><strong>Status :</strong> ${value === '4' ? '✅ CORRECT - Formation 4 est sélectionnée' : '❌ ERREUR - Formation ' + value + ' est sélectionnée'}</p>
        `;
    }
    
    // Auto-vérification au chargement
    window.onload = function() {
        check();
    };
    </script>
    
    <hr>
    <h3>Instructions :</h3>
    <ol>
        <li>Cette page teste si l'attribut HTML <code>selected</code> fonctionne dans votre navigateur</li>
        <li>La Formation 4 (en jaune) devrait être automatiquement sélectionnée</li>
        <li>Le résultat devrait afficher "✅ CORRECT"</li>
        <li>Si ça ne fonctionne pas ici, le problème vient de votre navigateur</li>
    </ol>
    
    <p><a href="test_button_planifier.php" style="color: #124c97;">← Retour au test du bouton Planifier</a></p>
</body>
</html>
