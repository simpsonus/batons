-- Option « Compter les bâtons à venir dans les statistiques » (v0.24).
-- Nouvelles catégories : non (on compte ce qui est arrivé). Catégories existantes : oui (comportement de la v0.23.1).
ALTER TABLE categories ADD COLUMN count_future INTEGER NOT NULL DEFAULT 0;
UPDATE categories SET count_future = 1;
