<?php
declare(strict_types=1);

namespace Batons;

/**
 * Éléments masqués : catégories et groupes invisibles tant que l'utilisateur n'a pas déverrouillé
 * (code à 6 chiffres ou passkey). Comme les photos masquées d'Apple : rien n'indique qu'il en existe.
 *
 * Ce n'est PAS du chiffrement : c'est un écran de confidentialité. Les données restent lisibles
 * par quiconque a accès à la base.
 *
 * Le déverrouillage est mémorisé dans la session, lié à l'utilisateur, et expire après
 * BATONS_HIDDEN_TIMEOUT secondes d'inactivité (300 par défaut).
 */
final class Privacy
{
    public const PIN_LENGTH = 6;
    public const MAX_FAILURES = 5;
    public const BLOCK_MINUTES = 15;

    public static function timeout(): int
    {
        $t = (int) (Config::get('hidden_timeout') ?? 300);
        return $t > 0 ? $t : 300;
    }

    /** Déverrouillé ? Prolonge la session d'inactivité à chaque appel (une fois par requête). */
    private static ?bool $unlocked = null;

    public static function isUnlocked(): bool
    {
        if (self::$unlocked !== null) {
            return self::$unlocked;
        }
        $u = $_SESSION['unlocked'] ?? null;
        if (!is_array($u) || ($u['user'] ?? null) !== Auth::id() || ($u['until'] ?? 0) < time()) {
            unset($_SESSION['unlocked']);
            return self::$unlocked = false;
        }
        $_SESSION['unlocked']['until'] = time() + self::timeout();
        return self::$unlocked = true;
    }

    /** Secondes avant reverrouillage (0 si verrouillé). */
    public static function remaining(): int
    {
        return self::isUnlocked() ? max(0, (int) $_SESSION['unlocked']['until'] - time()) : 0;
    }

    public static function unlock(): void
    {
        session_regenerate_id(true);
        $_SESSION['unlocked'] = ['user' => Auth::id(), 'until' => time() + self::timeout()];
        self::$unlocked = true;
    }

    public static function lock(): void
    {
        unset($_SESSION['unlocked']);
        self::$unlocked = false;
    }

    /**
     * Fragment SQL limitant aux éléments visibles. À ajouter après un WHERE, avec les alias
     * de la catégorie et du groupe (LEFT JOIN groups requis).
     */
    public static function visibleSql(string $c = 'c', string $g = 'g'): string
    {
        return self::isUnlocked() ? '' : " AND $c.hidden = 0 AND ($g.id IS NULL OR $g.hidden = 0)";
    }

    // --- Code ------------------------------------------------------------------

    public static function hasPin(int $userId): bool
    {
        return Db::value('SELECT pin_hash FROM users WHERE id = ?', [$userId]) !== null;
    }

    public static function validPinFormat(string $pin): bool
    {
        return (bool) preg_match('/^\d{' . self::PIN_LENGTH . '}$/', $pin);
    }

    public static function setPin(int $userId, string $pin): void
    {
        Db::run('UPDATE users SET pin_hash = ?, pin_failures = 0, pin_blocked_until = NULL WHERE id = ?',
            [password_hash($pin, PASSWORD_DEFAULT), $userId]);
    }

    /** Secondes de blocage restantes après trop d'essais ratés (0 si non bloqué). */
    public static function blockedFor(int $userId): int
    {
        $until = Db::value('SELECT pin_blocked_until FROM users WHERE id = ?', [$userId]);
        return $until ? max(0, strtotime($until . ' UTC') - time()) : 0;
    }

    /** Vérifie le code ; renvoie true, ou un message d'erreur. Compte les échecs et bloque au-delà du maximum. */
    public static function checkPin(int $userId, string $pin): true|string
    {
        if (($wait = self::blockedFor($userId)) > 0) {
            return 'Trop d\'essais. Réessaie dans ' . (int) ceil($wait / 60) . ' min.';
        }
        $row = Db::one('SELECT pin_hash, pin_failures FROM users WHERE id = ?', [$userId]);
        if ($row === null || $row['pin_hash'] === null) {
            return 'Aucun code n\'est défini.';
        }
        if (self::validPinFormat($pin) && password_verify($pin, $row['pin_hash'])) {
            Db::run('UPDATE users SET pin_failures = 0, pin_blocked_until = NULL WHERE id = ?', [$userId]);
            return true;
        }
        $fails = (int) $row['pin_failures'] + 1;
        if ($fails >= self::MAX_FAILURES) {
            Db::run('UPDATE users SET pin_failures = 0, pin_blocked_until = ? WHERE id = ?',
                [gmdate('Y-m-d H:i:s', time() + self::BLOCK_MINUTES * 60), $userId]);
            return 'Code incorrect. Trop d\'essais : déverrouillage bloqué pendant ' . self::BLOCK_MINUTES . ' min.';
        }
        Db::run('UPDATE users SET pin_failures = ? WHERE id = ?', [$fails, $userId]);
        $left = self::MAX_FAILURES - $fails;
        return "Code incorrect. Encore $left essai" . ($left > 1 ? 's' : '') . '.';
    }

    /** Données de la page « Confidentialité ». */
    public static function settings(int $userId): array
    {
        return [
            'passkeys' => Db::all('SELECT id, name, created_at, last_used_at FROM passkeys WHERE user_id = ? ORDER BY created_at', [$userId]),
            'timeout'  => self::timeout(),
            'hiddenCount' => (int) Db::value(
                'SELECT COUNT(*) FROM categories c LEFT JOIN groups g ON g.id = c.group_id
                  WHERE c.user_id = ? AND (c.hidden = 1 OR g.hidden = 1)', [$userId]),
        ];
    }

    // --- Masquage -------------------------------------------------------------------

    public static function setGroupHidden(int $userId, int $groupId, bool $hidden): void
    {
        Db::run('UPDATE groups SET hidden = ? WHERE id = ? AND user_id = ?', [$hidden ? 1 : 0, $groupId, $userId]);
    }

    /** Noms (en minuscules) des catégories masquées, directement ou via leur groupe. */
    public static function hiddenCategoryNames(int $userId): array
    {
        return array_map('mb_strtolower', array_column(Db::all(
            'SELECT c.name FROM categories c LEFT JOIN groups g ON g.id = c.group_id
              WHERE c.user_id = ? AND (c.hidden = 1 OR g.hidden = 1)', [$userId]), 'name'));
    }
}
