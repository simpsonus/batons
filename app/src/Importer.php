<?php
declare(strict_types=1);

namespace Batons;

/**
 * Import de données :
 *  - exports de l'app iOS Chronicling (fichiers events, et facultativement categories et groups) ;
 *  - export CSV de Bâtons lui-même (restauration d'une sauvegarde).
 *
 * Les catégories sont rapprochées par nom (sans tenir compte de la casse) : si elles existent déjà, on les
 * garde telles quelles ; sinon on les crée. Un bâton identique (même catégorie, même instant à la seconde)
 * n'est jamais importé deux fois : on peut relancer un import sans risque de doublons.
 */
final class Importer
{
    /** Couleurs nommées iOS -> palette Bâtons. */
    private const COLORS = [
        'red' => '#b91c1c', 'orange' => '#c2410c', 'yellow' => '#a16207', 'green' => '#047857',
        'mint' => '#0e7490', 'teal' => '#0e7490', 'cyan' => '#0e7490', 'blue' => '#1d4ed8',
        'indigo' => '#4338ca', 'purple' => '#7c3aed', 'pink' => '#be185d', 'magenta' => '#be185d',
        'brown' => '#78350f', 'gray' => '#57534e', 'grey' => '#57534e', 'black' => '#1c1917',
    ];

    /** SF Symbols (icônes Apple) les plus courants -> Material Design Icons. */
    private const SF_SYMBOLS = [
        'watch.analog' => 'watch', 'applewatch' => 'watch', 'message' => 'message', 'bubble.left' => 'message',
        'bed.double' => 'bed-double', 'bed' => 'bed', 'cross.vial' => 'test-tube', 'cross' => 'medical-bag',
        'cross.case' => 'medical-bag', 'pills' => 'pill', 'pill' => 'pill', 'syringe' => 'needle',
        'bandage' => 'bandage', 'book' => 'book-open-variant', 'books.vertical' => 'bookshelf',
        'leaf' => 'leaf', 'house' => 'home', 'heart' => 'heart', 'star' => 'star', 'cup.and.saucer' => 'coffee',
        'mug' => 'coffee', 'wineglass' => 'glass-wine', 'fork.knife' => 'silverware-fork-knife',
        'figure.run' => 'run', 'figure.walk' => 'walk', 'bicycle' => 'bike', 'figure.pool.swim' => 'swim',
        'dumbbell' => 'dumbbell', 'drop' => 'water', 'flame' => 'fire', 'bolt' => 'flash', 'car' => 'car',
        'cart' => 'cart', 'creditcard' => 'credit-card', 'dollarsign.circle' => 'cash', 'eurosign.circle' => 'currency-eur',
        'phone' => 'phone', 'envelope' => 'email', 'airplane' => 'airplane', 'gamecontroller' => 'gamepad-variant',
        'music.note' => 'music', 'tv' => 'television', 'scissors' => 'content-cut', 'paintbrush' => 'brush',
        'hammer' => 'hammer', 'wrench' => 'wrench', 'trash' => 'trash-can', 'washer' => 'washing-machine',
        'dishwasher' => 'dishwasher', 'shower' => 'shower', 'bathtub' => 'bathtub', 'toilet' => 'toilet',
        'pawprint' => 'paw', 'dog' => 'dog', 'cat' => 'cat', 'tree' => 'tree', 'sun.max' => 'weather-sunny',
        'moon' => 'weather-night', 'cloud.rain' => 'weather-rainy', 'thermometer' => 'thermometer',
        'battery.100' => 'battery', 'brain.head.profile' => 'head-cog', 'brain' => 'brain', 'face.smiling' => 'emoticon-happy-outline',
        'calendar' => 'calendar', 'clock' => 'clock-outline', 'alarm' => 'alarm', 'checkmark.circle' => 'check-circle',
        'person' => 'account', 'person.2' => 'account-multiple', 'gift' => 'gift', 'camera' => 'camera',
        'graduationcap' => 'school', 'briefcase' => 'briefcase', 'lightbulb' => 'lightbulb', 'key' => 'key',
        'tshirt' => 'tshirt-crew', 'stethoscope' => 'stethoscope', 'lungs' => 'lungs', 'eye' => 'eye',
        'mouth' => 'tooth', 'hand.raised' => 'hand-back-left', 'smoke' => 'smoking', 'fuelpump' => 'gas-station',
    ];

    private int $userId;
    /** @var array<string,int> nom en minuscules -> id */
    private array $categoryIds = [];
    /** Noms des catégories masquées, à ne pas citer dans le compte rendu tant que c'est verrouillé. */
    private array $hiddenNames = [];

