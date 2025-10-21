<?php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'classes/Agent.php';
require_once 'classes/Formation.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$agent = new Agent($db);
$formation = new Formation($db);

// Récupérer tous les agents inscrits
$agents = $agent->read();
$formations_a_renouveler = $formation->getFormationsExpireesOuAExpirer(90);

$total_agents = count($agents);
$formations_urgentes = count(array_filter($formations_a_renouveler, function($f) {
    return $f['jours_restants'] <= 30;
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Système de Gestion des Formations ANACIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #124c97;
            --danger-color: #ff011e;
            --warning-color: #f5df35;
            --dark-primary: #0a3570;
        }
        
        .navbar-custom {
            background-color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-primary);
            border-color: var(--dark-primary);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
        }
        
        .logo-header {
            max-height: 40px;
            margin-right: 10px;
            background-color: white;
            padding: 5px;
            border-radius: 5px;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: #000;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(18, 76, 151, 0.1);
        }
        
        .logo-header {
            max-height: 40px;
            margin-right: 10px;
            background-color: white;
            padding: 5px;
            border-radius: 5px;
        }
        
        .stat-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger-color);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="admin.php">
                <img src="logo-anacim.png" alt="ANACIM" class="logo-header">
                <span>Administration - Gestion des Formations</span>
            </a>
            <div class="navbar-nav ms-auto">
                <ul class="navbar-nav">
                    <!--
                    <li class="nav-item">
                        <a class="nav-link" href="admin_planning.php"><i class="fas fa-calendar"></i> Planning</a>
                    </li>
                    -->
                    <li class="nav-item">
                        <a class="nav-link" href="register.php"><i class="fas fa-home"></i> Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Total Agents Inscrits</h5>
                                <h2 class="text-primary"><?= $total_agents ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Formations Urgentes</h5>
                                <h2 class="text-danger"><?= $formations_urgentes ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agents List -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-users"></i> Liste des Agents Inscrits</h4>
            </div>
            <div class="card-body">
                <?php if (empty($agents)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Aucun agent inscrit pour le moment.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Photo</th>
                                    <th>Matricule</th>
                                    <th>Nom & Prénom</th>
                                    <th>Grade</th>
                                    <th>Structure</th>
                                    <th>Date Inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents as $ag): ?>
                                    <tr>
                                        <td>
                                            <?php if ($ag['photo']): ?>
                                                <img src="uploads/photos/<?= htmlspecialchars($ag['photo']) ?>" 
                                                     class="rounded-circle" width="40" height="40" alt="Photo">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($ag['matricule']) ?></strong></td>
                                        <td><?= htmlspecialchars($ag['prenom'] . ' ' . $ag['nom']) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= getGradeLabel($ag['grade']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($ag['structure_attache'] ?? 'Non spécifiée') ?></td>
                                        <td><?= formatDate($ag['created_at']) ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="viewAgent(<?= $ag['id'] ?>)">
                                                <i class="fas fa-eye"></i> Voir Plus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for Agent Details -->
    <div class="modal fade" id="agentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de l'Agent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="agentModalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewAgent(agentId) {
            console.log('Loading agent details for ID:', agentId);
            
            fetch('ajax/get_agent_details.php?id=' + agentId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    const modalContent = document.getElementById('agentModalBody');
                    if (modalContent) {
                        modalContent.innerHTML = html;
                        
                        // Déclencher l'événement pour initialiser les listeners
                        window.dispatchEvent(new CustomEvent('agentContentLoaded'));
                        
                        // Ouvrir la modal
                        const modal = new bootstrap.Modal(document.getElementById('agentModal'));
                        modal.show();
                    } else {
                        console.error('Modal content element not found');
                        alert('Erreur: Élément modal non trouvé');
                    }
                })
                .catch(error => {
                    console.error('Error loading agent details:', error);
                    alert('Erreur lors du chargement des détails de l\'agent: ' + error.message);
                });
        }

        // Fonction pour recharger les détails d'un agent (utilisée après planification)
        function loadAgentDetails(agentId) {
            console.log('Reloading agent details for ID:', agentId);
            
            fetch('ajax/get_agent_details.php?id=' + agentId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    const modalContent = document.getElementById('agentModalBody');
                    if (modalContent) {
                        modalContent.innerHTML = html;
                        
                        // Déclencher l'événement pour initialiser les listeners
                        window.dispatchEvent(new CustomEvent('agentContentLoaded'));
                    } else {
                        console.error('Modal content element not found');
                    }
                })
                .catch(error => {
                    console.error('Error reloading agent details:', error);
                    alert('Erreur lors du rechargement: ' + error.message);
                });
        }

        // Fonction pour planifier une formation
        function planifierFormation(agentId, formationId, formationNom) {
            // Créer le modal de planification
            const modalHtml = `
                <div class="modal fade" id="planificationModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Planifier une Formation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="planificationForm">
                                    <input type="hidden" name="agent_id" value="${agentId}">
                                    <input type="hidden" name="formation_id" value="${formationId}">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Formation</label>
                                        <input type="text" class="form-control" value="${formationNom}" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Centre de formation *</label>
                                        <select class="form-select" name="centre_formation_prevu" required>
                                            <option value="">Sélectionner...</option>
                                            <option value="ENAC">ENAC</option>
                                            <option value="ANACIM">ANACIM</option>
                                            <option value="ERNAM">ERNAM</option>
                                            <option value="ITAerea">ITAerea</option>
                                            <option value="IFURTA">IFURTA</option>
                                            <option value="EPT">EPT</option>
                                            <option value="IFNPC">IFNPC</option>
                                            <option value="EMAERO services">EMAERO services</option>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Date de début *</label>
                                                <input type="date" class="form-control" name="date_prevue_debut" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Date de fin *</label>
                                                <input type="date" class="form-control" name="date_prevue_fin" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Commentaires</label>
                                        <textarea class="form-control" name="commentaires" rows="3" placeholder="Commentaires optionnels..."></textarea>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="button" class="btn btn-primary" onclick="sauvegarderPlanification()">Planifier</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Ajouter le modal au DOM s'il n'existe pas
            if (!document.getElementById('planificationModal')) {
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            } else {
                document.getElementById('planificationModal').outerHTML = modalHtml;
            }
            
            // Afficher le modal
            new bootstrap.Modal(document.getElementById('planificationModal')).show();
        }

        // Fonction pour sauvegarder la planification
        function sauvegarderPlanification() {
            const form = document.getElementById('planificationForm');
            const formData = new FormData(form);
            
            fetch('ajax/save_planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Formation planifiée avec succès!');
                    bootstrap.Modal.getInstance(document.getElementById('planificationModal')).hide();
                    // Recharger les détails de l'agent
                    const agentModal = document.getElementById('agentModal');
                    if (agentModal && bootstrap.Modal.getInstance(agentModal)) {
                        const agentId = formData.get('agent_id');
                        viewAgent(agentId);
                    }
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la planification');
            });
        }

        // Global function for agent section navigation
        function showAgentSection(sectionId) {
            console.log('Switching to section:', sectionId);
            
            // Masquer toutes les sections dans la modal
            const modal = document.querySelector('.modal-body') || document.querySelector('#agentModal .modal-body') || document;
            
            modal.querySelectorAll('.agent-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Retirer la classe active de tous les boutons de navigation
            modal.querySelectorAll('.btn-group button, [id^="btn-"]').forEach(btn => {
                btn.classList.remove('btn-primary', 'active');
                btn.classList.add('btn-outline-primary');
            });
            
            // Afficher la section sélectionnée
            const targetSection = modal.querySelector('#' + sectionId);
            if (targetSection) {
                targetSection.style.display = 'block';
                console.log('Section found and displayed:', sectionId);
            } else {
                console.error('Section not found:', sectionId);
            }
            
            // Activer le bouton correspondant
            const activeBtn = modal.querySelector('#btn-' + sectionId);
            if (activeBtn) {
                activeBtn.classList.remove('btn-outline-primary');
                activeBtn.classList.add('btn-primary', 'active');
                console.log('Button activated:', 'btn-' + sectionId);
            } else {
                console.error('Button not found:', 'btn-' + sectionId);
            }
        }

        // Global function for planning section navigation
        function showPlanningSection(sectionId) {
            console.log('Switching to planning section:', sectionId);
            
            // Chercher dans la modal spécifiquement
            const modal = document.querySelector('#agentModal .modal-body') || document.querySelector('.modal-body') || document;
            
            // Masquer toutes les sous-sections de planning
            const planningSections = modal.querySelectorAll('.planning-subsection');
            console.log('Found planning subsections:', planningSections.length);
            
            planningSections.forEach(section => {
                section.style.display = 'none';
                section.classList.remove('active');
            });
            
            // Retirer la classe active de tous les boutons de navigation du planning
            const planningButtons = modal.querySelectorAll('[id^="btn-"][id$="-agent"]');
            console.log('Found planning buttons:', planningButtons.length);
            
            planningButtons.forEach(btn => {
                btn.classList.remove('btn-primary', 'active');
                btn.classList.add('btn-outline-primary');
            });
            
            // Afficher la sous-section sélectionnée
            const targetSection = modal.querySelector('#' + sectionId);
            if (targetSection) {
                targetSection.style.display = 'block';
                targetSection.classList.add('active');
                console.log('Planning section displayed:', sectionId);
            } else {
                console.error('Planning section not found:', sectionId);
            }
            
            // Activer le bouton correspondant
            const activeBtn = modal.querySelector('#btn-' + sectionId);
            if (activeBtn) {
                activeBtn.classList.remove('btn-outline-primary');
                activeBtn.classList.add('btn-primary', 'active');
                console.log('Planning button activated:', 'btn-' + sectionId);
            } else {
                console.error('Planning button not found:', 'btn-' + sectionId);
            }
        }

        // Global function for planning formation from agent details
        function planifierFormationAgent(agentId, formationId) {
            console.log('=== planifierFormationAgent called ===');
            console.log('Agent ID:', agentId, 'Formation ID:', formationId);
            console.log('Types:', typeof agentId, typeof formationId);
            
            // Afficher un message de succès pour la navigation
            const modal = document.querySelector('#agentModal .modal-body');
            if (modal) {
                const existingAlert = modal.querySelector('.planning-navigation-alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-info alert-dismissible fade show planning-navigation-alert';
                alertDiv.innerHTML = `
                    <i class="fas fa-info-circle"></i> Navigation vers la planification de formation...
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                modal.insertBefore(alertDiv, modal.firstChild);
                
                // Supprimer l'alerte après 3 secondes
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 3000);
            }
            
            // D'abord s'assurer qu'on est dans la section planning
            showAgentSection('planning');
            
            // Puis passer à la sous-section planification
            setTimeout(() => {
                showPlanningSection('planifier-agent');
                
                // Attendre un peu plus pour que les éléments soient bien chargés
                setTimeout(() => {
                    // Chercher dans le contexte de la modal spécifiquement
                    const modalContext = document.querySelector('#agentModal');
                    const formationSelect = modalContext ? 
                        modalContext.querySelector('#planifier-agent select[name="formation_id"]') : 
                        document.querySelector('#planifier-agent select[name="formation_id"]');
                    
                    console.log('Formation select found:', !!formationSelect);
                    console.log('Modal context found:', !!modalContext);
                    
                    if (formationSelect) {
                        const formationIdStr = String(formationId);
                        console.log('Trying to set formation:', formationIdStr);
                        console.log('Available options:');
                        
                        // Afficher toutes les options disponibles pour debug
                        Array.from(formationSelect.options).forEach((option, index) => {
                            console.log(`  [${index}] value: "${option.value}" (type: ${typeof option.value}), text: "${option.text}"`);
                        });
                        
                        // Essayer plusieurs approches pour sélectionner la formation
                        let optionFound = false;
                        
                        // Approche 1: Recherche par valeur exacte
                        Array.from(formationSelect.options).forEach((option, index) => {
                            if (option.value === formationIdStr || option.value === String(formationId) || option.value == formationId) {
                                formationSelect.selectedIndex = index;
                                option.selected = true;
                                optionFound = true;
                                console.log('✓ Formation found and selected at index:', index, 'value:', option.value);
                                return;
                            }
                        });
                        
                        // Approche 2: Si pas trouvé, essayer par contenu du texte
                        if (!optionFound) {
                            console.log('Trying to find by text content...');
                            Array.from(formationSelect.options).forEach((option, index) => {
                                if (option.text.includes(formationIdStr) || option.value.toString() === formationIdStr) {
                                    formationSelect.selectedIndex = index;
                                    option.selected = true;
                                    optionFound = true;
                                    console.log('✓ Formation found by text at index:', index, 'text:', option.text);
                                    return;
                                }
                            });
                        }
                        
                        // Approche 3: Forcer la sélection avec setAttribute
                        if (!optionFound) {
                            console.log('Trying setAttribute approach...');
                            const targetOption = Array.from(formationSelect.options).find(option => 
                                option.value == formationId || option.value === String(formationId)
                            );
                            if (targetOption) {
                                // Désélectionner toutes les options
                                Array.from(formationSelect.options).forEach(opt => opt.selected = false);
                                // Sélectionner la bonne option
                                targetOption.selected = true;
                                targetOption.setAttribute('selected', 'selected');
                                formationSelect.value = targetOption.value;
                                optionFound = true;
                                console.log('✓ Formation set with setAttribute:', targetOption.value);
                            }
                        }
                        
                        if (!optionFound) {
                            console.error('✗ Could not find matching option for formation ID:', formationId);
                            console.log('Available option values:', Array.from(formationSelect.options).map(o => o.value));
                        } else {
                            // Déclencher l'événement change pour s'assurer que la sélection est prise en compte
                            formationSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            
                            // Mettre en évidence visuellement la sélection
                            formationSelect.style.backgroundColor = '#e8f5e8';
                            setTimeout(() => {
                                formationSelect.style.backgroundColor = '';
                            }, 2000);
                        }
                    } else {
                        console.error('Formation select element not found');
                        // Debug: lister tous les selects disponibles
                        const allSelects = document.querySelectorAll('select');
                        console.log('Available select elements:', allSelects.length);
                        allSelects.forEach((select, i) => {
                            console.log(`Select ${i}:`, select.name, select.id, select.className);
                        });
                    }
                }, 1000);
            }, 500);
        }

        // Fonctions globales pour le planning
        function filterByYearAgent() {
            console.log('=== DEBUT FILTRAGE PAR ANNEE ===');
            
            // Toujours chercher dans la modal car c'est là que se trouve le planning
            const modalBody = document.querySelector('#agentModal .modal-body');
            if (!modalBody) {
                console.error('Modal body non trouvé');
                return;
            }
            
            const yearFilter = modalBody.querySelector('#yearFilterAgent');
            if (!yearFilter) {
                console.error('Filtre année non trouvé dans la modal');
                return;
            }
            
            const selectedYear = yearFilter.value;
            console.log('Année sélectionnée:', selectedYear);
            
            // Chercher les sections d'agents dans la modal
            const agentSections = modalBody.querySelectorAll('.agent-planning-section');
            console.log('Sections d\'agents trouvées:', agentSections.length);
            
            agentSections.forEach((section, index) => {
                console.log(`Traitement section ${index + 1}:`, section);
                
                if (!selectedYear) {
                    // Afficher toutes les sections si aucune année n'est sélectionnée
                    section.style.display = '';
                    const rows = section.querySelectorAll('tr[data-year]');
                    console.log(`Section ${index + 1} - Lignes trouvées:`, rows.length);
                    rows.forEach(row => {
                        row.style.display = '';
                        console.log('Ligne affichée:', row.getAttribute('data-year'));
                    });
                    
                    // Afficher toutes les catégories
                    const categories = section.querySelectorAll('.category-section');
                    categories.forEach(cat => cat.style.display = '');
                } else {
                    // Vérifier si cette section d'agent a des formations pour l'année sélectionnée
                    const rows = section.querySelectorAll('tr[data-year]');
                    let hasFormationsInYear = false;
                    
                    console.log(`Section ${index + 1} - Filtrage pour année ${selectedYear}, lignes:`, rows.length);
                    
                    rows.forEach(row => {
                        const rowYear = row.getAttribute('data-year');
                        console.log(`Ligne année: ${rowYear}, recherchée: ${selectedYear}`);
                        if (rowYear === selectedYear) {
                            row.style.display = '';
                            hasFormationsInYear = true;
                            console.log('Ligne affichée pour année correspondante');
                        } else {
                            row.style.display = 'none';
                            console.log('Ligne masquée');
                        }
                    });
                    
                    // Masquer toute la section de l'agent s'il n'a pas de formations cette année
                    if (hasFormationsInYear) {
                        section.style.display = '';
                        console.log(`Section ${index + 1} affichée (a des formations)`);
                        
                        // Afficher/masquer les catégories selon qu'elles ont des formations visibles
                        const categoryDivs = section.querySelectorAll('.category-section');
                        categoryDivs.forEach(categoryDiv => {
                            const visibleRows = categoryDiv.querySelectorAll('tr[data-year]:not([style*="none"])');
                            if (visibleRows.length > 0) {
                                categoryDiv.style.display = '';
                            } else {
                                categoryDiv.style.display = 'none';
                            }
                        });
                    } else {
                        section.style.display = 'none';
                        console.log(`Section ${index + 1} masquée (pas de formations)`);
                    }
                }
            });
            
            console.log('=== FIN FILTRAGE PAR ANNEE ===');
        }

        function downloadPlanningAgent() {
            console.log('=== DEBUT TELECHARGEMENT ===');
            
            // Chercher dans la modal
            const modalBody = document.querySelector('#agentModal .modal-body');
            if (!modalBody) {
                alert('Erreur: Modal non trouvée');
                return;
            }
            
            const yearFilter = modalBody.querySelector('#yearFilterAgent');
            const planningContent = modalBody.querySelector('#planningGlobalContent');
            
            console.log('Filtre année trouvé:', !!yearFilter);
            console.log('Contenu planning trouvé:', !!planningContent);
            
            if (!planningContent) {
                alert('Aucun contenu de planning à télécharger.');
                return;
            }
            
            const selectedYear = yearFilter ? yearFilter.value : '';
            const yearText = selectedYear ? ` - Année ${selectedYear}` : '';
            
            console.log('Téléchargement pour l\'année:', selectedYear || 'toutes');
            
            // Cloner le contenu pour le manipuler sans affecter l'affichage
            const contentClone = planningContent.cloneNode(true);
            
            // Si un filtre d'année est actif, supprimer les éléments masqués
            if (selectedYear) {
                // Supprimer les sections d'agents masquées
                contentClone.querySelectorAll('.agent-planning-section').forEach(section => {
                    if (section.style.display === 'none') {
                        section.remove();
                    } else {
                        // Supprimer les catégories masquées
                        section.querySelectorAll('.category-section').forEach(cat => {
                            if (cat.style.display === 'none') {
                                cat.remove();
                            } else {
                                // Supprimer les lignes masquées
                                cat.querySelectorAll('tr[data-year]').forEach(row => {
                                    if (row.style.display === 'none') {
                                        row.remove();
                                    }
                                });
                            }
                        });
                    }
                });
            }
            
            // Supprimer les boutons d'action dans le clone
            contentClone.querySelectorAll('.btn-group').forEach(btnGroup => btnGroup.remove());
            
            const printContent = `
                <html>
                <head>
                    <title>Planning des Formations${yearText}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .logo { max-height: 60px; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #124c97; color: white; font-weight: bold; }
                        .text-primary { color: #124c97; font-weight: bold; }
                        .text-secondary { color: #6c757d; font-style: italic; }
                        .border-bottom { border-bottom: 2px solid #124c97; padding-bottom: 5px; margin-bottom: 10px; }
                        .mb-3, .mb-4 { margin-bottom: 15px; }
                        .badge { 
                            display: inline-block; 
                            padding: 3px 8px; 
                            border-radius: 3px; 
                            font-size: 11px;
                            font-weight: bold;
                        }
                        .bg-success { background-color: #28a745; color: white; }
                        .bg-primary { background-color: #124c97; color: white; }
                        .bg-warning { background-color: #ffc107; color: black; }
                        .bg-secondary { background-color: #6c757d; color: white; }
                        h6 { margin-top: 15px; margin-bottom: 8px; }
                        @media print { 
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <img src="logo-anacim.png" alt="ANACIM" class="logo">
                        <h1>PLANNING DES FORMATIONS${yearText}</h1>
                        <p>Généré le ${new Date().toLocaleDateString('fr-FR')}</p>
                    </div>
                    ${contentClone.innerHTML}
                </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Attendre que le document soit chargé avant d'imprimer
            printWindow.onload = function() {
                console.log('Document prêt pour l\'impression');
                printWindow.print();
            };
            
            // Fallback si onload ne fonctionne pas
            setTimeout(() => {
                console.log('Tentative d\'impression via fallback');
                printWindow.print();
            }, 1000);
            
            console.log('=== FIN TELECHARGEMENT ===');
        }
    </script>
</body>
</html>
