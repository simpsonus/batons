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
use Batons\Periods;
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

// --- Chronologie : recherche dans les notes, période ------------------------------------------------

function test_timeline_search_and_dates(): void
{
    $uid = user();
    $car = cat($uid, 'Voiture');
    $home = cat($uid, 'Maison');
    $secret = cat($uid, 'Secret', ['hidden' => true]);
    Events::add($car, utc('-2 days'), null, 'Vidange + filtres');
    Events::add($home, utc('-40 days'), null, 'École : réunion');
    Events::add($home, utc('-1 day'), null, '50% de remise_ok');
    Events::add($secret, utc('-1 day'), null, 'vidange secrète');
    $notes = static fn(array $rows): array => array_column($rows, 'note');

    same(['Vidange + filtres'], $notes(Events::timeline($uid, 10, 0, null, ['q' => 'VIDANGE'])), 'majuscules ignorées, masquée exclue');
    same(['École : réunion'], $notes(Events::timeline($uid, 10, 0, null, ['q' => 'ecole'])), 'accents ignorés');
    same(['50% de remise_ok'], $notes(Events::timeline($uid, 10, 0, null, ['q' => '0%'])), '% cherché tel quel');
    same([], $notes(Events::timeline($uid, 10, 0, null, ['q' => 'e_r'])), '_ cherché tel quel (pas un joker)');
    same(1, Events::timelineCount($uid, [$car], ['q' => 'filtre']), 'recherche combinée à une catégorie');
    same(2, Events::timelineCount($uid, null, ['from' => Time::lastDaysUtc(7)]), '7 derniers jours');
    same(3, Events::timelineCount($uid, null, ['from' => Time::lastDaysUtc(365)]), '12 derniers mois');

    $d = static fn(string $rel): string => (new DateTimeImmutable($rel, Time::tz()))->format('Y-m-d');
    [$from, $to] = Time::daysUtc($d('-41 days'), $d('-39 days'));
    same(['École : réunion'], $notes(Events::timeline($uid, 10, 0, null, ['from' => $from, 'to' => $to])), 'intervalle de dates');
    same(Time::daysUtc($d('-41 days'), $d('-39 days')), Time::daysUtc($d('-39 days'), $d('-41 days')), 'dates inversées remises dans l\'ordre');
    check(Time::daysUtc('2026-02-30', '2026-03-01') === null, 'date impossible refusée');
    [$a, $b] = Time::daysUtc('2026-03-29', '2026-03-29');
    same(23, (strtotime($b . ' UTC') - strtotime($a . ' UTC')) / 3600, 'jour du passage à l\'heure d\'été : 23 h');
}

function test_highlight(): void
{
    same('<mark>École</mark> &amp; <mark>ecole</mark>', highlight('École & ecole', 'ÉCOLE'), 'accents et majuscules, sortie échappée');
    same('a &lt;b&gt;', highlight('a <b>', 'zz'), 'rien trouvé : texte échappé');
    same('<mark>œu</mark>vre', highlight('œuvre', 'oeu'), 'ligature');
    same('x', highlight('x', '  '), 'recherche vide');
}

// --- Livres en tomes : tome suivant ------------------------------------------------------------------

function test_book_next_volume(): void
{
    $uid = user();
    $c = cat($uid, 'Livres', ['kind' => 'livre']);
    $w = Batons\Works::manual($uid, 'livre', '1Q84');
    $work = Batons\Works::find($uid, $w);
    Events::addWork($c, utc('-12 days'), $w, null, null, 1, null, null, 'start');
    same(['volume' => 2], Batons\Works::next($uid, $work, Batons\Works::lastEvent($w, $c)), 'tome 1 commencé : suivant = tome 2');
    Events::addWork($c, utc('-1 day'), $w, null, null, 1, null, null, 'finish');
    same('done', Batons\Works::status($w, $c)['state'], 'tome 1 fini');
    same(['volume' => 2], Batons\Works::next($uid, $work, Batons\Works::lastEvent($w, $c)), 'tome 1 fini : on propose le tome 2');
    // Bâtons ordinaires d'un tome antérieur saisis après coup : le tome suivant reste celui qui suit le tome commencé.
    Events::addWork($c, utc('-30 days'), $w, null, null, 3, null, null);
    Events::addWork($c, utc('-2 hours'), $w, null, null, 4, null, null, 'start');
    Events::addWork($c, utc('-1 hour'), $w, null, null, 2, null, null);
    same(['volume' => 5], Batons\Works::next($uid, $work, Batons\Works::lastEvent($w, $c)), 'suivant = tome commencé + 1');
}

// --- Périodes : durée courte des tuiles ; palette ---------------------------------------------------------

