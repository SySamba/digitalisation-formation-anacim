-- Ajouter les nouveaux champs à la table planning_formations
-- Ville, Pays, Durée, Perdium, Priorité

ALTER TABLE planning_formations 
ADD COLUMN ville VARCHAR(255) AFTER centre_formation_prevu,
ADD COLUMN pays VARCHAR(255) AFTER ville,
ADD COLUMN duree INT COMMENT 'Durée en jours' AFTER pays,
ADD COLUMN perdiem DECIMAL(10,2) AFTER duree,
ADD COLUMN priorite ENUM('1', '2', '3') DEFAULT '3' COMMENT '1=Très élevé, 2=Moyen, 3=Moins élevé' AFTER perdiem;
