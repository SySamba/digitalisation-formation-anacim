<?php
// Script de test pour vérifier l'upload de photos
session_start();

// Simuler une session agent pour le test
$_SESSION['agent_logged_in'] = true;
$_SESSION['agent_id'] = 1; // ID de test

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Test d'upload de photo</h3>";
    echo "<pre>";
    echo "POST data:\n";
    print_r($_POST);
    echo "\nFILES data:\n";
    print_r($_FILES);
    echo "</pre>";
    
    if (isset($_FILES['photo'])) {
        $upload_dir = __DIR__ . '/uploads/photos/';
        echo "<p>Upload directory: " . $upload_dir . "</p>";
        echo "<p>Directory exists: " . (is_dir($upload_dir) ? 'YES' : 'NO') . "</p>";
        echo "<p>Directory writable: " . (is_writable($upload_dir) ? 'YES' : 'NO') . "</p>";
        
        if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_info = pathinfo($_FILES['photo']['name']);
            $photo_filename = 'test_photo_' . time() . '.' . $file_info['extension'];
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo_filename)) {
                echo "<p style='color: green;'>Photo uploadée avec succès: " . $photo_filename . "</p>";
                echo "<img src='uploads/photos/" . $photo_filename . "' style='max-width: 200px;'>";
            } else {
                echo "<p style='color: red;'>Erreur lors de l'upload</p>";
            }
        } else {
            echo "<p style='color: red;'>Erreur upload: " . $_FILES['photo']['error'] . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Upload Photo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Test d'upload de photo</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="photo" class="form-label">Sélectionner une photo</label>
                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary">Tester l'upload</button>
        </form>
        
        <hr>
        <h3>Photos existantes</h3>
        <?php
        $photos_dir = __DIR__ . '/uploads/photos/';
        if (is_dir($photos_dir)) {
            $files = scandir($photos_dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && $file !== '.gitkeep') {
                    echo "<p>$file - <img src='uploads/photos/$file' style='max-width: 100px; margin-left: 10px;'></p>";
                }
            }
        }
        ?>
    </div>
</body>
</html>
