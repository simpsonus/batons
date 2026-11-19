<?php
declare(strict_types=1);

/**
 * Génère app/icons/mdi.php à partir de Material Design Icons (les icônes de Home Assistant).
 * Licence des icônes : Apache 2.0 — https://pictogrammers.com/library/mdi/
 *
 * Usage : php app/bin/fetch-icons.php [chemin/vers/mdi.js chemin/vers/meta.json]
 * Sans argument, télécharge la version épinglée depuis jsDelivr (nécessite l'extension openssl).
 * Lancé automatiquement à la construction de l'image Docker.
 */

const MDI_VERSION = '7.4.47';

$jsSrc   = $argv[1] ?? 'https://cdn.jsdelivr.net/npm/@mdi/js@' . MDI_VERSION . '/mdi.js';
$metaSrc = $argv[2] ?? 'https://cdn.jsdelivr.net/npm/@mdi/svg@' . MDI_VERSION . '/meta.json';

$js = @file_get_contents($jsSrc);
$meta = @file_get_contents($metaSrc);
if ($js === false || $meta === false) {
    fwrite(STDERR, "Échec du téléchargement des icônes ($jsSrc / $metaSrc)\n");
    exit(1);
}

// mdi.js : export var mdiCoffeeOutline = "M2,21H20…";
preg_match_all('/export\s+(?:var|const)\s+mdi([A-Za-z0-9]+)\s*=\s*"([^"]+)"/', $js, $m, PREG_SET_ORDER);
$paths = [];
foreach ($m as [, $camel, $path]) {
    $paths[$camel] = $path; // clé : CoffeeOutline
}

$icons = [];
foreach (json_decode($meta, true, 512, JSON_THROW_ON_ERROR) as $entry) {
    $name = $entry['name'];                                              // coffee-outline
    $camel = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));  // CoffeeOutline
    if (!isset($paths[$camel]) || !empty($entry['deprecated'])) {
        continue;
    }
    $keywords = array_unique(array_map('strtolower', array_merge($entry['aliases'] ?? [], $entry['tags'] ?? [])));
    $icons[$name] = ['p' => $paths[$camel], 'k' => implode(' ', $keywords)];
}

if (count($icons) < 1000 && !isset($argv[1])) {
    fwrite(STDERR, 'Trop peu d\'icônes trouvées (' . count($icons) . "), format inattendu ?\n");
    exit(1);
}

$dir = dirname(__DIR__) . '/icons';
@mkdir($dir, 0775, true);
file_put_contents("$dir/mdi.php", "<?php\n// Généré par bin/fetch-icons.php — Material Design Icons " . MDI_VERSION . " (Apache 2.0)\nreturn " . var_export($icons, true) . ";\n");
echo count($icons) . " icônes MDI écrites dans $dir/mdi.php\n";
