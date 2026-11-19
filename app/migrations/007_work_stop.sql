-- « J'arrête là » : l'œuvre (série de livres, série TV, jeu…) n'est plus proposée dans « Continuer ».
-- Effacé automatiquement dès qu'un nouveau bâton est ajouté pour cette œuvre.
ALTER TABLE works ADD COLUMN stopped_at TEXT;
