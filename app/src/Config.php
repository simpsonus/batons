<?php
declare(strict_types=1);

namespace Batons;

/**
 * Configuration de l'application.
 *
 * Ordre de priorité : variables d'environnement BATONS_* > app/config.php > valeurs par défaut.
 * Rien ici ne doit dépendre d'un environnement de déploiement particulier (Docker, Home Assistant…).
 */
final class Config
{
    private static ?array $values = null;

    private const DEFAULTS = [
        'data_dir'        => null,          // calculé : app/data
        'db_dsn'          => null,          // calculé : sqlite:<data_dir>/batons.sqlite
        'db_user'         => null,
        'db_password'     => null,
        'auth'            => 'none',        // none | header  (builtin : à venir)
        'auth_header'     => 'Remote-User', // en mode header : en-tête portant l'identifiant
        'auth_name_header'=> '',            // en mode header : en-tête portant le nom affiché (optionnel)
        'trusted_proxies' => '',            // en mode header : IP autorisées, séparées par des virgules (vide = toutes)
        'timezone'        => null,          // ex. Europe/Paris ; par défaut celui de PHP / TZ
        'default_user'    => 'local',       // nom de l'utilisateur unique en mode none
        'hidden_timeout'  => 300,
        'rawg_key'        => '',            // clé API RAWG (jeux vidéo) par défaut
        'tmdb_key'        => '',            // clé API TMDB (films/séries) par défaut ; chaque utilisateur peut la sienne           // secondes d'inactivité avant reverrouillage des éléments masqués
    ];

    public static function get(string $key): mixed
    {
        if (self::$values === null) {
            self::load();
        }
        return self::$values[$key] ?? null;
    }

    private static function load(): void
    {
        $values = self::DEFAULTS;

        $file = dirname(__DIR__) . '/config.php';
        if (is_file($file)) {
            $fromFile = require $file;
            if (is_array($fromFile)) {
                $values = array_merge($values, $fromFile);
            }
        }

        foreach (array_keys(self::DEFAULTS) as $key) {
            $env = getenv('BATONS_' . strtoupper($key));
            if ($env !== false && $env !== '') {
                $values[$key] = $env;
            }
        }

        $values['data_dir'] ??= dirname(__DIR__) . '/data';
        $values['data_dir'] = rtrim($values['data_dir'], '/');
        $values['db_dsn'] ??= 'sqlite:' . $values['data_dir'] . '/batons.sqlite';
        $values['timezone'] ??= getenv('TZ') ?: date_default_timezone_get();

        self::$values = $values;
    }
}
