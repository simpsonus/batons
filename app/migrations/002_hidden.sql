-- Éléments masqués (catégories, groupes) et moyens de déverrouillage (code, passkeys).

ALTER TABLE categories ADD COLUMN hidden INTEGER NOT NULL DEFAULT 0;
ALTER TABLE groups     ADD COLUMN hidden INTEGER NOT NULL DEFAULT 0;

-- Code de déverrouillage : haché (password_hash), avec limitation des essais.
ALTER TABLE users ADD COLUMN pin_hash TEXT;
ALTER TABLE users ADD COLUMN pin_failures INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN pin_blocked_until TEXT;

-- Passkeys (WebAuthn) : Face ID, Touch ID, Windows Hello, empreinte Android…
CREATE TABLE passkeys (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    credential_id TEXT    NOT NULL UNIQUE,   -- base64url
    public_key    TEXT    NOT NULL,          -- PEM (SPKI)
    rp_id         TEXT    NOT NULL,          -- domaine pour lequel la passkey a été créée
    sign_count    INTEGER NOT NULL DEFAULT 0,
    name          TEXT    NOT NULL,
    created_at    TEXT    NOT NULL,
    last_used_at  TEXT
);
CREATE INDEX idx_passkeys_user ON passkeys (user_id);
