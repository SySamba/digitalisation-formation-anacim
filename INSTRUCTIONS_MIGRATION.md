# Instructions de Migration - Digitalisation Formation ANACIM

## Modifications à appliquer

### 1. Nouveaux grades et changement de "spécialiste" à "spécialité"

**Fichier SQL** : `add_new_grades.sql`

**Via phpMyAdmin** :
1. Ouvrez phpMyAdmin (http://localhost/phpmyadmin)
2. Sélectionnez la base de données `formation_anacim`
3. Cliquez sur l'onglet "SQL"
4. Copiez et collez le contenu du fichier `add_new_grades.sql`
5. Cliquez sur "Exécuter"

**Via ligne de commande MySQL** :
```bash
mysql -u root formation_anacim < add_new_grades.sql
```

---

### 2. Nouveaux champs pour le planning de formation

**Fichier SQL** : `add_planning_fields.sql`

**Via phpMyAdmin** :
1. Ouvrez phpMyAdmin (http://localhost/phpmyadmin)
2. Sélectionnez la base de données `formation_anacim`
3. Cliquez sur l'onglet "SQL"
4. Copiez et collez le contenu du fichier `add_planning_fields.sql`
5. Cliquez sur "Exécuter"

**Via ligne de commande MySQL** :
```bash
mysql -u root formation_anacim < add_planning_fields.sql
```

---

## Vérification des modifications

Après avoir exécuté les scripts, vérifiez que :

### Table `agents`
- La colonne `specialiste` a été renommée en `specialite`
- Le champ `grade` accepte maintenant les valeurs :
  - cadre_technique
  - agent_technique
  - inspecteur_stagiaire
  - inspecteur_titulaire
  - inspecteur_principal
  - **verificateur_stagiaire** (nouveau)
  - **verificateur_titulaire** (nouveau)

### Table `planning_formations`
- Nouveaux champs ajoutés :
  - `ville` VARCHAR(255)
  - `pays` VARCHAR(255)
  - `duree` INT (durée en jours)
  - `perdiem` DECIMAL(10,2)
  - `priorite` ENUM('1', '2', '3') avec valeur par défaut '3'

---

## Résumé des fonctionnalités ajoutées

### ✅ Grades
- Ajout de "Vérificateur Stagiaire" et "Vérificateur Titulaire"
- Changement de "Spécialiste" à "Spécialité" partout dans le système

### ✅ Planning de formation
- **Ville** : Lieu de la formation (obligatoire)
- **Pays** : Pays de la formation (obligatoire)
- **Durée** : Nombre de jours (obligatoire)
- **Perdiem** : Montant en FCFA (optionnel)
- **Priorité** : 
  - 1 = Très élevé (rouge)
  - 2 = Moyen (jaune)
  - 3 = Moins élevé (bleu)

### ✅ Bouton Planning
- Les formations non effectuées par l'agent ont maintenant un bouton "Planifier"
- Le système détecte automatiquement la formation concernée
- Badge "Jamais effectuée" pour identifier facilement ces formations

---

## En cas de problème

Si vous rencontrez des erreurs lors de l'exécution des scripts :

1. Vérifiez que la base de données `formation_anacim` existe
2. Vérifiez que vous avez les droits d'administration
3. Si la colonne existe déjà, commentez la ligne correspondante dans le script SQL
4. Consultez les logs d'erreur MySQL pour plus de détails

---

**Date de création** : 25 octobre 2025
**Version** : 1.0
