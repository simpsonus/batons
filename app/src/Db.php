<?php
declare(strict_types=1);

namespace Batons;

use PDO;

/**
 * Accès base de données via PDO.
 * Le SQL des dépôts reste aussi standard que possible pour garder la porte ouverte à PostgreSQL.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = (string) Config::get('db_dsn');
        if (str_starts_with($dsn, 'sqlite:')) {
            $dir = dirname(substr($dsn, 7));
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException("Impossible de créer le dossier de données : $dir");
            }
        }

        $pdo = new PDO($dsn, Config::get('db_user'), Config::get('db_password'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            // fold(texte) : minuscules sans accents, pour chercher « ecole » dans « École » (recherche de la Chronologie).
            // En PostgreSQL, l'équivalent serait lower(unaccent(…)).
            $pdo->sqliteCreateFunction('fold', static fn($v) => $v === null ? null : self::fold((string) $v), 1, PDO::SQLITE_DETERMINISTIC);
        }

        self::$pdo = $pdo;
        self::migrate();
        return $pdo;
    }

    /** Requête préparée, renvoie le statement. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /** Applique les fichiers migrations/NNN_*.sql pas encore passés. */
    private static function migrate(): void
    {
        $pdo = self::$pdo;
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
        $done = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $done, true)) {
                continue;
            }
            $pdo->beginTransaction();
            try {
                $pdo->exec((string) file_get_contents($file));
                $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
                $stmt->execute([$version, gmdate('Y-m-d H:i:s')]);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }

    /** Minuscules sans accents (é -> e, œ -> oe…), pour comparer des textes saisis à la main. */
    public static function fold(string $s): string
    {
        static $map = null;
        $map ??= array_combine(
            mb_str_split('àâäáãåçéèêëíìîïñóòôöõúùûüýÿ'),
            str_split('aaaaaaceeeeiiiinooooouuuuyy')
        ) + ['æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', '’' => "'"];
        return strtr(mb_strtolower($s), $map);
    }
}
