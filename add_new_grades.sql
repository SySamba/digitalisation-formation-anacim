-- Ajouter les nouveaux grades: vérificateur stagiaire et vérificateur titulaire
-- Changer le nom de la colonne specialiste à specialite

-- Modifier la colonne grade pour ajouter les nouveaux grades
ALTER TABLE agents 
MODIFY COLUMN grade ENUM(
    'cadre_technique', 
    'agent_technique', 
    'inspecteur_stagiaire', 
    'inspecteur_titulaire', 
    'inspecteur_principal',
    'verificateur_stagiaire',
    'verificateur_titulaire'
) NULL;

-- Renommer la colonne specialiste en specialite
ALTER TABLE agents 
CHANGE COLUMN specialiste specialite VARCHAR(255);