    public array $report = [
        'categories_created' => [], 'categories_existing' => [], 'events_imported' => 0,
        'events_skipped' => 0, 'errors' => [],
    ];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->hiddenNames = Privacy::isUnlocked() ? [] : Privacy::hiddenCategoryNames($userId);
        foreach (Db::all('SELECT id, name FROM categories WHERE user_id = ?', [$userId]) as $c) {
            $this->categoryIds[mb_strtolower($c['name'])] = (int) $c['id'];
        }
    }

    /**
     * Importe une liste de fichiers CSV (chemin => nom d'origine), dans un ordre logique :
     * groupes, puis catégories, puis événements. Le type de chaque fichier est reconnu à son en-tête.
     */
    public function importFiles(array $files): void
    {
        $parsed = [];
        foreach ($files as $path => $originalName) {
            $rows = self::readCsv($path);
            if ($rows === null || $rows === []) {
                $this->report['errors'][] = "« $originalName » : fichier vide ou illisible.";
                continue;
            }
            $kind = self::detect(array_keys($rows[0]));
            if ($kind === null) {
                $this->report['errors'][] = "« $originalName » : format non reconnu (colonnes : " . implode(', ', array_keys($rows[0])) . ').';
                continue;
            }
            $parsed[$kind][] = $rows;
        }

        Db::pdo()->beginTransaction();
        try {
            $groupOrder = [];
            foreach ($parsed['chronicling_groups'] ?? [] as $rows) {
                foreach ($rows as $r) {
                    $groupOrder[] = trim($r['Name'] ?? '');
                }
            }
            foreach ($parsed['chronicling_categories'] ?? [] as $rows) {
                $this->importChroniclingCategories($rows, $groupOrder);
            }
            foreach ($parsed['chronicling_events'] ?? [] as $rows) {
                $this->importChroniclingEvents($rows);
            }
            foreach ($parsed['batons'] ?? [] as $rows) {
                $this->importBatonsExport($rows);
            }
            Db::pdo()->commit();
        } catch (\Throwable $e) {
            Db::pdo()->rollBack();
            $this->report['errors'][] = 'Import annulé : ' . $e->getMessage();
        }
    }

    // --- Chronicling ------------------------------------------------------------------

    private function importChroniclingCategories(array $rows, array $groupOrder): void
    {
        foreach ($rows as $r) {
            $name = trim($r['Name'] ?? '');
            if ($name === '') {
                continue;
            }
            $group = trim($r['Group'] ?? '');
            if ($group === 'Ungrouped') {
                $group = '';
            }
            $icon = ($r['UseEmoji'] ?? '') === 'true' ? trim($r['Icon'] ?? '') : self::sfSymbolToMdi(trim($r['Icon'] ?? ''));
            $id = $this->category($name, [
                'icon'  => $icon,
                'color' => self::color($r['Color'] ?? ''),
                'group' => $group,
            ]);
            if (($r['IsArchived'] ?? '') === 'true' && in_array($name, $this->report['categories_created'], true)) {
                Categories::setArchived($this->userId, $id, true);
            }
        }
        // Ordre des groupes tel que dans Chronicling.
        foreach ($groupOrder as $pos => $g) {
            Db::run('UPDATE groups SET position = ? WHERE user_id = ? AND name = ?', [$pos + 1, $this->userId, $g]);
        }
    }

    private function importChroniclingEvents(array $rows): void
    {
        foreach ($rows as $i => $r) {
            $at = self::utc($r['DateTime'] ?? '');
            $name = trim($r['Category'] ?? '');
            if ($at === null || $name === '') {
                $this->report['errors'][] = 'Ligne ' . ($i + 2) . ' ignorée (date ou catégorie manquante).';
                continue;
            }
            $count = (float) str_replace(',', '.', (string) ($r['Count'] ?? '1'));
            $this->event($this->category($name), $at, $count == 1.0 ? null : $count, trim($r['Notes'] ?? ''));
        }
    }

    // --- Export Bâtons -----------------------------------------------------------------

    private function importBatonsExport(array $rows): void
    {
        foreach ($rows as $i => $r) {
            $at = self::utc((string) ($r['date_utc'] ?? ''));
            $name = trim($r['categorie'] ?? '');
            if ($at === null || $name === '') {
                $this->report['errors'][] = 'Ligne ' . ($i + 2) . ' ignorée (date ou catégorie manquante).';
                continue;
            }
            $kind = in_array($r['type'] ?? '', ['film', 'serie', 'livre', 'jeu'], true) ? $r['type'] : 'simple';
            $id = $this->category($name, ['group' => trim($r['groupe'] ?? ''), 'unit' => trim($r['unite'] ?? ''), 'kind' => $kind]);
            $work = trim((string) ($r['oeuvre'] ?? ''));
            if ($work !== '' && $kind !== 'simple') {
                $int = static fn($v) => ctype_digit((string) $v) && (int) $v > 0 ? (int) $v : null;
                $src = (string) ($r['source'] ?? '');
                $workId = ($src !== '' && $src !== 'manual' && ($r['source_id'] ?? '') !== '')
                    ? Works::fromCatalog($this->userId, $kind, ['source' => $src, 'source_id' => (string) $r['source_id'],
                        'title' => $work, 'subtitle' => ($r['oeuvre_detail'] ?? '') ?: null, 'year' => $int($r['annee'] ?? null), 'poster' => null])
                    : Works::manual($this->userId, $kind, $work, ($r['oeuvre_detail'] ?? '') ?: null, $int($r['annee'] ?? null));
                if (Db::value('SELECT 1 FROM events WHERE category_id = ? AND occurred_at = ?', [$id, $at])) {
                    $this->report['events_skipped']++;
                } else {
                    $rt = $int($r['note_sur_10'] ?? null);
                    Events::addWork($id, $at, $workId, $int($r['saison'] ?? null), $int($r['episode'] ?? null),
                        $int($r['tome'] ?? null), $rt !== null && $rt <= 10 ? $rt : null, trim($r['note'] ?? ''),
                        ['debut' => 'start', 'fin' => 'finish'][$r['etape'] ?? ''] ?? null);
                    $this->report['events_imported']++;
                }
                continue;
            }
            $value = trim((string) ($r['valeur'] ?? ''));
            $this->event($id, $at, $value === '' ? null : (float) str_replace(',', '.', $value), trim($r['note'] ?? ''),
                ['debut' => 'start', 'fin' => 'finish'][$r['etape'] ?? ''] ?? null);
        }
    }

    // --- Communs -------------------------------------------------------------------------

    /** Id de la catégorie de ce nom, créée avec $attrs si elle n'existe pas. */
    private function category(string $name, array $attrs = []): int
    {
        $key = mb_strtolower($name);
        if (isset($this->categoryIds[$key])) {
            if (!in_array($name, $this->report['categories_created'], true)
                && !in_array($name, $this->report['categories_existing'], true)
                && !in_array($key, $this->hiddenNames, true)) {
                $this->report['categories_existing'][] = $name;
            }
            return $this->categoryIds[$key];
        }
        $icon = $attrs['icon'] ?? '';
        if ($icon === '' || !Icons::isValid($icon)) {
            $icon = '|';
        }
        $id = Categories::create($this->userId, [
            'name'  => mb_substr($name, 0, 80),
            'icon'  => $icon,
            'color' => $attrs['color'] ?? '#c2410c',
            'unit'  => ($attrs['unit'] ?? '') === '' ? null : $attrs['unit'],
            'group' => $attrs['group'] ?? '',
            'kind'  => $attrs['kind'] ?? 'simple',
        ]);
        $this->report['categories_created'][] = $name;
        return $this->categoryIds[$key] = $id;
    }

    private function event(int $categoryId, string $at, ?float $value, string $note, ?string $milestone = null): void
    {
        $exists = Db::value('SELECT 1 FROM events WHERE category_id = ? AND occurred_at = ?', [$categoryId, $at]);
        if ($exists) {
            $this->report['events_skipped']++;
            return;
        }
        Events::add($categoryId, $at, $value, $note, $milestone);
        if ($milestone !== null) {
            Db::run('UPDATE categories SET periods = 1 WHERE id = ? AND kind = ?', [$categoryId, 'simple']);
        }
        $this->report['events_imported']++;
    }

    /** Lit un CSV (séparateur , ou ; détecté, BOM toléré) en tableaux associatifs. */
    private static function readCsv(string $path): ?array
    {
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            return null;
        }
        $first = (string) fgets($fh);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map('trim', str_getcsv(rtrim($first, "\r\n"), $sep, '"', ''));
        $rows = [];
        while (($line = fgetcsv($fh, null, $sep, '"', '')) !== false) {
            if ($line === [null] || $line === ['']) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
        }
        fclose($fh);
        return $rows;
    }

    private static function detect(array $cols): ?string
    {
        return match (true) {
            in_array('DateTime', $cols, true) && in_array('Category', $cols, true) => 'chronicling_events',
            in_array('UseEmoji', $cols, true) && in_array('Name', $cols, true)     => 'chronicling_categories',
            $cols === ['Name', 'Categories']                                       => 'chronicling_groups',
            in_array('date_utc', $cols, true) && in_array('categorie', $cols, true)=> 'batons',
            default => null,
        };
    }

    /** Date ISO (« 2026-11-16T20:30:00.000Z ») ou « Y-m-d H:i:s » UTC -> format de stockage. */
    private static function utc(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        try {
            $dt = new \DateTimeImmutable($s, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function color(string $c): string
    {
        $c = strtolower(trim($c));
        if (preg_match('/^#[0-9a-f]{6}$/', $c)) {
            return $c;
        }
        if (preg_match('/name:\s*"?([a-z]+)"?/', $c, $m)) { // NamedColor(name: "magenta", bundle: nil)
            $c = $m[1];
        }
        return self::COLORS[$c] ?? '#c2410c';
    }

    /** « bed.double.fill » -> « mdi:bed-double » (table de correspondance, puis devinette par le nom). */
    public static function sfSymbolToMdi(string $symbol): string
    {
        if ($symbol === '') {
            return '|';
        }
        $base = preg_replace('/\.(fill|circle\.fill|square\.fill|circle|square)$/', '', $symbol);
        foreach ([$symbol, $base] as $s) {
            if (isset(self::SF_SYMBOLS[$s])) {
                return 'mdi:' . self::SF_SYMBOLS[$s];
            }
        }
        $guess = str_replace('.', '-', $base);
        if (Icons::available() && Icons::path($guess) !== null) {
            return 'mdi:' . $guess;
        }
        return '|';
    }
}
