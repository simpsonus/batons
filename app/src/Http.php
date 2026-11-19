<?php
declare(strict_types=1);

namespace Batons;

/**
 * Petits utilitaires HTTP.
 *
 * Règle d'or : toutes les URLs sont RELATIVES (« ./?r=… », « assets/… »).
 * Toute l'application est servie par un seul index.php, routé par le paramètre « r ».
 * Ainsi elle fonctionne telle quelle à la racine d'un domaine, dans un sous-dossier,
 * derrière un reverse proxy ou sous Home Assistant Ingress, sans réécriture d'URL.
 */
final class Http
{
    /** URL relative vers une route : url('category', ['id' => 3]) -> './?r=category&id=3' */
    public static function url(string $route = 'home', array $params = []): string
    {
        $q = $route === 'home' ? $params : ['r' => $route] + $params;
        return './' . ($q ? '?' . http_build_query($q) : '');
    }

    public static function redirect(string $route = 'home', array $params = []): never
    {
        header('Location: ' . self::url($route, $params), true, 303);
        exit;
    }

    /** Redirige vers la page d'origine si elle a été transmise par le formulaire (champ « back »). */
    public static function back(string $fallbackRoute = 'home', array $params = []): never
    {
        $back = (string) ($_POST['back'] ?? '');
        if ($back !== '' && str_starts_with($back, './') && !str_contains($back, '//')) {
            header('Location: ' . $back, true, 303);
            exit;
        }
        self::redirect($fallbackRoute, $params);
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function input(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function intInput(string $key): ?int
    {
        $v = self::input($key);
        return ctype_digit($v) ? (int) $v : null;
    }

    // --- Session, CSRF, messages flash ---------------------------------------

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('batons_sid');
        // Sessions stockées avec les données (survivent aux redémarrages), valables 30 jours.
        $dir = Config::get('data_dir') . '/sessions';
        if (is_dir($dir) || @mkdir($dir, 0700, true)) {
            session_save_path($dir);
        }
        ini_set('session.gc_maxlifetime', (string) (30 * 86400));
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 30 * 86400,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        ]);
        session_start();
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf" value="' . self::csrfToken() . '">';
    }

    public static function checkCsrf(): void
    {
        if (!hash_equals(self::csrfToken(), (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            exit('Jeton de sécurité invalide. Recharge la page et réessaie.');
        }
    }

    /** Message flash ; $undo = id d'un bâton qu'on peut annuler. */
    public static function flash(string $message, ?int $undo = null): void
    {
        $_SESSION['flash'] = ['message' => $message, 'undo' => $undo];
    }

    public static function takeFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }

    // --- Rendu -----------------------------------------------------------------

    public static function render(string $view, array $vars = [], string $title = 'Bâtons'): never
    {
        $vars['title'] = $title;
        $vars['flash'] = self::takeFlash();
        extract($vars, EXTR_SKIP);
        $viewDir = dirname(__DIR__) . '/views';
        ob_start();
        require "$viewDir/$view.php";
        $content = ob_get_clean();
        require "$viewDir/layout.php";
        exit;
    }

    public static function notFound(string $what = 'Page'): never
    {
        http_response_code(404);
        self::render('error', ['message' => "$what introuvable."], 'Introuvable');
    }
}
