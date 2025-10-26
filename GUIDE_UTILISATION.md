# Guide d'Utilisation - Planification des Formations

## 🎯 Comment planifier une formation non effectuée

### Étape 1 : Accéder à la fiche de l'agent
1. Connectez-vous en tant qu'administrateur
2. Allez sur la page **admin.php**
3. Cliquez sur le bouton **"Voir"** à côté d'un agent

### Étape 2 : Trouver les formations non effectuées
1. Dans la fiche de l'agent, vous verrez plusieurs sections
2. Cherchez la section **"Formations Non Effectuées"**
3. Cette section liste toutes les formations que l'agent n'a jamais faites

### Étape 3 : Cliquer sur "Planifier"
1. À côté de chaque formation non effectuée, vous verrez un bouton bleu **"Planifier"**
2. Cliquez sur ce bouton
3. Vous serez redirigé vers la page de planification

### Étape 4 : Vérifier la pré-sélection
Quand la page s'ouvre, vous devriez voir :
- ✅ Un message bleu : "Pré-sélection active : L'agent et la formation ont été automatiquement sélectionnés"
- ✅ L'agent déjà sélectionné dans le premier menu déroulant
- ✅ La formation déjà sélectionnée dans le deuxième menu déroulant

### Étape 5 : Remplir les champs obligatoires
Remplissez les champs suivants :

**Champs obligatoires :**
- **Centre de Formation** : Sélectionnez le centre (ex: ANACIM, ENAC, etc.)
- **Date de début** : Date de début de la formation
- **Date de fin** : Date de fin de la formation
- **Ville** : Ville où se déroule la formation (ex: Dakar)
- **Pays** : Pays de la formation (ex: Sénégal)
- **Durée (jours)** : Nombre de jours (ex: 5)
- **Priorité** : Choisissez entre :
  - **1 - Très élevé** (urgent)
  - **2 - Moyen** (important)
  - **3 - Moins élevé** (normal)

**Champs optionnels :**
- **Perdiem (FCFA)** : Montant du perdiem en francs CFA (ex: 50000)
- **Statut** : Planifié ou Confirmé
- **Commentaires** : Notes supplémentaires

### Étape 6 : Enregistrer
1. Cliquez sur le bouton **"Planifier la Formation"**
2. Un message de succès s'affiche
3. Vous êtes redirigé vers la section "Planning Existant"

### Étape 7 : Vérifier le planning
Dans la section **"Planning Existant"**, vous verrez :
- La formation planifiée avec tous les détails
- Les colonnes : Formation, Centre, **Lieu** (Ville, Pays), Dates, **Durée**, **Priorité**, Statut, Actions

---

## 🔧 Test de la fonctionnalité

Pour tester que tout fonctionne correctement :

1. **Test de pré-sélection** : http://localhost/digitalisation-formation/test_preselection.php
2. **Test des champs** : http://localhost/digitalisation-formation/test_planning_display.php

---

## ⚠️ Dépannage

### Le bouton "Planifier" ne fait rien
- Vérifiez que vous êtes connecté en tant qu'administrateur
- Vérifiez que JavaScript est activé dans votre navigateur
- Essayez de rafraîchir la page (F5)

### L'agent ou la formation ne sont pas pré-sélectionnés
- Vérifiez l'URL : elle doit contenir `?section=planifier&agent_id=X&formation_id=Y`
- Vérifiez que les IDs existent dans la base de données
- Consultez les commentaires HTML (clic droit → Inspecter) pour voir les valeurs de debug

### Les nouveaux champs ne s'affichent pas
- Exécutez le script SQL `add_planning_fields.sql` dans phpMyAdmin
- Vérifiez avec http://localhost/digitalisation-formation/test_planning_display.php

---

## 📊 Signification des priorités

- **Priorité 1 (Rouge)** : Formation très urgente, à planifier immédiatement
- **Priorité 2 (Jaune)** : Formation importante, à planifier prochainement
- **Priorité 3 (Bleu)** : Formation normale, peut être planifiée plus tard

---

**Date de création** : 25 octobre 2025
**Version** : 1.0
