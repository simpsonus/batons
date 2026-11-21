<?php
declare(strict_types=1);

/*
 * Routes — Œuvres : films, séries, livres, jeux (recherche catalogue, fiche, épisode / tome suivant, affiches).
 * Inclus par public/index.php, dans sa portée : $route, $uid et les aides ($category, $event, $parseValue…)
 * y sont disponibles. Chaque route se termine par un rendu, une redirection ou exit.
 */

use Batons\Auth;
use Batons\Categories;
use Batons\Events;
use Batons\Http;
use Batons\Privacy;
use Batons\Time;
use Batons\WebAuthn;

switch ($route) {
    // --- Œuvres (catégories films / séries / livres) ------------------------------------
    case 'work_add':
        $c = $category(Http::intInput('id'));
        if ($c['kind'] === 'simple') {
            Http::redirect('category', ['id' => $c['id']]);
        }
        $q = Http::input('q');
        $results = $q !== '' ? Batons\Catalog::search($c['kind'], $q, $uid) : [];
        if (($_GET['partial'] ?? '') === '1') {
            $partial = true;
            require dirname(__DIR__) . '/views/_work_results.php';
            exit;
        }
        $continue = [];
        foreach (Batons\Works::inCategory($uid, (int) $c['id']) as $w) {
            if ($w['stopped_at']) {
                continue; // « J'arrête là »
            }
            $next = Batons\Works::next($uid, $w, Batons\Works::lastEvent((int) $w['id'], (int) $c['id']));
            if (in_array($w['kind'], Batons\Works::TRACKED, true)) {
                $st = Batons\Works::status((int) $w['id'], (int) $c['id']);
                $explicitStart = (bool) Batons\Db::value("SELECT 1 FROM events WHERE work_id = ? AND category_id = ? AND milestone = 'start'",
                    [$w['id'], $c['id']]);
                if ($st['state'] === 'done' && !($w['kind'] === 'livre' && $next !== null)) {
                    $next = null; // terminé (sauf livre avec un tome suivant)
                } elseif ($st['state'] === 'playing' && !$explicitStart
                    && strtotime($w['last_at'] . ' UTC') < time() - 90 * 86400) {
                    $next = null; // jamais marqué « commencé » et rien depuis 3 mois : oublié
                } elseif ($st['state'] === 'playing' && $next === null) {
                    $next = ['reading' => true]; // en cours sans étape suivante (livre sans tome)
                }
            }
            if ($next !== null) {
                $continue[] = ['work' => $w, 'next' => $next];
            }
            if (count($continue) >= 30) {
                break;
            }
        }
        Http::render('work_add', ['c' => $c, 'q' => $q, 'results' => $results, 'continue' => $continue,
            'sources' => Batons\Catalog::sources($c['kind'], $uid)], Batons\Catalog::KINDS[$c['kind']] . ' : ajouter');

    case 'work_pick':
        $c = $category(Http::intInput('id'));
        $source = Http::input('source');
        $sid = Http::input('sid');
        if (!in_array($source, ['tmdb', 'tvmaze', 'gbooks', 'openlibrary', 'rawg'], true) || $sid === '') {
            Http::redirect('work_add', ['id' => $c['id']]);
        }
        $item = Batons\Catalog::details($source, $sid, $c['kind'], $uid) ?? [
            // Catalogue injoignable : on garde ce que la recherche avait renvoyé.
            'source' => $source, 'source_id' => $sid, 'title' => Http::input('title') ?: '(sans titre)',
            'subtitle' => Http::input('subtitle') ?: null, 'year' => $posInt(Http::input('year')), 'poster' => Http::input('poster') ?: null,
        ];
        $workId = Batons\Works::fromCatalog($uid, $c['kind'], $item);
        Http::redirect('work_log', ['id' => $c['id'], 'work' => $workId]);

    case 'work_manual':
        $c = $category(Http::intInput('id'));
        $title = Http::input('title');
        if ($title === '') {
            Http::redirect('work_add', ['id' => $c['id']]);
        }
        $workId = Batons\Works::manual($uid, $c['kind'], $title, Http::input('subtitle') ?: null);
        Http::redirect('work_log', ['id' => $c['id'], 'work' => $workId]);

    case 'work_log':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $last = Batons\Works::lastEvent((int) $w['id'], (int) $c['id']);
        if (Http::isPost()) {
            $at = Time::fromLocalInput(Http::input('occurred_at')) ?? Time::nowUtc();
            $evId = Events::addWork((int) $c['id'], $at, (int) $w['id'],
                $c['kind'] === 'serie' ? $posInt(Http::input('season')) : null,
                $c['kind'] === 'serie' ? $posInt(Http::input('episode')) : null,
                $c['kind'] === 'livre' ? $posInt(Http::input('volume')) : null,
                $rating(Http::input('rating')), Http::input('note'),
                in_array($c['kind'], Batons\Works::TRACKED, true) ? $milestone(Http::input('milestone')) : null);
            $ev = Events::find($uid, $evId);
            $label = trim($w['title'] . ' ' . Batons\Works::progress($ev['season'], $ev['episode'], $ev['volume'])
                . ['start' => ' : commencé', 'finish' => ' : terminé', '' => ''][$ev['milestone'] ?? '']);
            Http::flash('« ' . $label . ' » ajouté.', $evId);
            Http::redirect('work', ['id' => $c['id'], 'work' => $w['id']]);
        }
        Http::render('work_log', ['c' => $c, 'w' => $w, 'last' => $last,
            'next' => Batons\Works::next($uid, $w, $last), 'seasons' => Batons\Works::seasons($w)], $w['title']);

    case 'work':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $evs = Events::forWork((int) $w['id'], (int) $c['id']);
        $last = $evs[0] ?? null;
        Http::render('work', ['c' => $c, 'w' => $w, 'events' => $evs,
            'next' => Batons\Works::next($uid, $w, $last), 'last' => $last], $w['title']);

    case 'work_mark':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $m = $milestone(Http::input('milestone'));
        if ($m === null || !in_array($c['kind'], Batons\Works::TRACKED, true)) {
            Http::redirect('work', ['id' => $c['id'], 'work' => $w['id']]);
        }
        // Livre en plusieurs tomes : l'étape porte le numéro du tome en cours.
        $vol = $c['kind'] === 'livre' ? Batons\Works::currentVolume((int) $w['id'], (int) $c['id']) : null;
        if ($m === 'start' && $vol !== null) {
            $vol = null; // « Recommencer » un livre : on repart sans tome, la fiche permet d'en préciser un
        }
        $evId = Events::addWork((int) $c['id'], Time::nowUtc(), (int) $w['id'], null, null, $vol, null, null, $m);
        $st = Batons\Works::status((int) $w['id'], (int) $c['id']);
        if ($m === 'finish') {
            $isBook = $c['kind'] === 'livre';
            $d = (int) $st['days'];
            $inDays = $d === 0 ? 'le jour même' : 'en ' . Batons\Works::daysLabel($d);
            if ($isBook && $vol !== null) {
                // Tome fini : on propose d'enchaîner, sans l'imposer (le message se ferme avec ✕).
                Http::flash('Tome ' . $vol . ' de « ' . $w['title'] . ' » marqué comme lu, ' . $inDays . '.', $evId,
                    ['label' => 'Commencer le tome ' . ($vol + 1), 'route' => 'work_next', 'params' => ['id' => $c['id'], 'work' => $w['id']]],
                    'Annuler « lu »');
            } else {
                Http::flash('« ' . $w['title'] . ' » marqué comme ' . ($isBook ? 'lu' : 'terminé') . ', ' . $inDays . '.', $evId, null,
                    $isBook ? 'Annuler « lu »' : 'Annuler « terminé »');
            }
        } else {
            Http::flash('« ' . $w['title'] . ' » commencé aujourd\'hui.', $evId);
        }
        Http::back('work', ['id' => $c['id'], 'work' => $w['id']]);

    case 'work_stop':
    case 'work_resume':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        Batons\Db::run('UPDATE works SET stopped_at = ? WHERE id = ? AND user_id = ?',
            [$route === 'work_stop' ? Time::nowUtc() : null, $w['id'], $uid]);
        $stS = in_array($c['kind'], Batons\Works::TRACKED, true) ? Batons\Works::status((int) $w['id'], (int) $c['id']) : null;
        $afterDone = $stS && $stS['state'] === 'done';   // arrêté après un tome / un livre fini : rangé dans « Lus »
        Http::flash($route === 'work_stop'
            ? ($afterDone ? '« ' . $w['title'] . ' » : la suite ne sera plus proposée. Rangé dans « ' . ($c['kind'] === 'livre' ? 'Lus' : 'Terminés') . ' ».'
                          : '« ' . $w['title'] . ' » arrêté : il n\'est plus proposé dans « Continuer ».')
            : '« ' . $w['title'] . ' » est de nouveau proposé dans « Continuer ».');
        Http::back('work', ['id' => $c['id'], 'work' => $w['id']]);   // reste sur la fiche, sans perdre son « from »

    case 'work_next':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $next = Batons\Works::next($uid, $w, Batons\Works::lastEvent((int) $w['id'], (int) $c['id']));
        if ($next === null) {
            Http::redirect('work_log', ['id' => $c['id'], 'work' => $w['id']]);
        }
        if ($c['kind'] === 'livre' && isset($next['volume'])) {
            // Tome suivant : le tome en cours est marqué lu, et le suivant commencé (même instant).
            $st = Batons\Works::status((int) $w['id'], (int) $c['id']);
            $cur = Batons\Works::currentVolume((int) $w['id'], (int) $c['id']);
            $now = Time::nowUtc();
            $msg = '';
            $undo = [];
            if ($st['state'] === 'playing' && $cur !== null) {
                // Fin du tome en cours une seconde avant le début du suivant (ordre garanti, jamais dans le futur).
                $undo[] = Events::addWork((int) $c['id'], gmdate('Y-m-d H:i:s', strtotime($now . ' UTC') - 1), (int) $w['id'], null, null, $cur, null, null, 'finish');
                $msg = 'Tome ' . $cur . ' lu, ';
            }
            $undo[] = $evId = Events::addWork((int) $c['id'], $now, (int) $w['id'],
                null, null, (int) $next['volume'], null, null, 'start');
            Http::flash('« ' . $w['title'] . ' » : ' . $msg . 'tome ' . $next['volume'] . ' commencé.', $undo);
            Http::back('category', ['id' => $c['id']]);
        }
        $evId = Events::addWork((int) $c['id'], Time::nowUtc(), (int) $w['id'],
            $next['season'] ?? null, $next['episode'] ?? null, $next['volume'] ?? null, null, null);
        Http::flash('« ' . trim($w['title'] . ' ' . Batons\Works::progress($next['season'] ?? null, $next['episode'] ?? null, $next['volume'] ?? null)) . ' » ajouté.', $evId);
        Http::back('category', ['id' => $c['id']]);

    case 'thumb':
        $u = (string) ($_GET['u'] ?? '');
        $bin = Batons\Works::thumb($u);
        if ($bin === null) {
            http_response_code(404);
            exit;
        }
        $mime = getimagesizefromstring($bin)['mime'] ?? 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=604800');
        header('X-Content-Type-Options: nosniff');
        echo $bin;
        exit;

    case 'poster':
        $w = Batons\Works::find($uid, (int) Http::intInput('id'));
        // Pas d'affiche d'une œuvre qui n'apparaît que dans des catégories masquées (verrouillé).
        $visible = $w && (!Batons\Db::value('SELECT 1 FROM events WHERE work_id = ?', [$w['id']])
            || Batons\Db::value('SELECT 1 FROM events e JOIN categories c ON c.id = e.category_id
                LEFT JOIN groups g ON g.id = c.group_id WHERE e.work_id = ?' . Privacy::visibleSql(), [$w['id']]));
        $file = $w ? Batons\Works::posterFile((int) $w['id']) : '';
        if (!$visible || !$w['has_poster'] || !is_file($file)) {
            http_response_code(404);
            exit;
        }
        $mime = (getimagesize($file)['mime'] ?? 'image/jpeg');
        header('Content-Type: ' . (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : 'image/jpeg'));
        header('Cache-Control: private, max-age=604800');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
}
