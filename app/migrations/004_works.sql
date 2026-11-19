-- Catégories « œuvre » : films, séries, livres.

ALTER TABLE categories ADD COLUMN kind TEXT NOT NULL DEFAULT 'simple';   -- simple | film | serie | livre

CREATE TABLE works (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    kind        TEXT    NOT NULL,                 -- film | serie | livre
    source      TEXT    NOT NULL,                 -- tmdb | tvmaze | gbooks | openlibrary | manual
    source_id   TEXT    NOT NULL,
    title       TEXT    NOT NULL,
    subtitle    TEXT,                             -- auteur(s), titre original…
    year        INTEGER,
    has_poster  INTEGER NOT NULL DEFAULT 0,
    meta        TEXT,                             -- JSON : saisons {"1": 10, "2": 8} pour les séries
    meta_at     TEXT,
    created_at  TEXT    NOT NULL,
    UNIQUE (user_id, source, source_id)
);

ALTER TABLE events ADD COLUMN work_id INTEGER REFERENCES works(id) ON DELETE SET NULL;
ALTER TABLE events ADD COLUMN season  INTEGER;
ALTER TABLE events ADD COLUMN episode INTEGER;
ALTER TABLE events ADD COLUMN volume  INTEGER;
ALTER TABLE events ADD COLUMN rating  INTEGER;     -- 1 à 10
CREATE INDEX idx_events_work ON events (work_id, occurred_at);

-- Réglages par utilisateur (clé TMDB…).
CREATE TABLE user_settings (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name    TEXT    NOT NULL,
    value   TEXT,
    PRIMARY KEY (user_id, name)
);

-- Cache des réponses des catalogues.
CREATE TABLE http_cache (
    key        TEXT PRIMARY KEY,
    body       TEXT NOT NULL,
    fetched_at INTEGER NOT NULL
);
