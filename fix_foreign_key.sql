-- 1. Désactiver temporairement la vérification des clés étrangères
SET FOREIGN_KEY_CHECKS = 0;

-- 2. Créer un requérant par défaut pour les enregistrements orphelins
INSERT INTO requerent (nom, prenom, email, date_creation)
VALUES ('Inconnu', 'Inconnu', 'inconnu@example.com', NOW());

-- 3. Récupérer l'ID du requérant par défaut
SET @default_requerent_id = LAST_INSERT_ID();

-- 4. Mettre à jour les enregistrements problématiques pour les associer au requérant par défaut
UPDATE extrait 
SET requerent_id = @default_requerent_id 
WHERE requerent_id = 0 OR requerent_id IS NULL;

-- 5. Modifier la colonne requerent_id pour qu'elle ne puisse pas être NULL
ALTER TABLE extrait 
MODIFY COLUMN requerent_id INT NOT NULL;

-- 6. Ajouter la contrainte de clé étrangère
ALTER TABLE extrait
ADD CONSTRAINT fk_extrait_requerent
FOREIGN KEY (requerent_id) REFERENCES requerent(id);

-- 7. Réactiver la vérification des clés étrangères
SET FOREIGN_KEY_CHECKS = 1;

-- Afficher un message de confirmation
SELECT 'Opération terminée avec succès !' AS message;
