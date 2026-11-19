-- Comptes avec mot de passe (mode d'authentification « builtin »).
-- users.password_hash existe depuis 001.

ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN password_failures INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN password_blocked_until TEXT;

-- Échecs de connexion par adresse IP (limitation globale, quel que soit l'identifiant tenté).
CREATE TABLE login_failures (
    ip TEXT NOT NULL,
    at TEXT NOT NULL
);
CREATE INDEX idx_login_failures_ip ON login_failures (ip, at);
