<?php
declare(strict_types=1);

namespace Batons;

/**
 * Sauvegarde quotidienne de la base SQLite dans data_dir/backups (batons-AAAA-MM-JJ.sqlite), en gardant les
 * BATONS_BACKUP_DAYS derniers jours (7 par défaut, 0 = désactivé). Déclenchée par la première requête du jour,
 * après l'envoi de la page : personne n'attend. « VACUUM INTO » produit une copie cohérente même en mode WAL.
 */
final class Backup
{
    public static function dir(): string
    {
        return Config::get('data_dir') . '/backups';
    }

    public static function keepDays(): int
    {
        return max(0, (int) Config::get('backup_days'));
    }

    /** Liste des sauvegardes existantes, la plus récente d'abord : [chemin => taille]. */
    public static function list(): array
    {
        $files = glob(self::dir() . '/batons-*.sqlite') ?: [];
        rsort($files);
        $out = [];
        foreach ($files as $f) {
            $out[$f] = (int) @filesize($f);
        }
        return $out;
    }

    public static function maybeRun(): void
    {
        $keep = self::keepDays();
        if ($keep === 0 || !str_starts_with((string) Config::get('db_dsn'), 'sqlite:')) {
            return;
        }
        $dir = self::dir();
        $today = $dir . '/batons-' . date('Y-m-d') . '.sqlite';
        if (is_file($today) || (!is_dir($dir) && !@mkdir($dir, 0700, true))) {
            return;
        }
        $lock = fopen($dir . '/.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return;                                   // une autre requête s'en charge
        }
        try {
            if (!is_file($today)) {
                $tmp = $today . '.tmp';
                @unlink($tmp);
                Db::pdo()->exec('VACUUM INTO ' . Db::pdo()->quote($tmp));
                rename($tmp, $today);
                @chmod($today, 0600);
            }
            foreach (array_slice(array_keys(self::list()), $keep) as $old) {
                @unlink($old);
            }
        } catch (\Throwable $e) {
            error_log('Bâtons : sauvegarde impossible : ' . $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
