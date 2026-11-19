-- Option « périodes » sur une catégorie simple : les bâtons peuvent marquer un début et une fin (events.milestone).
ALTER TABLE categories ADD COLUMN periods INTEGER NOT NULL DEFAULT 0;
