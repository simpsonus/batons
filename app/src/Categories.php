<?php
declare(strict_types=1);

namespace Batons;

/** Dépôt des catégories (et des groupes) d'un utilisateur. */
final class Categories
{
    /** Liste avec résumé (dernier bâton, compte du jour), triée par groupe puis position. */
    public static function listWithSummary(int $userId, bool $archived = false): array
    {
        [$from, $to] = Time::periodUtc('today');
        return Db::all(
            'SELECT c.*, g.name AS group_name,
                    (SELECT MAX(e.occurred_at) FROM events e WHERE e.category_id = c.id) AS last_at,
                    (SELECT COUNT(*) FROM events e WHERE e.category_id = c.id
                        AND e.occurred_at >= ? AND e.occurred_at < ?) AS today_count,
                    (SELECT SUM(e.value) FROM events e WHERE e.category_id = c.id
                        AND e.occurred_at >= ? AND e.occurred_at < ?) AS today_sum
               FROM categories c
               LEFT JOIN groups g ON g.id = c.group_id
              WHERE c.user_id = ? AND c.archived = ?
              ORDER BY CASE WHEN g.id IS NULL THEN 1 ELSE 0 END, g.position, g.name, c.position, c.name',
            [$from, $to, $from, $to, $userId, $archived ? 1 : 0]
        );
    }

    public static function find(int $userId, int $id): ?array
    {
        return Db::one(
            'SELECT c.*, g.name AS group_name FROM categories c
               LEFT JOIN groups g ON g.id = c.group_id
              WHERE c.id = ? AND c.user_id = ?',
            [$id, $userId]
        );
    }

    public static function groupNames(int $userId): array
    {
        return array_column(Db::all('SELECT name FROM groups WHERE user_id = ? ORDER BY position, name', [$userId]), 'name');
    }

    /** Valide et normalise les champs d'un formulaire. Renvoie [données, erreurs]. */
    public static function validate(array $in): array
    {
        $errors = [];
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($name) > 80) {
            $errors['name'] = '80 caractères maximum.';
        }
        $icon = trim((string) ($in['icon'] ?? ''));
        if ($icon === '') {
            $icon = '|';
        }
        if (preg_match('/^mdi[:\-]/i', $icon)) {
            $icon = 'mdi:' . strtolower(substr($icon, 4)); // accepte aussi « mdi-coffee » ou « MDI:Coffee »
        }
        if (!Icons::isValid($icon)) {
            $errors['icon'] = str_starts_with($icon, 'mdi:')
                ? 'Icône inconnue : « ' . $icon . ' ».'
                : 'Une icône courte : un émoji ou un ou deux caractères.';
        }
        // Une pastille de la palette (radio « color ») ou, à défaut, le sélecteur libre (« color_custom »).
        $color = strtolower(trim((string) ($in['color'] ?? $in['color_custom'] ?? '')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $color = '#c2410c';
        }
        $unit = trim((string) ($in['unit'] ?? ''));
        return [[
            'name'  => $name,
            'icon'  => $icon,
            'color' => $color,
            'unit'  => $unit === '' ? null : mb_substr($unit, 0, 20),
            'group' => trim((string) ($in['group'] ?? '')),
        ], $errors];
    }

    public static function create(int $userId, array $d): int
    {
        $pos = (int) Db::value('SELECT COALESCE(MAX(position), 0) + 1 FROM categories WHERE user_id = ?', [$userId]);
        Db::run(
            'INSERT INTO categories (user_id, group_id, name, icon, color, unit, position, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, self::groupId($userId, $d['group']), $d['name'], $d['icon'], $d['color'], $d['unit'], $pos, Time::nowUtc()]
        );
        return Db::lastId();
    }

    public static function update(int $userId, int $id, array $d): void
    {
        Db::run(
            'UPDATE categories SET group_id = ?, name = ?, icon = ?, color = ?, unit = ?
              WHERE id = ? AND user_id = ?',
            [self::groupId($userId, $d['group']), $d['name'], $d['icon'], $d['color'], $d['unit'], $id, $userId]
        );
        self::pruneGroups($userId);
    }

    public static function setArchived(int $userId, int $id, bool $archived): void
    {
        Db::run('UPDATE categories SET archived = ? WHERE id = ? AND user_id = ?', [$archived ? 1 : 0, $id, $userId]);
    }

    public static function delete(int $userId, int $id): void
    {
        Db::run('DELETE FROM categories WHERE id = ? AND user_id = ?', [$id, $userId]);
        self::pruneGroups($userId);
    }

    /** Déplace une catégorie d'un cran vers le haut (-1) ou le bas (+1) parmi toutes celles de l'utilisateur. */
    public static function move(int $userId, int $id, int $dir): void
    {
        $ids = array_column(Db::all(
            'SELECT id FROM categories WHERE user_id = ? AND archived = 0 ORDER BY position, name', [$userId]), 'id');
        $i = array_search($id, array_map('intval', $ids), true);
        $j = $i === false ? false : $i + $dir;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $pos => $cid) {
            Db::run('UPDATE categories SET position = ? WHERE id = ?', [$pos, $cid]);
        }
    }

    /** Id du groupe portant ce nom (créé si besoin), ou null si nom vide. */
    private static function groupId(int $userId, string $name): ?int
    {
        if ($name === '') {
            return null;
        }
        $id = Db::value('SELECT id FROM groups WHERE user_id = ? AND name = ?', [$userId, $name]);
        if ($id !== null) {
            return (int) $id;
        }
        $pos = (int) Db::value('SELECT COALESCE(MAX(position), 0) + 1 FROM groups WHERE user_id = ?', [$userId]);
        Db::run('INSERT INTO groups (user_id, name, position) VALUES (?, ?, ?)', [$userId, $name, $pos]);
        return Db::lastId();
    }

    /** Supprime les groupes devenus vides. */
    private static function pruneGroups(int $userId): void
    {
        Db::run('DELETE FROM groups WHERE user_id = ? AND id NOT IN (SELECT group_id FROM categories WHERE group_id IS NOT NULL)', [$userId]);
    }
}
