<?php
declare(strict_types=1);

// Fonctions globales utilisées dans les vues (espace de noms global, accessibles partout).

use Batons\Http;

/** Échappement HTML. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL relative vers une route. */
function url(string $route = 'home', array $params = []): string
{
    return Http::url($route, $params);
}

/** Champ caché CSRF. */
function csrf(): string
{
    return Http::csrfField();
}

/** URL de la page courante (relative), pour revenir après un POST. */
function here(): string
{
    $q = $_SERVER['QUERY_STRING'] ?? '';
    return './' . ($q !== '' ? '?' . $q : '');
}

/*
 * Retour « d'où l'on vient » : les liens vers une page secondaire portent ?from=<page d'origine>, et la croix ✕
 * (ou « Annuler », ou la redirection après enregistrement) y ramène. Sans « from » valide : un écran fixe par défaut.
 * Seules les URL relatives de l'application sont acceptées (pas de redirection ouverte).
 */
function safe_from(mixed $v): ?string
{
    return is_string($v) && $v !== '' && strlen($v) < 1500 && str_starts_with($v, './')
        && !str_contains($v, '//') && !str_contains($v, '\\') ? $v : null;
}

/** Page d'origine transmise par « from », sinon $default. */
function from_url(string $default): string
{
    return safe_from($_GET['from'] ?? null) ?? $default;
}

/** Paramètres d'un lien qui doit ramener ici (ajoute from = page courante). */
function from_here(array $params): array
{
    return $params + ['from' => here()];
}

/** Paramètres d'un lien vers la même page (pagination, échelle…) : conserve le « from » reçu. */
function keep_from(array $params): array
{
    $f = safe_from($_GET['from'] ?? null);
    return $f ? $params + ['from' => $f] : $params;
}

/** HTML d'une icône de catégorie (émoji ou « mdi:nom »). */
function icon(?string $icon): string
{
    return Batons\Icons::html($icon);
}

/** Petites icônes d'interface (Material Design Icons, Apache 2.0), indépendantes de la table MDI. */
function ui_icon(string $name): string
{
    static $paths = [
        'menu'   => 'M3,6H21V8H3V6M3,11H21V13H3V11M3,16H21V18H3V16Z',
        'grid'   => 'M3,11H11V3H3M5,5H9V9H5M13,21H21V13H13M15,15H19V19H15M3,21H11V13H3M5,15H9V19H5M13,3V11H21V3M19,9H15V5H19Z',
        'clock'  => 'M12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22C6.47,22 2,17.5 2,12A10,10 0 0,1 12,2M12.5,7V12.25L17,14.92L16.25,16.15L11,13V7H12.5Z',
        'reorder'=> 'M9,3L5,7H8V14H10V7H13M16,17V10H14V17H11L15,21L19,17H16Z',
        'archive'=> 'M3,3H21V8H3Z M4,9H20V21H4Z M6,11V19H18V11Z M9,12H15V14H9Z',
        'import' => 'M2,12H4V17H20V12H22V17C22,18.11 21.11,19 20,19H4C2.9,19 2,18.11 2,17V12M12,15L17.55,9.54L16.13,8.13L13,11.25V2H11V11.25L7.88,8.13L6.46,9.55L12,15Z',
        'export' => 'M2,12H4V17H20V12H22V17C22,18.11 21.11,19 20,19H4C2.9,19 2,18.11 2,17V12M12,2L6.46,7.46L7.88,8.88L11,5.75V15H13V5.75L16.13,8.88L17.55,7.45L12,2Z',
        'account'=> 'M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,6A2,2 0 0,0 10,8A2,2 0 0,0 12,10A2,2 0 0,0 14,8A2,2 0 0,0 12,6M12,13C14.67,13 20,14.33 20,17V20H4V17C4,14.33 9.33,13 12,13M12,14.9C9.03,14.9 5.9,16.36 5.9,17V18.1H18.1V17C18.1,16.36 14.97,14.9 12,14.9Z',
        'logout' => 'M17,7L15.59,8.41L18.17,11H8V13H18.17L15.59,15.58L17,17L22,12M4,5H12V3H4C2.9,3 2,3.9 2,5V19C2,20.1 2.9,21 4,21H12V19H4V5Z',
        'chart'  => 'M22,21H2V3H4V19H6V10H10V19H12V6H16V19H18V14H22V21Z',
        'drag'   => 'M9,3H11V5H9V3M13,3H15V5H13V3M9,7H11V9H9V7M13,7H15V9H13V7M9,11H11V13H9V11M13,11H15V13H13V11M9,15H11V17H9V15M13,15H15V17H13V15M9,19H11V21H9V19M13,19H15V21H13V19Z',
        'plus'   => 'M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z',
        'pencil' => 'M14.06,9L15,9.94L5.92,19H5V18.08L14.06,9M17.66,3C17.41,3 17.15,3.1 16.96,3.29L15.13,5.12L18.88,8.87L20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18.17,3.09 17.92,3 17.66,3M14.06,6.19L3,17.25V21H6.75L17.81,9.94L14.06,6.19Z',
        'trash'  => 'M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M7,6H17V19H7V6M9,8V17H11V8H9M13,8V17H15V8H13Z',
        'close'  => 'M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z',
        'chevron'=> 'M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z',
        'up'     => 'M7.41,15.41L12,10.83L16.59,15.41L18,14L12,8L6,14L7.41,15.41Z',
        'down'   => 'M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z',
        'eye'    => 'M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z',
        'eye-off'=> 'M11.83,9L15,12.16C15,12.11 15,12.05 15,12A3,3 0 0,0 12,9C11.94,9 11.89,9 11.83,9M7.53,9.8L9.08,11.35C9.03,11.56 9,11.77 9,12A3,3 0 0,0 12,15C12.22,15 12.44,14.97 12.65,14.92L14.2,16.47C13.53,16.8 12.79,17 12,17A5,5 0 0,1 7,12C7,11.21 7.2,10.47 7.53,9.8M2,4.27L4.28,6.55L4.73,7C3.08,8.3 1.78,10 1,12C2.73,16.39 7,19.5 12,19.5C13.55,19.5 15.03,19.2 16.38,18.66L16.81,19.08L19.73,22L21,20.73L3.27,3M12,7A5,5 0 0,1 17,12C17,12.64 16.87,13.26 16.64,13.82L19.57,16.75C21.07,15.5 22.27,13.86 23,12C21.27,7.61 17,4.5 12,4.5C10.6,4.5 9.26,4.75 8,5.2L10.17,7.35C10.74,7.13 11.35,7 12,7Z',
        'lock'   => 'M12,17A2,2 0 0,0 14,15C14,13.89 13.1,13 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10C4,8.89 4.9,8 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z',
        'lock-open' => 'M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6C4.89,22 4,21.1 4,20V10A2,2 0 0,1 6,8H15V6A3,3 0 0,0 12,3A3,3 0 0,0 9,6H7A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,17A2,2 0 0,0 14,15A2,2 0 0,0 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17Z',
    ];
    return '<svg class="ui" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . $paths[$name] . '"/></svg>';
}

