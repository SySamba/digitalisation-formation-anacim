<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Agent.php';
require_once __DIR__ . '/../classes/Formation.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $agent = new Agent($db);
    $formationEffectuee = new FormationEffectuee($db);

    // Validation des données de base
    $required_fields = ['matricule', 'prenom', 'nom', 'date_recrutement', 'grade'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est obligatoire.");
        }
    }

    // Validation spéciale pour inspecteur titulaire
    if ($_POST['grade'] === 'inspecteur_titulaire') {
        $errors = validateInspecteurTitulaireFields($_POST);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors));
        }
    }

    // Gestion de l'upload des fichiers
    $photo_filename = null;
    $cv_filename = null;
    $diplome_filename = null;
    $attestation_filename = null;

    // Upload photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/photos';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $photo_filename = uploadFile($_FILES['photo'], $upload_dir, ['jpg', 'jpeg', 'png']);
    }

    // Upload CV (obligatoire)
    if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Le CV est obligatoire.');
    }
    $upload_dir = __DIR__ . '/../uploads/diplomes';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $cv_filename = uploadFile($_FILES['cv'], $upload_dir, ['pdf', 'doc', 'docx']);

    // Upload Diplôme (obligatoire)
    if (!isset($_FILES['diplome']) || $_FILES['diplome']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Le diplôme est obligatoire.');
    }
    $diplome_filename = uploadFile($_FILES['diplome'], $upload_dir, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

    // Upload Attestation (obligatoire)
    if (!isset($_FILES['attestation']) || $_FILES['attestation']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('L\'attestation est obligatoire.');
    }
    $attestation_filename = uploadFile($_FILES['attestation'], $upload_dir, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

    // Préparation des données agent
    $agent_data = [
        'matricule' => sanitizeInput($_POST['matricule']),
        'prenom' => sanitizeInput($_POST['prenom']),
        'nom' => sanitizeInput($_POST['nom']),
        'date_recrutement' => $_POST['date_recrutement'],
        'structure_attache' => sanitizeInput($_POST['structure_attache'] ?? ''),
        'domaine_activites' => sanitizeInput($_POST['domaine_activites'] ?? ''),
        'specialite' => sanitizeInput($_POST['specialite'] ?? ''),
        'grade' => $_POST['grade'],
        'date_nomination' => $_POST['date_nomination'] ?? null,
        'numero_badge' => sanitizeInput($_POST['numero_badge'] ?? ''),
        'date_validite_badge' => $_POST['date_validite_badge'] ?? null,
        'date_prestation_serment' => $_POST['date_prestation_serment'] ?? null,
        'photo' => $photo_filename
    ];

    // Validation des dates
    if (!validateDate($agent_data['date_recrutement'])) {
        throw new Exception('Date de recrutement invalide.');
    }

    if ($agent_data['grade'] === 'inspecteur_titulaire') {
        if (!validateDate($agent_data['date_nomination']) || 
            !validateDate($agent_data['date_validite_badge']) || 
            !validateDate($agent_data['date_prestation_serment'])) {
            throw new Exception('Une ou plusieurs dates pour l\'inspecteur titulaire sont invalides.');
        }
    }

    // Commencer une transaction
    $db->beginTransaction();

    try {
        // Créer l'agent
        if (!$agent->create($agent_data)) {
            throw new Exception('Erreur lors de la création de l\'agent.');
        }

        // Récupérer l'ID de l'agent créé
        $agent_id = $db->lastInsertId();

        // Enregistrer les diplômes académiques
        if ($cv_filename) {
            $stmt = $db->prepare("INSERT INTO diplomes_academiques (agent_id, type_diplome, fichier_path) VALUES (?, ?, ?)");
            $stmt->execute([$agent_id, 'CV', $cv_filename]);
        }
        
        if ($diplome_filename) {
            $stmt = $db->prepare("INSERT INTO diplomes_academiques (agent_id, type_diplome, fichier_path) VALUES (?, ?, ?)");
            $stmt->execute([$agent_id, 'Diplôme', $diplome_filename]);
        }
        
        if ($attestation_filename) {
            $stmt = $db->prepare("INSERT INTO diplomes_academiques (agent_id, type_diplome, fichier_path) VALUES (?, ?, ?)");
            $stmt->execute([$agent_id, 'Attestation', $attestation_filename]);
        }

        // Traiter les formations effectuées sélectionnées
        if (!empty($_POST['formations_effectuees'])) {
            $formations_effectuees = $_POST['formations_effectuees'];
            $centres_formation = $_POST['centre_formation'] ?? [];
            $dates_debut = $_POST['date_debut'] ?? [];
            $dates_fin = $_POST['date_fin'] ?? [];

            foreach ($formations_effectuees as $formation_id) {
                // Vérifier que les dates sont fournies
                if (empty($dates_debut[$formation_id]) || empty($dates_fin[$formation_id])) {
                    throw new Exception("Les dates de début et fin sont obligatoires pour toutes les formations sélectionnées.");
                }

                // Gestion de l'upload du certificat pour cette formation
                $certificat_filename = null;
                if (isset($_FILES['certificat']['tmp_name'][$formation_id]) && 
                    $_FILES['certificat']['error'][$formation_id] === UPLOAD_ERR_OK) {
                    
                    $upload_dir = __DIR__ . '/../uploads/formations';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Reconstituer le tableau $_FILES pour cette formation
                    $file_data = [
                        'name' => $_FILES['certificat']['name'][$formation_id],
                        'type' => $_FILES['certificat']['type'][$formation_id],
                        'tmp_name' => $_FILES['certificat']['tmp_name'][$formation_id],
                        'error' => $_FILES['certificat']['error'][$formation_id],
                        'size' => $_FILES['certificat']['size'][$formation_id]
                    ];

                    $certificat_filename = uploadFile($file_data, $upload_dir, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
                }

                // Créer l'enregistrement de formation effectuée
                $formation_data = [
                    'agent_id' => $agent_id,
                    'formation_id' => $formation_id,
                    'centre_formation' => sanitizeInput($centres_formation[$formation_id] ?? ''),
                    'date_debut' => $dates_debut[$formation_id],
                    'date_fin' => $dates_fin[$formation_id],
                    'fichier_joint' => $certificat_filename,
                    'statut' => 'valide'
                ];

                if (!$formationEffectuee->create($formation_data)) {
                    throw new Exception('Erreur lors de l\'enregistrement d\'une formation effectuée.');
                }
            }
        }

        // Valider la transaction
        $db->commit();

        logActivity('REGISTER_AGENT', "Nouvel agent inscrit: {$agent_data['matricule']} - {$agent_data['prenom']} {$agent_data['nom']} avec " . count($_POST['formations_effectuees'] ?? []) . " formations");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Inscription réussie ! Votre profil a été créé avec succès.',
            'agent_id' => $agent_id
        ]);

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
