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

/** HTML d'une icône de catégorie (émoji ou « mdi:nom »). */
function icon(?string $icon): string
{
    return Batons\Icons::html($icon);
}

/** Petites icônes d'interface (Material Design Icons, Apache 2.0), indépendantes de la table MDI. */
function ui_icon(string $name): string
{
    static $paths = [
        'pencil' => 'M14.06,9L15,9.94L5.92,19H5V18.08L14.06,9M17.66,3C17.41,3 17.15,3.1 16.96,3.29L15.13,5.12L18.88,8.87L20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18.17,3.09 17.92,3 17.66,3M14.06,6.19L3,17.25V21H6.75L17.81,9.94L14.06,6.19Z',
        'trash'  => 'M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M7,6H17V19H7V6M9,8V17H11V8H9M13,8V17H15V8H13Z',
        'close'  => 'M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z',
        'chevron'=> 'M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z',
        'up'     => 'M7.41,15.41L12,10.83L16.59,15.41L18,14L12,8L6,14L7.41,15.41Z',
        'down'   => 'M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z',
    ];
    return '<svg class="ui" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . $paths[$name] . '"/></svg>';
}
