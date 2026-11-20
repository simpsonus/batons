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
        $now = Time::nowUtc();
        // « Compter les bâtons à venir » décoché : la tuile ignore les bâtons datés de plus tard.
        $f = ' AND (c.count_future = 1 OR e.occurred_at <= ?)';
        return Db::all(
            'SELECT c.*, g.name AS group_name, g.hidden AS group_hidden,
                    (SELECT MAX(e.occurred_at) FROM events e WHERE e.category_id = c.id' . $f . ') AS last_at,
                    (SELECT COUNT(*) FROM events e WHERE e.category_id = c.id
                        AND e.occurred_at >= ? AND e.occurred_at < ?' . $f . ') AS today_count,
                    (SELECT SUM(e.value) FROM events e WHERE e.category_id = c.id
                        AND e.occurred_at >= ? AND e.occurred_at < ?' . $f . ') AS today_sum,
                    (SELECT e.id FROM events e WHERE e.category_id = c.id' . $f . '
                      ORDER BY e.occurred_at DESC, e.id DESC LIMIT 1) AS last_event_id
               FROM categories c
               LEFT JOIN groups g ON g.id = c.group_id
              WHERE c.user_id = ? AND c.archived = ?' . Privacy::visibleSql() . '
              ORDER BY CASE WHEN g.id IS NULL THEN 1 ELSE 0 END, g.position, g.name, c.position, c.name',
            [$now, $from, $to, $now, $from, $to, $now, $now, $userId, $archived ? 1 : 0]
        );
    }

    public static function find(int $userId, int $id): ?array
    {
        return Db::one(
            'SELECT c.*, g.name AS group_name, g.hidden AS group_hidden FROM categories c
               LEFT JOIN groups g ON g.id = c.group_id
              WHERE c.id = ? AND c.user_id = ?' . Privacy::visibleSql(),
            [$id, $userId]
        );
    }

    public static function groupNames(int $userId): array
    {
        return array_column(Db::all('SELECT name FROM groups WHERE user_id = ?' . self::hiddenGroupSql() . ' ORDER BY position, name', [$userId]), 'name');
    }

    /** Valide et normalise les champs d'un formulaire. Renvoie [données, erreurs]. */
    public static function validate(array $in): array
    {
        $errors = [];
        $name = trim((string) ($in['label'] ?? $in['name'] ?? ''));   // « label » : évite le remplissage auto de contact de Safari
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
            'hidden'=> !empty($in['hidden']),
            'periods' => !empty($in['periods']) && ($in['kind'] ?? 'simple') === 'simple',
            'count_future' => !empty($in['count_future']),
            'kind'  => in_array($in['kind'] ?? 'simple', ['simple', 'film', 'serie', 'livre', 'jeu'], true) ? $in['kind'] : 'simple',
        ], $errors];
    }

    public static function create(int $userId, array $d): int
    {
        $pos = (int) Db::value('SELECT COALESCE(MAX(position), 0) + 1 FROM categories WHERE user_id = ?', [$userId]);
        Db::run(
            'INSERT INTO categories (user_id, group_id, name, icon, color, unit, position, hidden, kind, periods, count_future, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, self::groupId($userId, $d['group']), $d['name'], $d['icon'], $d['color'], $d['unit'], $pos,
             !empty($d['hidden']) ? 1 : 0, $d['kind'] ?? 'simple', !empty($d['periods']) ? 1 : 0, !empty($d['count_future']) ? 1 : 0, Time::nowUtc()]
        );
        return Db::lastId();
    }

    public static function update(int $userId, int $id, array $d): void
    {
        Db::run(
            'UPDATE categories SET group_id = ?, name = ?, icon = ?, color = ?, unit = ?, hidden = ?, kind = ?, periods = ?, count_future = ?
              WHERE id = ? AND user_id = ?',
            [self::groupId($userId, $d['group']), $d['name'], $d['icon'], $d['color'], $d['unit'],
             !empty($d['hidden']) ? 1 : 0, $d['kind'] ?? 'simple', !empty($d['periods']) ? 1 : 0, !empty($d['count_future']) ? 1 : 0, $id, $userId]
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

    /** Déplace une catégorie d'un cran vers le haut (-1) ou le bas (+1) à l'intérieur de son groupe. */
    public static function move(int $userId, int $id, int $dir): void
    {
        $c = self::find($userId, $id);
        if ($c === null) {
            return;
        }
        $ids = $c['group_id'] === null
            ? Db::all('SELECT id FROM categories WHERE user_id = ? AND archived = 0 AND group_id IS NULL' . self::hiddenCatSql() . ' ORDER BY position, name', [$userId])
            : Db::all('SELECT id FROM categories WHERE user_id = ? AND archived = 0 AND group_id = ?' . self::hiddenCatSql() . ' ORDER BY position, name', [$userId, $c['group_id']]);
        self::swap('categories', array_map('intval', array_column($ids, 'id')), $id, $dir);
    }

    /** Déplace un groupe d'un cran vers le haut (-1) ou le bas (+1). */
    public static function moveGroup(int $userId, int $groupId, int $dir): void
    {
        $ids = Db::all('SELECT id FROM groups WHERE user_id = ?' . self::hiddenGroupSql() . ' ORDER BY position, name', [$userId]);
        self::swap('groups', array_map('intval', array_column($ids, 'id')), $groupId, $dir);
    }

    /**
     * Nouvel ordre complet issu du glisser-déposer : $groupOrder = ids de groupes dans l'ordre affiché,
     * $catOrder = [id de groupe (0 = sans groupe) => ids de catégories dans l'ordre affiché].
     * Les éléments non affichés (masqués et verrouillés, archivés) gardent leur place relative.
     * Renvoie false si la demande ne correspond pas aux éléments visibles (page périmée).
     */
    public static function reorder(int $userId, array $groupOrder, array $catOrder): bool
    {
        $visGroups = array_map('intval', array_column(self::groups($userId), 'id'));
        $visCats = array_map('intval', array_column(Db::all(
            'SELECT c.id FROM categories c LEFT JOIN groups g ON g.id = c.group_id
              WHERE c.user_id = ? AND c.archived = 0' . Privacy::visibleSql(), [$userId]), 'id'));
        $groupOrder = array_values(array_map('intval', $groupOrder));
        $sentCats = [];
        foreach ($catOrder as $gid => $ids) {
            if ((int) $gid !== 0 && !in_array((int) $gid, $visGroups, true)) {
                return false;
            }
            foreach ($ids as $id) {
                $sentCats[] = (int) $id;
            }
        }
        $sorted = static function (array $a): array { sort($a); return $a; };
        if ($sorted($groupOrder) !== $sorted($visGroups) || $sorted($sentCats) !== $sorted($visCats)) {
            return false;
        }

        Db::pdo()->beginTransaction();
        // Groupes : les visibles prennent, dans le nouvel ordre, les places des visibles.
        $all = array_map('intval', array_column(Db::all('SELECT id FROM groups WHERE user_id = ? ORDER BY position, name', [$userId]), 'id'));
        self::renumber('groups', self::merge($all, $visGroups, $groupOrder));
        // Catégories : groupe par groupe (y compris « sans groupe »).
        foreach ($catOrder as $gid => $ids) {
            $gid = (int) $gid;
            $ids = array_map('intval', $ids);
            foreach ($ids as $id) {
                Db::run('UPDATE categories SET group_id = ? WHERE id = ? AND user_id = ?', [$gid ?: null, $id, $userId]);
            }
            $members = array_map('intval', array_column($gid
                ? Db::all('SELECT id FROM categories WHERE user_id = ? AND group_id = ? ORDER BY position, name', [$userId, $gid])
                : Db::all('SELECT id FROM categories WHERE user_id = ? AND group_id IS NULL ORDER BY position, name', [$userId]), 'id'));
            self::renumber('categories', self::merge($members, array_values(array_intersect($members, $visCats)), $ids));
        }
        Db::pdo()->commit();
        self::pruneGroups($userId);
        return true;
    }

    /** Place $newOrder sur les emplacements de $visible dans $all ; les autres éléments ne bougent pas. */
    private static function merge(array $all, array $visible, array $newOrder): array
    {
        $out = [];
        $queue = $newOrder;
        foreach ($all as $id) {
            if (in_array($id, $visible, true) || in_array($id, $newOrder, true)) {
                if ($queue) {
                    $out[] = array_shift($queue);
                }
            } else {
                $out[] = $id;
            }
        }
        return array_values(array_unique(array_merge($out, $queue)));
    }

    private static function renumber(string $table, array $ids): void
    {
        $table = $table === 'groups' ? 'groups' : 'categories'; // liste blanche
        foreach ($ids as $pos => $id) {
            Db::run("UPDATE $table SET position = ? WHERE id = ?", [$pos + 1, $id]);
        }
    }

    public static function group(int $userId, int $id): ?array
    {
        return Db::one('SELECT * FROM groups WHERE id = ? AND user_id = ?' . self::hiddenGroupSql(), [$id, $userId]);
    }

    /** Autre groupe portant exactement ce nom (y compris masqué), ou null. */
    public static function groupNamed(int $userId, string $name, int $exceptId): ?array
    {
        return Db::one('SELECT * FROM groups WHERE user_id = ? AND name = ? AND id <> ?', [$userId, $name, $exceptId]);
    }

    public static function renameGroup(int $userId, int $id, string $name): void
    {
        Db::run('UPDATE groups SET name = ? WHERE id = ? AND user_id = ?', [$name, $id, $userId]);
    }

    /** Réunit les catégories du groupe $from dans le groupe $into (à la suite), puis supprime $from. */
    public static function mergeGroups(int $userId, int $from, int $into): void
    {
        $pos = (int) Db::value('SELECT COALESCE(MAX(position), 0) FROM categories WHERE user_id = ? AND group_id = ?', [$userId, $into]);
        foreach (Db::all('SELECT id FROM categories WHERE user_id = ? AND group_id = ? ORDER BY position, name', [$userId, $from]) as $r) {
            Db::run('UPDATE categories SET group_id = ?, position = ? WHERE id = ?', [$into, ++$pos, $r['id']]);
        }
        Db::run('DELETE FROM groups WHERE id = ? AND user_id = ?', [$from, $userId]);
    }

    /** Groupes de l'utilisateur, dans l'ordre. */
    public static function groups(int $userId): array
    {
        return Db::all('SELECT * FROM groups WHERE user_id = ?' . self::hiddenGroupSql() . ' ORDER BY position, name', [$userId]);
    }

    private static function hiddenGroupSql(): string
    {
        return Privacy::isUnlocked() ? '' : ' AND hidden = 0';
    }

    private static function hiddenCatSql(): string
    {
        return Privacy::isUnlocked() ? '' : ' AND hidden = 0';
    }

    /** Échange un élément avec son voisin dans la liste ordonnée $ids, puis renumérote les positions. */
    private static function swap(string $table, array $ids, int $id, int $dir): void
    {
        $i = array_search($id, $ids, true);
        $j = $i === false ? false : $i + $dir;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $table = $table === 'groups' ? 'groups' : 'categories'; // liste blanche
        foreach ($ids as $pos => $rowId) {
            Db::run("UPDATE $table SET position = ? WHERE id = ?", [$pos + 1, $rowId]);
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
