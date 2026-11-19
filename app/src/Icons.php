<?php
declare(strict_types=1);

namespace Batons;

/**
 * Icônes des catégories : soit un émoji / quelques caractères, soit une icône
 * Material Design Icons notée « mdi:nom » (les mêmes que dans Home Assistant).
 * La table des icônes (app/icons/mdi.php) est générée par bin/fetch-icons.php ;
 * si elle est absente, seuls les émojis sont disponibles.
 */
final class Icons
{
    private static ?array $mdi = null;

    /** Suggestions affichées avant toute recherche. */
    public const SUGGESTED = [
        'coffee', 'cup-water', 'glass-wine', 'beer', 'food-apple', 'silverware-fork-knife', 'cigarette',
        'pill', 'needle', 'emoticon-sick-outline', 'head-flash-outline', 'heart-pulse', 'thermometer', 'bed',
        'run', 'walk', 'bike', 'swim', 'dumbbell', 'yoga', 'scale-bathroom',
        'emoticon-happy-outline', 'emoticon-sad-outline', 'meditation', 'book-open-variant', 'music', 'gamepad-variant',
        'flower', 'watering-can', 'washing-machine', 'dishwasher', 'broom', 'trash-can', 'shower', 'toothbrush',
        'dog', 'cat', 'baby-bottle', 'car', 'gas-station', 'cash', 'cart', 'phone', 'airplane', 'home', 'star',
    ];

    /** Quelques mots français -> termes anglais de MDI, pour la recherche. */
    private const FR = [
        'café' => 'coffee', 'cafe' => 'coffee', 'thé' => 'tea', 'the' => 'tea', 'eau' => 'water', 'vin' => 'wine',
        'bière' => 'beer', 'biere' => 'beer', 'alcool' => 'glass', 'verre' => 'glass', 'repas' => 'food',
        'nourriture' => 'food', 'manger' => 'food', 'pomme' => 'apple', 'cigarette' => 'cigarette', 'tabac' => 'smoking',
        'médicament' => 'pill', 'medicament' => 'pill', 'pilule' => 'pill', 'cachet' => 'pill', 'piqûre' => 'needle',
        'vaccin' => 'needle', 'santé' => 'heart', 'sante' => 'heart', 'cœur' => 'heart', 'coeur' => 'heart',
        'migraine' => 'head', 'tête' => 'head', 'tete' => 'head', 'douleur' => 'emoticon-sick', 'malade' => 'sick',
        'fièvre' => 'thermometer', 'fievre' => 'thermometer', 'température' => 'thermometer', 'sommeil' => 'sleep',
        'dormir' => 'bed', 'lit' => 'bed', 'sieste' => 'sleep', 'course' => 'run', 'courir' => 'run', 'marche' => 'walk',
        'vélo' => 'bike', 'velo' => 'bike', 'natation' => 'swim', 'piscine' => 'pool', 'sport' => 'dumbbell',
        'muscu' => 'dumbbell', 'poids' => 'scale', 'humeur' => 'emoticon', 'joie' => 'happy', 'triste' => 'sad',
        'livre' => 'book', 'lecture' => 'book', 'musique' => 'music', 'jeu' => 'gamepad', 'fleur' => 'flower',
        'plante' => 'flower', 'arrosage' => 'watering', 'arroser' => 'watering', 'jardin' => 'shovel',
        'lessive' => 'washing-machine', 'linge' => 'washing-machine', 'vaisselle' => 'dishwasher', 'ménage' => 'broom',
        'menage' => 'broom', 'poubelle' => 'trash', 'douche' => 'shower', 'bain' => 'bathtub', 'dents' => 'toothbrush',
        'chien' => 'dog', 'chat' => 'cat', 'bébé' => 'baby', 'bebe' => 'baby', 'biberon' => 'baby-bottle',
        'voiture' => 'car', 'essence' => 'gas-station', 'carburant' => 'gas-station', 'argent' => 'cash',
        'courses' => 'cart', 'achat' => 'cart', 'téléphone' => 'phone', 'telephone' => 'phone', 'avion' => 'airplane',
        'voyage' => 'airplane', 'maison' => 'home', 'travail' => 'briefcase', 'école' => 'school', 'ecole' => 'school',
        'étoile' => 'star', 'etoile' => 'star', 'pluie' => 'weather-rainy', 'soleil' => 'weather-sunny',
        'chauffage' => 'radiator', 'électricité' => 'flash', 'electricite' => 'flash', 'ampoule' => 'lightbulb',
        'cuisine' => 'chef-hat', 'pain' => 'bread', 'gâteau' => 'cake', 'gateau' => 'cake', 'anniversaire' => 'cake',
        'médecin' => 'doctor', 'medecin' => 'doctor', 'hôpital' => 'hospital', 'hopital' => 'hospital',
        'méditation' => 'meditation', 'meditation' => 'meditation', 'prière' => 'hands-pray', 'priere' => 'hands-pray',
    ];

