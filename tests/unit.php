<?php
declare(strict_types=1);

/**
 * Tests unitaires de Bâtons, sans dépendance : php tests/unit.php
 * Chaque fonction test_* reçoit une base SQLite neuve (dossier temporaire). Sortie non nulle en cas d'échec.
 */

$dataDir = sys_get_temp_dir() . '/batons-unit-' . getmypid();
putenv('BATONS_DATA_DIR=' . $dataDir);
putenv('BATONS_TIMEZONE=Europe/Paris');
putenv('BATONS_BACKUP_DAYS=7');
@mkdir($dataDir, 0700, true);
require dirname(__DIR__) . '/app/src/bootstrap.php';

use Batons\Categories;
use Batons\Chart;
use Batons\Db;
use Batons\Events;
use Batons\Importer;
use Batons\Theme;
use Batons\Time;

$failures = 0;
$checks = 0;

function check(bool $ok, string $what): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures++;
        $t = debug_backtrace()[1]['function'] ?? '?';
        fwrite(STDERR, "  ÉCHEC [$t] $what\n");
    }
}

function same(mixed $expected, mixed $actual, string $what): void
{
    check($expected === $actual, $what . ' : attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true));
}

function user(): int
{
    static $n = 0;
    $n++;
    Db::run('INSERT INTO users (username, display_name, created_at) VALUES (?, ?, ?)', ["u$n", "U$n", Time::nowUtc()]);
    return (int) Db::pdo()->lastInsertId();
}

function cat(int $uid, string $name, array $extra = []): int
{
    return Categories::create($uid, $extra + ['name' => $name, 'icon' => '☕', 'color' => '#c2410c', 'unit' => '',
        'group' => '', 'hidden' => false, 'kind' => 'simple', 'periods' => false, 'count_future' => false]);
}

function utc(string $relative): string
{
    return (new DateTimeImmutable($relative, new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

// --- Périodes et bâtons à venir -----------------------------------------------------------

function test_period_covers_whole_day(): void
{
    [$from, $to] = Time::periodUtc('today');
    $start = new DateTimeImmutable($from, new DateTimeZone('UTC'));
    $end = new DateTimeImmutable($to, new DateTimeZone('UTC'));
    $hours = (float) (($end->getTimestamp() - $start->getTimestamp()) / 3600);
    check(in_array($hours, [23.0, 24.0, 25.0], true), "« aujourd'hui » couvre une journée entière ($hours h)");
    check($to > Time::nowUtc(), "la fin d'« aujourd'hui » est après maintenant");
}

function test_future_events_counted_or_not(): void
{
    $uid = user();
    $c = cat($uid, 'Fioul', ['unit' => 'l']);
    Events::add($c, utc('-40 days'), 500.0);
    Events::add($c, utc('+1 minute'), 1000.0);    // plus tard (aujourd'hui, sauf juste avant minuit)
    $with = Events::stats($c, true);
    $without = Events::stats($c, false);
    same(1500.0, (float) $with['total_value'], 'total en comptant les bâtons à venir');
    same(500.0, (float) $without['total_value'], 'total sans les bâtons à venir');
    check($without['next_at'] !== null, '« Prochain bâton » annoncé quand ils ne sont pas comptés');
    same(null, $with['next_at'], 'pas de « Prochain bâton » quand ils sont comptés');
}

function test_new_event_clears_stop(): void
{
    $uid = user();
    $c = cat($uid, 'Livres', ['kind' => 'livre']);
    Db::run("INSERT INTO works (user_id, kind, source, source_id, title, stopped_at, created_at) VALUES (?, 'livre', 'manual', 'dune', 'Dune', ?, ?)",
        [$uid, Time::nowUtc(), Time::nowUtc()]);
    $w = (int) Db::pdo()->lastInsertId();
    Events::addWork($c, Time::nowUtc(), $w, null, null, 1, null, null, 'start');
    same(null, Db::value('SELECT stopped_at FROM works WHERE id = ?', [$w]), "un nouveau bâton annule « J'arrête là »");
}

// --- Calendrier ---------------------------------------------------------------------------------

function test_calendar_scale(): void
{
    $unit = Chart::calendarScale([1, 1, 1, 1]);
    same(['4' => '1 bâton'], array_map('strval', $unit['legend']) + [], 'catégorie à 1 bâton par jour : couleur pleine');
    same(4, Chart::calendarLevel(1, $unit['ref']), 'un bâton = niveau 4 quand le maximum habituel est 1');
    $spike = Chart::calendarScale([1, 1, 1, 1, 1, 1, 1, 1, 1, 20]);
    same(1, $spike['ref'], 'une journée exceptionnelle ne fixe pas la référence');
    same(0, Chart::calendarLevel(0, 5), 'jour vide = niveau 0');
}

// --- Couleurs : contrastes garantis --------------------------------------------------------------

function test_theme_contrast(): void
{
    foreach (array_keys(Theme::palette()) + ['x' => '#ffffff', 'y' => '#000000', 'z' => '#fde047'] as $hex) {
        preg_match_all('/--c-([a-z0-9-]+): (#[0-9a-f]{6})/', Theme::tileVars($hex) . ' ' . Theme::baseVars($hex), $m);
        $v = array_combine($m[1], $m[2]);
        $on = Theme::rgb($v['on']);
        foreach (['solid', 'deep', 'glow', 'hue2'] as $k) {
            check(Theme::contrast(Theme::rgb($v[$k]), $on) >= 4.5, "$hex : texte sur --c-$k ≥ 4,5:1");
        }
        check(Theme::contrast(Theme::rgb($v['pastel']), Theme::rgb($v['pastel-ink'])) >= 4.5, "$hex : pastel ≥ 4,5:1");
        check(Theme::contrast(Theme::rgb('#f7f4ee'), Theme::rgb($v['ink-l'])) >= 4.5, "$hex : texte coloré sur fond clair ≥ 4,5:1");
    }
}

// --- Retour « d'où l'on vient » : pas de redirection ouverte -------------------------------------

function test_safe_from(): void
{
    same('./?r=timeline', safe_from('./?r=timeline'), 'URL relative acceptée');
    foreach (['https://evil.example', '//evil.example', '/?r=x', 'javascript:alert(1)', './\\evil', ''] as $bad) {
        same(null, safe_from($bad), "refusé : $bad");
    }
}

// --- Réorganisation ----------------------------------------------------------------------------------

function test_reorder_moves_between_groups(): void
{
    $uid = user();
    $a = cat($uid, 'A', ['group' => 'G1']);
    $b = cat($uid, 'B', ['group' => 'G1']);
    $c = cat($uid, 'C', ['group' => 'G2']);
    $groups = array_column(Categories::groups($uid), 'id', 'name');
    $ok = Categories::reorder($uid, [$groups['G2'], $groups['G1']], [$groups['G2'] => [$b, $c], $groups['G1'] => [$a], 0 => []]);
    check($ok, 'ordre accepté');
    same((int) $groups['G2'], (int) Db::value('SELECT group_id FROM categories WHERE id = ?', [$b]), 'B a changé de groupe');
    same(['G2', 'G1'], array_column(Categories::groups($uid), 'name'), 'groupes réordonnés');
    check(!Categories::reorder($uid, [$groups['G1']], [0 => [$a]]), 'demande incomplète refusée');
}

// --- Import : la vérification n'écrit rien ---------------------------------------------------------

function test_import_dry_run_writes_nothing(): void
{
    $uid = user();
    $f = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($f, "DateTime,Category,Value,Note\n2026-11-10T08:00:00.000Z,Thé,,\n2026-11-11T08:00:00.000Z,Thé,,\n");
    $dry = new Importer($uid);
    $dry->importFiles([$f => 'events.csv'], true);
    same(2, $dry->report['events_imported'], 'la vérification annonce 2 bâtons');
    same(0, (int) Db::value('SELECT COUNT(*) FROM categories WHERE user_id = ?', [$uid]), 'rien écrit pendant la vérification');
    $real = new Importer($uid);
    $real->importFiles([$f => 'events.csv']);
    same(2, (int) Db::value('SELECT COUNT(*) FROM events e JOIN categories c ON c.id = e.category_id WHERE c.user_id = ?', [$uid]), 'import réel');
    $again = new Importer($uid);
    $again->importFiles([$f => 'events.csv']);
    same(2, $again->report['events_skipped'], 'réimport : doublons ignorés');
    unlink($f);
}

// --- Sauvegarde quotidienne -------------------------------------------------------------------------

function test_backup(): void
{
    Batons\Backup::maybeRun();
    $list = Batons\Backup::list();
    check(count($list) === 1, 'une sauvegarde créée');
    check(reset($list) > 0, 'sauvegarde non vide');
    Batons\Backup::maybeRun();
    same(1, count(Batons\Backup::list()), 'une seule par jour');
}

// --- Exécution ---------------------------------------------------------------------------------------

$tests = array_values(array_filter(get_defined_functions()['user'], static fn($f) => str_starts_with($f, 'test_')));
foreach ($tests as $t) {
    $before = $failures;
    try {
        $t();
    } catch (Throwable $e) {
        $failures++;
        fwrite(STDERR, "  ERREUR [$t] " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n");
    }
    echo ($failures === $before ? '✓ ' : '✗ ') . substr($t, 5) . "\n";
}
exec('rm -rf ' . escapeshellarg($dataDir));
echo "\n$checks vérifications, $failures échec(s).\n";
exit($failures ? 1 : 0);
