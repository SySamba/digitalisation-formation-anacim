<?php
session_start();

// Si déjà connecté, rediriger vers le profil
if (isset($_SESSION['agent_logged_in']) && $_SESSION['agent_logged_in'] === true) {
    header('Location: agent_profile.php');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/database.php';
    require_once 'classes/Agent.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $agent = new Agent($db);
    
    $matricule = $_POST['matricule'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Vérifier les identifiants (matricule + email)
    $query = "SELECT * FROM agents WHERE matricule = ? AND email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$matricule, $email]);
    $agent_data = $stmt->fetch();
    
    if ($agent_data) {
        $_SESSION['agent_logged_in'] = true;
        $_SESSION['agent_id'] = $agent_data['id'];
        $_SESSION['agent_matricule'] = $agent_data['matricule'];
        $_SESSION['agent_nom'] = $agent_data['prenom'] . ' ' . $agent_data['nom'];
        $_SESSION['agent_email'] = $agent_data['email'];
        header('Location: agent_profile.php');
        exit;
    } else {
        $error_message = 'Matricule ou email incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Agent - ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #124c97;
            --danger-color: #ff011e;
            --warning-color: #f5df35;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color), #1e5bb8);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .login-header {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 12px 30px;
        }
        
        .btn-primary:hover {
            background-color: #0a3570;
            border-color: #0a3570;
        }
        
        .logo-login {
            max-height: 60px;
            margin-bottom: 1rem;
            background-color: white;
            padding: 10px;
            border-radius: 10px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(18, 76, 151, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <img src="logo-anacim.png" alt="ANACIM" class="logo-login">
                        <h4>Espace Agent</h4>
                        <p class="mb-0">Accès à votre profil</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="matricule" class="form-label">Matricule</label>
                                <input type="text" class="form-control" id="matricule" name="matricule" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Se connecter
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <a href="register.php" class="text-decoration-none">
                                <i class="fas fa-user-plus"></i>  Inscription
                            </a>
                            <br><br>
                            <a href="admin.php" class="text-decoration-none">
                                <i class="fas fa-cog"></i> Administration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
