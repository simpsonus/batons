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
