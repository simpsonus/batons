<?php
declare(strict_types=1);

/*
 * Routes — Import et export.
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
    // --- Import (Chronicling, ou export CSV de Bâtons) -----------------------------
    case 'import':
    case 'import_check':
        // Import en trois étapes : provenance -> fichiers -> vérification (rien n'est écrit) -> import.
        // Les fichiers envoyés sont gardés le temps de la confirmation dans data_dir/import/<jeton>.
        $importDir = Batons\Config::get('data_dir') . '/import';
        foreach (glob($importDir . '/*', GLOB_ONLYDIR) ?: [] as $old) {        // ménage : plus de 24 h
            if (filemtime($old) < time() - 86400) {
                array_map('unlink', glob($old . '/*') ?: []);
                @rmdir($old);
            }
        }
        $source = in_array($_GET['source'] ?? $_POST['source'] ?? '', ['chronicling', 'batons'], true) ? ($_GET['source'] ?? $_POST['source']) : null;
        $loadFiles = static function (string $dir): array {
            $meta = json_decode((string) @file_get_contents($dir . '/meta.json'), true) ?: [];
            $files = [];
            foreach ($meta as $stored => $name) {
                if (is_file($dir . '/' . $stored)) {
                    $files[$dir . '/' . $stored] = $name;
                }
            }
            return $files;
        };
        if ($route === 'import_check' && Http::isPost()) {
            $token = bin2hex(random_bytes(12));
            $dir = $importDir . '/' . $uid . '-' . $token;
            @mkdir($dir, 0700, true);
            $meta = [];
            foreach ((array) ($_FILES['files']['tmp_name'] ?? []) as $i => $tmp) {
                if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                    $stored = 'f' . count($meta) . '.csv';
                    if (move_uploaded_file($tmp, $dir . '/' . $stored)) {
                        $meta[$stored] = (string) $_FILES['files']['name'][$i];
                    }
                }
            }
            file_put_contents($dir . '/meta.json', json_encode($meta));
            $importer = new Batons\Importer($uid);
            if ($meta === []) {
                $importer->report['errors'][] = 'Aucun fichier reçu : choisis au moins un fichier CSV.';
            } else {
                $importer->importFiles($loadFiles($dir), true);
            }
            Http::render('import', ['step' => 'check', 'source' => $source, 'report' => $importer->report, 'token' => $token], 'Importer');
        }
        if ($route === 'import' && Http::isPost()) {
            $token = preg_replace('/[^0-9a-f]/', '', (string) Http::input('token'));
            $dir = $importDir . '/' . $uid . '-' . $token;
            $importer = new Batons\Importer($uid);
            $files = $token !== '' && is_dir($dir) ? $loadFiles($dir) : [];
            if ($files === []) {
                $importer->report['errors'][] = 'Les fichiers à importer ont expiré : recommence l\'import.';
            } else {
                $importer->importFiles($files);
            }
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
            Http::render('import', ['step' => 'done', 'source' => $source, 'report' => $importer->report], 'Importer');
        }
        Http::render('import', ['step' => $source ? 'files' : 'source', 'source' => $source, 'report' => null], 'Importer');


    // --- Export CSV ----------------------------------------------------------------
    case 'export':
        // Page de choix d'abord ; le fichier n'est produit qu'avec « download ».
        $all = Categories::listWithSummary($uid);
        $only = null;
        if (($_GET['what'] ?? 'all') === 'one') {
            $only = Categories::find($uid, Http::intInput('cat'));
        }
        if (!isset($_GET['download'])) {
            Http::render('export', ['categories' => array_merge($all, Categories::listWithSummary($uid, true)),
                'hiddenLocked' => !Privacy::isUnlocked() && Privacy::hiddenCategoryNames($uid) !== []], 'Exporter');
        }
        $slug = $only ? '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $only['name']) ?: 'categorie')), '-') : '';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="batons' . $slug . '-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, ['date_locale', 'date_utc', 'categorie', 'groupe', 'valeur', 'unite', 'note',
            'type', 'oeuvre', 'oeuvre_detail', 'annee', 'source', 'source_id', 'saison', 'episode', 'tome', 'note_sur_10', 'etape'], ';');
        $stmt = Events::allForExport($uid, $only ? (int) $only['id'] : null);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [Time::local($r['occurred_at'])->format('Y-m-d H:i'), $r['occurred_at'], $r['category'],
                $r['grp'], $r['value'], $r['unit'], $r['note'], $r['kind'], $r['work'], $r['work_subtitle'], $r['work_year'],
                $r['work_source'], $r['work_source_id'], $r['season'], $r['episode'], $r['volume'], $r['rating'],
                ['start' => 'debut', 'finish' => 'fin'][$r['milestone'] ?? ''] ?? ''], ';');
        }
        exit;
}