/** Croix « fermer la page » (en haut à droite des pages secondaires) : un lien vers un écran fixe. */
function close_link(string $href, string $label): string
{
    return '<a class="icon-btn close-btn" href="' . e($href) . '" aria-label="' . e($label) . '" title="' . e($label) . '">' . ui_icon('close') . '</a>';
}

/** Note affichée : « ★ 7/10 » (avec texte lisible par les lecteurs d'écran). */
function rating_badge(?int $r): string
{
    if (!$r) {
        return '';
    }
    return '<span class="rating" title="Note : ' . $r . ' sur 10"><span aria-hidden="true">★ ' . $r . '</span><span class="sr">Note : ' . $r . ' sur 10</span><span aria-hidden="true" class="rating-max">/10</span></span>';
}

/** Choix de la note : pastilles 1 à 10 + « aucune » (boutons radio, utilisable sans JS). */
function rating_input(?int $current, string $legend = 'Note (facultatif)'): string
{
    $html = '<fieldset class="rating-input"><legend>' . e($legend) . '</legend><div class="rating-chips">';
    $html .= '<label class="chip"><input type="radio" name="rating" value=""' . (!$current ? ' checked' : '') . '><span>–<span class="sr">aucune</span></span></label>';
    for ($i = 1; $i <= 10; $i++) {
        $html .= '<label class="chip"><input type="radio" name="rating" value="' . $i . '"' . ($current === $i ? ' checked' : '')
            . '><span>' . $i . '<span class="sr"> sur 10</span></span></label>';
    }
    return $html . '</div></fieldset>';
}

/** URL de l'affiche d'une œuvre (ou null). */
function poster_url(array $w): ?string
{
    return !empty($w['has_poster']) ? url('poster', ['id' => $w['id']]) : null;
}

/**
 * Petite aide contextuelle « ? » (details/summary : clavier, lecteurs d'écran, tactile, sans JS).
 * $items : liste de phrases (HTML déjà échappé autorisé, à n'utiliser qu'avec du texte de l'application).
 */
function help_box(string $title, array $items): string
{
    $html = '<details class="help-box"><summary aria-label="Aide : ' . e($title) . '" title="Aide"><span aria-hidden="true">?</span></summary>'
        . '<div class="help-pop" role="note"><p class="help-title">' . e($title) . '</p><ul>';
    foreach (array_filter($items) as $it) {
        // Espaces insécables de la typographie française : « mot » et « : ; ? ! » ne se retrouvent pas seuls en début de ligne.
        $it = str_replace(['« ', ' »', ' :', ' ;', ' ?', ' !'], ["«\u{00A0}", "\u{00A0}»", "\u{00A0}:", "\u{00A0};", "\u{00A0}?", "\u{00A0}!"], $it);
        $html .= '<li>' . $it . '</li>';
    }
    return $html . '</ul></div></details>';
}