function test_period_short_duration(): void
{
    $now = Time::nowUtc();
    same('1 min', Periods::shortDuration($now, $now), 'moins d\'une minute : 1 min');
    same('45 min', Periods::shortDuration(utc('-45 minutes'), $now), 'minutes');
    $start = (new DateTimeImmutable('today 08:00', Time::tz()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $end = (new DateTimeImmutable('today 11:30', Time::tz()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    same('3 h', Periods::shortDuration($start, $end), 'heures dans la même journée');
    same('4 j', Periods::shortDuration(utc('-4 days'), $now), 'jours calendaires');
}

function test_palette_hues_and_tones(): void
{
    $hues = Theme::hues();
    same(16, count($hues), '16 teintes');
    check(array_reduce($hues, static fn($ok, $t) => $ok && count($t) === 4, true), '4 nuances par teinte');
    same(64, count(Theme::palette()), '64 couleurs distinctes');
    same(['Vert moyen', 'Vert'], Theme::palette()['#16a34a'], 'nom « Vert moyen »');
}

// --- CBOR : exemples de la RFC 8949, annexe A ----------------------------------------------------------

function test_cbor_rfc8949_examples(): void
{
    $ok = [
        '00' => 0, '01' => 1, '0a' => 10, '17' => 23, '1818' => 24, '1819' => 25, '1864' => 100, '1903e8' => 1000,
        '1a000f4240' => 1000000, '1b000000e8d4a51000' => 1000000000000, '1b7fffffffffffffff' => PHP_INT_MAX,
        '20' => -1, '29' => -10, '3863' => -100, '3903e7' => -1000,
        '40' => '', '4401020304' => "\x01\x02\x03\x04", '60' => '', '6161' => 'a', '6449455446' => 'IETF',
        '62225c' => "\"\\", '62c3bc' => 'ü', '63e6b0b4' => '水', '64f0908591' => '𐅑',
        '80' => [], '83010203' => [1, 2, 3], '8301820203820405' => [1, [2, 3], [4, 5]],
        '98190102030405060708090a0b0c0d0e0f101112131415161718181819' => range(1, 25),
        'a0' => [], 'a201020304' => [1 => 2, 3 => 4], 'a26161016162820203' => ['a' => 1, 'b' => [2, 3]],
        '826161a161626163' => ['a', ['b' => 'c']],
        'a56161614161626142616361436164614461656145' => ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'],
        'f4' => false, 'f5' => true, 'f6' => null, 'f7' => null,
    ];
    foreach ($ok as $hex => $expected) {   // (string) : PHP change les clés '17', '20'… en entiers
        $hex = (string) $hex;
        same($expected, Batons\Cbor::decode(hex2bin($hex)), "CBOR $hex");
    }
    Batons\Cbor::decode(hex2bin('8301020304'), $end);
    same(4, $end, 'position après la valeur (octets restants ignorés)');

    // Hors du sous-ensemble WebAuthn ou invalides : refusés proprement, jamais une valeur fausse.
    $refused = [
        '1bffffffffffffffff' => 'entier ≥ 2^63', '3bffffffffffffffff' => 'négatif < -2^63',
        'f93c00' => 'flottant', 'fb3ff199999999999a' => 'flottant double', 'c11a514b67b0' => 'étiquette (date)',
        '5f42010243030405ff' => 'longueur indéfinie', '9fff' => 'tableau indéfini', 'f820' => 'valeur simple 32',
        '' => 'vide', '19' => 'tronqué', '6461' => 'texte tronqué', '83' => 'tableau tronqué', 'a1' => 'table tronquée',
        'a1800102' => 'clé de table non scalaire',
        str_repeat('81', 20) . '00' => 'imbrication trop profonde',
    ];
    foreach ($refused as $hex => $what) {
        $hex = (string) $hex;
        try {
            Batons\Cbor::decode((string) hex2bin($hex));
            check(false, "CBOR refusé : $what");
        } catch (RuntimeException) {
            check(true, "CBOR refusé : $what");
        }
    }
}

// --- WebAuthn : jeux d'essai officiels du W3C (tests/webauthn-vectors.php) -----------------------------

/** Réponse du navigateur (champs en base64url) pour une création de passkey. */
function wa_create(array $v, ?string $challenge = null): array
{
    $_SESSION['webauthn'] = ['type' => 'create', 'challenge' => Batons\WebAuthn::b64u(hex2bin($challenge ?? $v['reg_challenge'])), 'exp' => time() + 60];
    return ['clientDataJSON' => Batons\WebAuthn::b64u(hex2bin($v['reg_client_data'])),
            'attestationObject' => Batons\WebAuthn::b64u(hex2bin($v['attestation_object']))];
}

/** Réponse du navigateur pour une signature (déverrouillage). */
function wa_get(array $v, array $change = []): array
{
    $_SESSION['webauthn'] = ['type' => 'get', 'challenge' => Batons\WebAuthn::b64u(hex2bin($change['challenge'] ?? $v['auth_challenge'])), 'exp' => time() + 60];
    return ['id' => Batons\WebAuthn::b64u(hex2bin($v['credential_id'])),
            'clientDataJSON' => Batons\WebAuthn::b64u(hex2bin($v['auth_client_data'])),
            'authenticatorData' => Batons\WebAuthn::b64u(hex2bin($change['authenticator_data'] ?? $v['authenticator_data'])),
            'signature' => Batons\WebAuthn::b64u(hex2bin($change['signature'] ?? $v['signature']))];
}

/** Exécute $fn et renvoie le message d'erreur (ou '' si tout s'est bien passé). */
function wa_error(callable $fn): string
{
    try {
        $fn();
        return '';
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}

function test_webauthn_official_vectors_es256(): void
{
    $vectors = require __DIR__ . '/webauthn-vectors.php';
    foreach (['packed-es256', 'tpm-es256'] as $name) {
        $v = $vectors[$name];
        $uid = user();
        same('', wa_error(fn() => Batons\WebAuthn::register($uid, wa_create($v), 'Test')), "$name : création acceptée");
        $key = Db::one('SELECT * FROM passkeys WHERE user_id = ?', [$uid]);
        same(Batons\WebAuthn::b64u(hex2bin($v['credential_id'])), $key['credential_id'] ?? null, "$name : identifiant enregistré");
        same('example.org', $key['rp_id'] ?? null, "$name : domaine (rpId) enregistré avec la clé");
        same('', wa_error(fn() => Batons\WebAuthn::verify($uid, wa_get($v))), "$name : signature officielle acceptée");

        // Falsifications : chacune doit être refusée.
        $sig = hex2bin($v['signature']);
        $sig[10] = chr(ord($sig[10]) ^ 1);
        check(wa_error(fn() => Batons\WebAuthn::verify($uid, wa_get($v, ['signature' => bin2hex($sig)]))) !== '', "$name : signature altérée refusée");
        check(wa_error(fn() => Batons\WebAuthn::verify($uid, wa_get($v, ['challenge' => str_repeat('00', 32)]))) !== '', "$name : autre défi refusé");
        $auth = hex2bin($v['authenticator_data']);
        $auth[0] = chr(ord($auth[0]) ^ 1);
        check(wa_error(fn() => Batons\WebAuthn::verify($uid, wa_get($v, ['authenticator_data' => bin2hex($auth)]))) !== '', "$name : autre domaine refusé");
        $in = wa_get($v);
        same('', wa_error(fn() => Batons\WebAuthn::verify($uid, $in)), "$name : nouvelle signature acceptée");
        check(wa_error(fn() => Batons\WebAuthn::verify($uid, $in)) !== '', "$name : défi à usage unique (rejeu refusé)");
        $other = user();
        check(wa_error(fn() => Batons\WebAuthn::verify($other, wa_get($v))) !== '', "$name : clé d'un autre utilisateur refusée");
        check(wa_error(fn() => Batons\WebAuthn::register($uid, wa_create($v), 'Test')) !== '', "$name : même appareil pas enregistré deux fois");
    }
}

function test_webauthn_official_vectors_refused(): void
{
    $vectors = require __DIR__ . '/webauthn-vectors.php';
    // Sans vérification de l'utilisateur (bit UV absent) : refusé, Bâtons exige biométrie ou code de l'appareil.
    foreach (['none-es256', 'none-es256-long-credential-id'] as $name) {
        $uid = user();
        check(str_contains(wa_error(fn() => Batons\WebAuthn::register($uid, wa_create($vectors[$name]), 'T')), 'vérification'), "$name : création sans UV refusée");
    }
    $uid = user();
    check(str_contains(wa_error(fn() => Batons\WebAuthn::register($uid, wa_create($vectors['none-es256-crossOrigin']), 'T')), 'autre site'), 'crossOrigin : refusé');
    check(str_contains(wa_error(fn() => Batons\WebAuthn::register($uid, wa_create($vectors['packed-es512']), 'T')), 'non pris en charge'), 'ES512 : type de clé refusé');
    check(wa_error(fn() => Batons\WebAuthn::register($uid, wa_create($vectors['packed-es256'], str_repeat('11', 32)), 'T')) !== '', 'création avec un autre défi refusée');

    // Création acceptée, signature refusée faute d'UV ; la clé et la signature restent mathématiquement valides.
    foreach (['packed-self-es256' => OPENSSL_ALGO_SHA256, 'packed-rs256' => OPENSSL_ALGO_SHA256] as $name => $algo) {
        $v = $vectors[$name];
        $uid = user();
        same('', wa_error(fn() => Batons\WebAuthn::register($uid, wa_create($v), 'T')), "$name : création acceptée (clé COSE convertie)");
        $pem = (string) Db::value('SELECT public_key FROM passkeys WHERE user_id = ?', [$uid]);
        $signed = hex2bin($v['authenticator_data']) . hash('sha256', hex2bin($v['auth_client_data']), true);
        same(1, openssl_verify($signed, hex2bin($v['signature']), $pem, $algo), "$name : clé convertie en PEM vérifie la signature officielle");
        check(str_contains(wa_error(fn() => Batons\WebAuthn::verify($uid, wa_get($v))), 'vérification'), "$name : signature sans UV refusée");
    }
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
