<?php
declare(strict_types=1);

/*
 * Routes — Bâtons : saisie détaillée, édition, suppression ; Chronologie.
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
    // --- Bâtons : ajout détaillé, édition, suppression ----------------------------
    case 'event_add':
        $c = $category(Http::intInput('category_id'));
        $at = Time::fromLocalInput(Http::input('occurred_at')) ?? Time::nowUtc();
        $id = Events::add((int) $c['id'], $at, $parseValue(Http::input('value')), Http::input('note'),
            $c['periods'] ? $milestone(Http::input('milestone')) : null);
        Http::flash('Bâton ajouté.', $id);
        Http::redirect('category', ['id' => $c['id']]);

    case 'event_edit':
        $ev = $event(Http::intInput('id'));
        Http::render('event_form', ['ev' => $ev], 'Modifier le bâton');

    case 'event_save':
        $ev = $event(Http::intInput('id'));
        $at = Time::fromLocalInput(Http::input('occurred_at')) ?? $ev['occurred_at'];
        Events::update((int) $ev['id'], $at, $parseValue(Http::input('value')), Http::input('note'));
        if (!$ev['work_id'] && $ev['periods']) {
            Batons\Db::run('UPDATE events SET milestone = ? WHERE id = ?', [$milestone(Http::input('milestone')), $ev['id']]);
        }
        if ($ev['work_id']) {
            Events::updateWork((int) $ev['id'], $posInt(Http::input('season')), $posInt(Http::input('episode')),
                $posInt(Http::input('volume')), $rating(Http::input('rating')),
                in_array($ev['kind'], Batons\Works::TRACKED, true) ? $milestone(Http::input('milestone')) : null);
        }
        Http::flash('Bâton modifié.');
        Http::back('category', ['id' => $ev['category_id']]);

    case 'event_delete':
        $ev = $event(Http::intInput('id'));
        Events::delete((int) $ev['id']);
        Http::flash('Bâton supprimé.');
        Http::back('category', ['id' => $ev['category_id']]);


    // --- Chronologie ---------------------------------------------------------------
    case 'timeline':
        // Filtre : g = groupes entiers, c = catégories isolées (listes « 1,2,3 » ou tableaux g[] / c[] du panneau).
        // Groupe 0 = « Sans groupe ». Rien de choisi = pas de filtre.
        $ids = static function (string $k): array {
            $v = $_GET[$k] ?? [];
            $v = is_array($v) ? $v : explode(',', (string) $v);
            return array_values(array_unique(array_map('intval', array_filter($v, static fn($x) => ctype_digit((string) $x)))));
        };
        $cats = array_merge(Categories::listWithSummary($uid), Categories::listWithSummary($uid, true));
        $byGroup = [];
        foreach ($cats as $cat) {
            $byGroup[(int) $cat['group_id']][] = (int) $cat['id'];
        }
        $selected = $ids('c');
        foreach ($ids('g') as $gid) {
            $selected = array_merge($selected, $byGroup[$gid] ?? []);
        }
        $selected = array_values(array_intersect(array_unique($selected), array_merge(...array_values($byGroup ?: [[]]))));
        // Forme canonique : les groupes entièrement choisis deviennent g, le reste c.
        $fullGroups = [];
        $looseCats = $selected;
        foreach ($byGroup as $gid => $members) {
            if ($members && !array_diff($members, $selected)) {
                $fullGroups[] = $gid;
                $looseCats = array_diff($looseCats, $members);
            }
        }
        if ($selected && count($selected) === count($cats)) {
            [$selected, $fullGroups, $looseCats] = [[], [], []];      // tout coché = pas de filtre
        }
        $filter = ['g' => $fullGroups, 'c' => array_values($looseCats), 'cats' => $selected];

        // Recherche (note, titre d'œuvre) et période : p = 7 | 30 | 365 jours glissants, ou dates du … au … (jours locaux).
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $p = (string) ($_GET['p'] ?? '');
        $du = (string) ($_GET['du'] ?? '');
        $au = (string) ($_GET['au'] ?? '');
        $range = null;
        if ($p === 'dates' || ($p === '' && ($du !== '' || $au !== ''))) {
            $range = Batons\Time::daysUtc($du !== '' ? $du : $au, $au !== '' ? $au : $du);
            $p = $range ? 'dates' : '';
            if ($range) {      // dates remises dans l'ordre (au avant du : échangées)
                $du = Batons\Time::local($range[0])->format('Y-m-d');
                $au = Batons\Time::local($range[1])->modify('-1 day')->format('Y-m-d');
            }
        } elseif (in_array($p, ['7', '30', '365'], true)) {
            $range = [Batons\Time::lastDaysUtc((int) $p), null];
        } else {
            $p = '';
        }
        $crit = array_filter(['q' => $q, 'p' => $p, 'du' => $p === 'dates' ? $du : '', 'au' => $p === 'dates' ? $au : ''], 'strlen');
        $filter += ['q' => $q, 'p' => $p, 'du' => $crit['du'] ?? '', 'au' => $crit['au'] ?? '', 'crit' => $crit];

        if (isset($_GET['f']) || is_array($_GET['c'] ?? null) || is_array($_GET['g'] ?? null)) {
            // Envoi du panneau : on redirige vers l'adresse courte, lisible et à garder en favori.
            Http::redirect('timeline', array_filter(['g' => implode(',', $fullGroups), 'c' => implode(',', $filter['c'])], 'strlen') + $crit);
        }
        $page = max(0, (int) ($_GET['page'] ?? 0));
        $opts = ['q' => $q, 'from' => $range[0] ?? null, 'to' => $range[1] ?? null];
        $cid = $selected ? $selected : null;
        Http::render('timeline', [
            'events' => Events::timeline($uid, 101, $page * 100, $cid, $opts),
            'found' => ($q !== '' || $range) ? Events::timelineCount($uid, $cid, $opts) : null,
            'page' => $page, 'filter' => $filter, 'cats' => $cats, 'byGroup' => $byGroup,
            'groups' => Categories::groups($uid), 'counts' => Events::countsByCategory($uid),
        ], 'Chronologie');
}