    public static function available(): bool
    {
        return self::map() !== [];
    }

    /** Table nom => ['p' => path SVG, 'k' => mots-clés]. */
    private static function map(): array
    {
        if (self::$mdi === null) {
            $file = dirname(__DIR__) . '/icons/mdi.php';
            self::$mdi = is_file($file) ? (require $file) : [];
        }
        return self::$mdi;
    }

    public static function path(string $name): ?string
    {
        return self::map()[$name]['p'] ?? null;
    }

    /** Valeur saisie valide ? (émoji/texte court, ou « mdi:nom » existant) */
    public static function isValid(string $icon): bool
    {
        if (str_starts_with($icon, 'mdi:')) {
            return !self::available() || self::path(substr($icon, 4)) !== null;
        }
        return mb_strlen($icon) <= 8;
    }

    /** HTML de l'icône (SVG inline pour MDI, texte échappé sinon). */
    public static function html(?string $icon): string
    {
        $icon = (string) $icon;
        if (str_starts_with($icon, 'mdi:')) {
            $path = self::path(substr($icon, 4));
            if ($path !== null) {
                return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . htmlspecialchars($path, ENT_QUOTES) . '"/></svg>';
            }
            return '<span aria-hidden="true">?</span>';
        }
        return htmlspecialchars($icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Recherche : renvoie une liste de ['n' => nom, 'p' => path], meilleurs résultats d'abord. */
    public static function search(string $q, int $limit = 60): array
    {
        $map = self::map();
        if ($map === []) {
            return [];
        }
        $q = mb_strtolower(trim($q));
        if (str_starts_with($q, 'mdi:')) {
            $q = substr($q, 4);
        }
        if ($q === '') {
            $names = array_values(array_filter(self::SUGGESTED, static fn($n) => isset($map[$n])));
        } else {
            $terms = array_unique(array_filter([str_replace(' ', '-', $q), self::FR[$q] ?? null]));
            $scored = [];
            foreach ($map as $name => $icon) {
                $best = 0;
                foreach ($terms as $t) {
                    $score = match (true) {
                        $name === $t                                 => 100,
                        str_starts_with($name, $t)                   => 80 - min(30, strlen($name) - strlen($t)),
                        str_contains($name, $t)                      => 50 - min(30, strlen($name) - strlen($t)),
                        str_contains(' ' . $icon['k'] . ' ', " $t ") => 30,
                        str_contains($icon['k'], $t)                 => 10,
                        default                                      => 0,
                    };
                    $best = max($best, $score);
                }
                if ($best > 0) {
                    $scored[$name] = $best;
                }
            }
            arsort($scored);
            $names = array_keys(array_slice($scored, 0, $limit, true));
        }
        return array_map(static fn($n) => ['n' => $n, 'p' => $map[$n]['p']], array_slice($names, 0, $limit));
    }
}
