<?php
declare(strict_types=1);

/**
 * Bâtons — point d'entrée unique.
 * Les routes passent par le paramètre « r » (./?r=category&id=3) : aucune réécriture d'URL nécessaire.
 */

use Batons\Auth;
use Batons\Categories;
use Batons\Events;
use Batons\Http;
use Batons\Time;

require dirname(__DIR__) . '/src/bootstrap.php';

$route = (string) ($_GET['r'] ?? 'home');

// Sonde de santé, sans authentification (Docker HEALTHCHECK, supervision).
if ($route === 'health') {
    header('Content-Type: text/plain');
    Batons\Db::pdo();
    exit('ok');
}

Http::startSession();
$uid = Auth::id();

if (Http::isPost()) {
    Http::checkCsrf();
}

/** Lit une valeur numérique saisie (accepte la virgule). */
$parseValue = static function (string $raw): ?float {
    $raw = str_replace([' ', ','], ['', '.'], trim($raw));
    return is_numeric($raw) ? (float) $raw : null;
};

/** Catégorie de l'utilisateur ou 404. */
$category = static function (?int $id) use ($uid): array {
    return ($id !== null ? Categories::find($uid, $id) : null) ?? Http::notFound('Catégorie');
};

/** Bâton de l'utilisateur ou 404. */
$event = static function (?int $id) use ($uid): array {
    return ($id !== null ? Events::find($uid, $id) : null) ?? Http::notFound('Bâton');
};

switch ($route) {

    // --- Accueil : grille des catégories ---------------------------------------
    case 'home':
        Http::render('home', [
            'categories' => Categories::listWithSummary($uid),
            'archivedCount' => count(Categories::listWithSummary($uid, true)),
        ]);

    // --- Ajout éclair : un tap = un bâton maintenant ----------------------------
    case 'tap':
        $c = $category(Http::intInput('id'));
        $id = Events::add((int) $c['id']);
        Http::flash('Bâton ajouté à « ' . $c['name'] . ' ».', $id);
        Http::back();

    case 'undo':
        $ev = $event(Http::intInput('id'));
        Events::delete((int) $ev['id']);
        Http::flash('Bâton annulé.');
        Http::back();

    // --- Fiche catégorie ----------------------------------------------------------
    case 'category':
        $c = $category(Http::intInput('id'));
        $scale = in_array($_GET['scale'] ?? '', ['day', 'week', 'month'], true) ? $_GET['scale'] : 'day';
        $stats = Events::stats((int) $c['id']);
        $metric = ($_GET['metric'] ?? '') === 'sum' || (($_GET['metric'] ?? '') === '' && (int) $stats['with_value'] > 0) ? 'sum' : 'count';
        if ((int) $stats['with_value'] === 0) {
            $metric = 'count';
        }
        $page = max(0, (int) ($_GET['page'] ?? 0));
        Http::render('category', [
            'c'      => $c,
            'stats'  => $stats,
            'series' => Events::series((int) $c['id'], $scale),
            'scale'  => $scale,
            'metric' => $metric,
            'events' => Events::forCategory((int) $c['id'], 51, $page * 50),
            'page'   => $page,
        ], $c['name']);

    // --- Bâtons : ajout détaillé, édition, suppression ----------------------------
    case 'event_add':
        $c = $category(Http::intInput('category_id'));
        $at = Time::fromLocalInput(Http::input('occurred_at')) ?? Time::nowUtc();
        $id = Events::add((int) $c['id'], $at, $parseValue(Http::input('value')), Http::input('note'));
        Http::flash('Bâton ajouté.', $id);
        Http::redirect('category', ['id' => $c['id']]);

    case 'event_edit':
        $ev = $event(Http::intInput('id'));
        Http::render('event_form', ['ev' => $ev], 'Modifier le bâton');

    case 'event_save':
        $ev = $event(Http::intInput('id'));
        $at = Time::fromLocalInput(Http::input('occurred_at')) ?? $ev['occurred_at'];
        Events::update((int) $ev['id'], $at, $parseValue(Http::input('value')), Http::input('note'));
        Http::flash('Bâton modifié.');
        Http::redirect('category', ['id' => $ev['category_id']]);

    case 'event_delete':
        $ev = $event(Http::intInput('id'));
        Events::delete((int) $ev['id']);
        Http::flash('Bâton supprimé.');
        Http::back('category', ['id' => $ev['category_id']]);

    // --- Catégories : création, édition, archivage, suppression, ordre -------------
    case 'category_new':
    case 'category_edit':
        $c = $route === 'category_edit' ? $category(Http::intInput('id'))
            : ['id' => null, 'name' => '', 'icon' => '', 'color' => '#c2410c', 'unit' => '', 'group_name' => '', 'archived' => 0];
        Http::render('category_form', ['c' => $c, 'errors' => [], 'groups' => Categories::groupNames($uid)],
            $c['id'] ? 'Modifier « ' . $c['name'] . ' »' : 'Nouvelle catégorie');

    case 'category_save':
        $id = Http::intInput('id');
        if ($id !== null) {
            $category($id);
        }
        [$data, $errors] = Categories::validate($_POST);
        if ($errors) {
            $c = ['id' => $id, 'name' => $data['name'], 'icon' => $data['icon'], 'color' => $data['color'],
                  'unit' => $data['unit'], 'group_name' => $data['group'], 'archived' => 0];
            Http::render('category_form', ['c' => $c, 'errors' => $errors, 'groups' => Categories::groupNames($uid)], 'Catégorie');
        }
        if ($id !== null) {
            Categories::update($uid, $id, $data);
            Http::flash('Catégorie enregistrée.');
            Http::redirect('category', ['id' => $id]);
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

    case 'category_order':
        Http::render('category_order', ['categories' => Categories::listWithSummary($uid)], 'Réorganiser');

    case 'archives':
        Http::render('archives', ['categories' => Categories::listWithSummary($uid, true)], 'Archives');

    // --- Chronologie ---------------------------------------------------------------
    case 'timeline':
        $page = max(0, (int) ($_GET['page'] ?? 0));
        Http::render('timeline', ['events' => Events::timeline($uid, 101, $page * 100), 'page' => $page], 'Chronologie');

    // --- Import (Chronicling, ou export CSV de Bâtons) -----------------------------
    case 'import':
        if (!Http::isPost()) {
            Http::render('import', ['report' => null], 'Importer');
        }
        $files = [];
        foreach ((array) ($_FILES['files']['tmp_name'] ?? []) as $i => $tmp) {
            if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                $files[$tmp] = (string) $_FILES['files']['name'][$i];
            }
        }
        $importer = new Batons\Importer($uid);
        if ($files === []) {
            $importer->report['errors'][] = 'Aucun fichier reçu.';
        } else {
            $importer->importFiles($files);
        }
        Http::render('import', ['report' => $importer->report], 'Importer');

    // --- Recherche d'icônes (JSON, pour le sélecteur du formulaire) ----------------
    case 'icons':
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=3600');
        echo json_encode(Batons\Icons::search((string) ($_GET['q'] ?? '')), JSON_UNESCAPED_SLASHES);
        exit;

    // --- Export CSV ----------------------------------------------------------------
    case 'export':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="batons-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, ['date_locale', 'date_utc', 'categorie', 'groupe', 'valeur', 'unite', 'note'], ';');
        $stmt = Events::allForExport($uid);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [Time::local($r['occurred_at'])->format('Y-m-d H:i'), $r['occurred_at'], $r['category'],
                $r['grp'], $r['value'], $r['unit'], $r['note']], ';');
        }
        exit;

    default:
        Http::notFound();
}
