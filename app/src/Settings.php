<?php
declare(strict_types=1);

namespace Batons;

/** Réglages par utilisateur (table user_settings). */
final class Settings
{
    public static function get(int $userId, string $name, ?string $default = null): ?string
    {
        $v = Db::value('SELECT value FROM user_settings WHERE user_id = ? AND name = ?', [$userId, $name]);
        return $v === null || $v === '' ? $default : (string) $v;
    }

    public static function set(int $userId, string $name, ?string $value): void
    {
        Db::run('DELETE FROM user_settings WHERE user_id = ? AND name = ?', [$userId, $name]);
        if ($value !== null && $value !== '') {
            Db::run('INSERT INTO user_settings (user_id, name, value) VALUES (?, ?, ?)', [$userId, $name, $value]);
        }
    }

    /** Clé RAWG (jeux vidéo) : réglage de l'utilisateur, sinon BATONS_RAWG_KEY. */
    public static function rawgKey(int $userId): string
    {
        return (string) (self::get($userId, 'rawg_key') ?? Config::get('rawg_key') ?? '');
    }

    /** Clé TMDB : réglage de l'utilisateur, sinon BATONS_TMDB_KEY. */
    public static function tmdbKey(int $userId): string
    {
        return (string) (self::get($userId, 'tmdb_key') ?? Config::get('tmdb_key') ?? '');
    }
}
