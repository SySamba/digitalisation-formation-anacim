<?php
// Fonctions utilitaires pour le système de formation

function uploadFile($file, $upload_dir, $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Paramètres de fichier invalides.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('Aucun fichier envoyé.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Fichier trop volumineux.');
        default:
            throw new RuntimeException('Erreur inconnue.');
    }

    if ($file['size'] > 10000000) { // 10MB max
        throw new RuntimeException('Fichier trop volumineux (max 10MB).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_types)) {
        throw new RuntimeException('Type de fichier non autorisé.');
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = sprintf('%s_%s.%s',
        uniqid(),
        date('Y-m-d_H-i-s'),
        $extension
    );

    $filepath = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new RuntimeException('Échec du téléchargement du fichier.');
    }

    return $filename;
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function getGradeLabel($grade) {
    $grades = [
        'cadre_technique' => 'Cadre Technique',
        'agent_technique' => 'Agent Technique',
        'inspecteur_stagiaire' => 'Inspecteur Stagiaire',
        'inspecteur_titulaire' => 'Inspecteur Titulaire',
        'inspecteur_principal' => 'Inspecteur Principal',
        'verificateur_stagiaire' => 'Vérificateur Stagiaire',
        'verificateur_titulaire' => 'Vérificateur Titulaire'
    ];
    
    return $grades[$grade] ?? $grade;
}

function getCategorieLabel($categorie) {
    $categories = [
        'FAMILIARISATION' => 'Familiarisation',
        'FORMATION_INITIALE' => 'Formation Initiale',
        'FORMATION_COURS_EMPLOI' => 'Formation en Cours d\'Emploi',
        'FORMATION_TECHNIQUE' => 'Formation Technique/Spécialisée'
    ];
    
    return $categories[$categorie] ?? $categorie;
}

function getStatutLabel($statut) {
    $statuts = [
        'planifie' => 'Planifié',
        'confirme' => 'Confirmé',
        'reporte' => 'Reporté',
        'annule' => 'Annulé',
        'en_cours' => 'En Cours',
        'termine' => 'Terminé',
        'valide' => 'Validé'
    ];
    
    return $statuts[$statut] ?? $statut;
}

function calculerJoursRestants($date_echeance) {
    $aujourd_hui = new DateTime();
    $echeance = new DateTime($date_echeance);
    $diff = $aujourd_hui->diff($echeance);
    
    if ($echeance < $aujourd_hui) {
        return -$diff->days; // Négatif si expiré
    }
    
    return $diff->days;
}

function getAlertClass($jours_restants) {
    if ($jours_restants < 0) {
        return 'alert-danger'; // Expiré
    } elseif ($jours_restants <= 30) {
        return 'alert-warning'; // Expire bientôt
    } elseif ($jours_restants <= 90) {
        return 'alert-info'; // À surveiller
    }
    
    return 'alert-success'; // OK
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function generateMatricule() {
    return 'ANA' . date('Y') . sprintf('%04d', rand(1, 9999));
}

function logActivity($action, $details, $user_id = null) {
    // Log des activités pour audit
    $log_file = '../logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] User: $user_id | Action: $action | Details: $details" . PHP_EOL;
    
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function sendNotification($type, $message, $recipient = null) {
    // Système de notifications (peut être étendu pour email, SMS, etc.)
    $_SESSION['notifications'][] = [
        'type' => $type, // success, warning, error, info
        'message' => $message,
        'timestamp' => time()
    ];
}

function getNotifications() {
    $notifications = $_SESSION['notifications'] ?? [];
    $_SESSION['notifications'] = []; // Clear after reading
    return $notifications;
}

function isInspecteurTitulaire($grade) {
    return $grade === 'inspecteur_titulaire';
}

function validateInspecteurTitulaireFields($data) {
    $required_fields = ['date_nomination', 'numero_badge', 'date_validite_badge', 'date_prestation_serment'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "Le champ '$field' est obligatoire pour un inspecteur titulaire.";
        }
    }
    
    return $errors;
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}
?>
