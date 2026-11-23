<?php
declare(strict_types=1);

/*
 * Routes — Catégories : accueil, ajout éclair, fiche, création / édition, groupes, réorganisation, icônes.
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
    // --- Accueil : grille des catégories ---------------------------------------
    case 'home':
        Http::render('home', [
            'uid' => $uid,
            'categories' => Categories::listWithSummary($uid),
            'archivedCount' => count(Categories::listWithSummary($uid, true)),
        ]);

    // --- Ajout éclair : un tap = un bâton maintenant ----------------------------
    case 'tap':
        $c = $category(Http::intInput('id'));
        if ($c['kind'] !== 'simple') {
            Http::redirect('work_add', ['id' => $c['id']]); // une œuvre se choisit
        }
        // Sans JS, retour sur la tuile (#t-ID) plutôt qu'en haut de l'accueil ; avec JS, l'envoi est « doux » (app.js).
        $_POST['back'] ??= url('home') . '#t-' . (int) $c['id'];
        if ($c['periods']) {
            // Option « périodes » : un tap commence la période, le suivant la termine.
            $open = Batons\Periods::open((int) $c['id']);
            $id = Events::add((int) $c['id'], null, null, null, $open ? 'finish' : 'start');
            if ($open) {
                $p = Batons\Periods::open((int) $c['id']) ?? Batons\Periods::list((int) $c['id'])[0];
                Http::flash('« ' . $c['name'] . ' » terminé : ' . Batons\Works::daysLabel((int) $p['days']) . '.', $id);
            } else {
                Http::flash('« ' . $c['name'] . ' » commencé.', $id);
            }
            Http::back();
        }
        $id = Events::add((int) $c['id']);
        Http::flash('Bâton ajouté à « ' . $c['name'] . ' ».', $id);
        Http::back();

    case 'undo':
        $ids = array_filter(array_map('intval', explode(',', Http::input('ids') ?: Http::input('id'))));
        $n = 0;
        foreach ($ids as $undoId) {
            if ($ev = Events::find($uid, $undoId)) { // seulement les bâtons de l'utilisateur
                Events::delete((int) $ev['id']);
                $n++;
            }
        }
        Http::flash($n > 1 ? 'Bâtons annulés.' : 'Bâton annulé.');
        Http::back();

    // --- Fiche catégorie ----------------------------------------------------------
    case 'category':
        $c = $category(Http::intInput('id'));
        $scale = in_array($_GET['scale'] ?? '', ['day', 'week', 'month'], true) ? $_GET['scale'] : 'day';
        $stats = Events::stats((int) $c['id'], (bool) $c['count_future']);
        $metric = ($_GET['metric'] ?? '') === 'sum' || (($_GET['metric'] ?? '') === '' && (int) $stats['with_value'] > 0) ? 'sum' : 'count';
        if ((int) $stats['with_value'] === 0) {
            $metric = 'count';
        }
        $page = max(0, (int) ($_GET['page'] ?? 0));
        Http::render('category', [
            'c'      => $c,
            'stats'  => $stats,
            'series' => Events::series((int) $c['id'], $scale, (bool) $c['count_future']),
            'scale'  => $scale,
            'metric' => $metric,
            'events' => Events::forCategory((int) $c['id'], 51, $page * 50),
            'page'   => $page,
            'uid'    => $uid,
        ], $c['name']);


    // --- Catégories : création, édition, archivage, suppression, ordre -------------
    case 'category_new':
    case 'category_edit':
        $c = $route === 'category_edit' ? $category(Http::intInput('id'))
            : ['id' => null, 'name' => '', 'icon' => '', 'color' => '#ea580c', 'unit' => '', 'group_name' => '', 'archived' => 0, 'hidden' => 0, 'periods' => 0, 'count_future' => 0, 'kind' => in_array($_GET['kind'] ?? '', ['film', 'serie', 'livre', 'jeu'], true) ? $_GET['kind'] : 'simple'];
        Http::render('category_form', ['c' => $c, 'errors' => [], 'groups' => Categories::groupNames($uid)],
            $c['id'] ? 'Modifier « ' . $c['name'] . ' »' : 'Nouvelle catégorie');

    case 'category_save':
        $id = Http::intInput('id');
        if ($id !== null) {
            $category($id);
        }
        // Groupe choisi dans la liste, ou « Nouveau groupe… » + nom tapé (sans liste : le champ seul).
        if (isset($_POST['group_pick']) && $_POST['group_pick'] !== '__new') {
            $_POST['group'] = $_POST['group_pick'];
        }
        [$data, $errors] = Categories::validate($_POST);
        if ($data['hidden'] && !Privacy::hasPin($uid)) {
            $errors['hidden'] = 'Définis d\'abord un code de déverrouillage (menu du logo › Afficher les éléments masqués).';
        }
        if ($errors) {
            $c = ['id' => $id, 'name' => $data['name'], 'icon' => $data['icon'], 'color' => $data['color'],
                  'unit' => $data['unit'], 'group_name' => $data['group'], 'archived' => 0, 'hidden' => $data['hidden'] ? 1 : 0, 'kind' => $data['kind'], 'periods' => $data['periods'] ? 1 : 0];
            Http::render('category_form', ['c' => $c, 'errors' => $errors, 'groups' => Categories::groupNames($uid)], 'Catégorie');
        }
        if ($id !== null) {
            Categories::update($uid, $id, $data);
            if ($data['hidden'] && !Privacy::isUnlocked()) {
                Http::flash('Catégorie « ' . $data['name'] . ' » masquée.');
                Http::redirect();
            }
            Http::flash('Catégorie enregistrée.');
            Http::back('category', ['id' => $id]);
        }
        $id = Categories::create($uid, $data);
        Http::flash('Catégorie « ' . $data['name'] . ' » créée.');
        Http::redirect();

    case 'category_archive':
        $c = $category(Http::intInput('id'));
        $archive = !(bool) $c['archived'];
        Categories::setArchived($uid, (int) $c['id'], $archive);
        Http::flash($archive ? 'Catégorie archivée.' : 'Catégorie restaurée.');
        Http::redirect($archive ? 'home' : 'category', $archive ? [] : ['id' => $c['id']]);

    case 'category_delete':
        $c = $category(Http::intInput('id'));
        Categories::delete($uid, (int) $c['id']);
        Http::flash('Catégorie « ' . $c['name'] . ' » supprimée, avec ses bâtons.');
        Http::redirect();

    case 'category_move':
        $c = $category(Http::intInput('id'));
        Categories::move($uid, (int) $c['id'], Http::input('dir') === 'up' ? -1 : 1);
        Http::back('category_order');


    case 'group_hide':
        $hide = Http::input('hidden') === '1';
        if ($hide && !Privacy::hasPin($uid)) {
            Http::flash('Définis d\'abord un code de déverrouillage pour pouvoir masquer.');
            Http::redirect('unlock');
        }
        if (!$hide && !Privacy::isUnlocked()) {
            Http::redirect('unlock');
        }
        Privacy::setGroupHidden($uid, (int) Http::intInput('id'), $hide);
        Http::flash($hide ? 'Groupe masqué.' : 'Groupe de nouveau visible.');
        Http::back('category_order');

    case 'group_move':
        Categories::moveGroup($uid, (int) Http::intInput('id'), Http::input('dir') === 'up' ? -1 : 1);
        Http::back('category_order');

    case 'reorder':
        // Glisser-déposer : nouvel ordre complet (groupes, puis catégories par groupe). Réponse JSON.
        $groupOrder = array_filter(explode(',', Http::input('groups')), 'ctype_digit');
        $catOrder = [];
        foreach ((array) ($_POST['cats'] ?? []) as $gid => $list) {
            if (ctype_digit((string) $gid) && is_string($list)) {
                $catOrder[(int) $gid] = array_filter(explode(',', $list), 'ctype_digit');
            }
        }
        $ok = Categories::reorder($uid, $groupOrder, $catOrder);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($ok ? 200 : 409);
        echo json_encode(['ok' => $ok]);
        exit;

    case 'group_rename':
        $g = Categories::group($uid, (int) Http::intInput('id'));
        if ($g === null) {
            Http::notFound();
        }
        $name = trim(preg_replace('/\s+/u', ' ', Http::input('label')));
        $error = null;
        $conflict = null;
        if (Http::isPost() || isset($_GET['label'])) {
            if ($name === '') {
                $error = 'L\'intitulé est obligatoire.';
            } elseif (mb_strlen($name) > 80) {
                $error = '80 caractères maximum.';
            } elseif ($name !== $g['name']) {
                $conflict = Categories::groupNamed($uid, $name, (int) $g['id']);
                if ($conflict && $conflict['hidden'] && !Privacy::isUnlocked()) {
                    $error = 'Cet intitulé n\'est pas disponible.';
                    $conflict = null;
                }
            }
        }
        if (Http::isPost() && $error === null) {
            if ($conflict && Http::input('merge') === '1') {
                Categories::mergeGroups($uid, (int) $g['id'], (int) $conflict['id']);
                Http::flash('Groupes réunis dans « ' . $conflict['name'] . ' ».');
                Http::back('category_order');
            }
            if (!$conflict) {
                if ($name !== $g['name']) {
                    Categories::renameGroup($uid, (int) $g['id'], $name);
                    Http::flash('Groupe renommé en « ' . $name . ' ».');
                }
                Http::back('category_order');
            }
        }
        Http::render('group_rename', ['g' => $g, 'name' => $name !== '' ? $name : $g['name'],
            'error' => $error, 'conflict' => $conflict], 'Renommer le groupe');

    case 'category_order':
        Http::render('category_order', [
            'categories' => Categories::listWithSummary($uid),
            'groups'     => Categories::groups($uid),
        ], 'Réorganiser');


    // --- Recherche d'icônes (JSON, pour le sélecteur du formulaire) ----------------
    case 'icons':
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=3600');
        echo json_encode(Batons\Icons::search((string) ($_GET['q'] ?? '')), JSON_UNESCAPED_SLASHES);
        exit;
}
