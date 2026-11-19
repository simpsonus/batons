-- Étapes d'une œuvre (jeux vidéo) : 'start' (commencé) ou 'finish' (terminé). NULL = bâton ordinaire (partie).
ALTER TABLE events ADD COLUMN milestone TEXT;
