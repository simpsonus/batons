<?php
declare(strict_types=1);

/*
 * Routes — Éléments masqués : déverrouillage, code, passkeys (Face ID / Touch ID).
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
    // --- Éléments masqués : déverrouillage, code, passkeys ---------------------------
    case 'unlock':
        if (Privacy::isUnlocked()) {
            Http::redirect();
        }
        $error = null;
        if (Http::isPost()) {
            $res = Privacy::checkPin($uid, Http::input('pin'));
            if ($res === true) {
                Privacy::unlock();
                Http::flash('Éléments masqués visibles. Ils se reverrouillent après ' . intdiv(Privacy::timeout(), 60) . ' min d\'inactivité.');
                Http::redirect();
            }
            $error = $res;
        }
        Http::render('unlock', [
            'hasPin'      => Privacy::hasPin($uid),
            'hasPasskeys' => (bool) Batons\Db::value('SELECT 1 FROM passkeys WHERE user_id = ?', [$uid]),
            'blocked'     => Privacy::blockedFor($uid),
            'error'       => $error,
            'pinErrors'   => [],
        ], 'Éléments masqués');

    case 'pin_save':
        // Première définition du code (verrouillé, aucun code) ou changement (déverrouillé).
        $first = !Privacy::hasPin($uid);
        if (!$first && !Privacy::isUnlocked()) {
            Http::redirect('unlock');
        }
        $pin = Http::input('pin');
        $errs = [];
        if (!Privacy::validPinFormat($pin)) {
            $errs['pin'] = 'Le code doit faire exactement ' . Privacy::PIN_LENGTH . ' chiffres.';
        } elseif ($pin !== Http::input('pin2')) {
            $errs['pin2'] = 'Les deux saisies ne correspondent pas.';
        }
        if ($errs) {
            if ($first) {
                Http::render('unlock', ['hasPin' => false, 'hasPasskeys' => false, 'blocked' => 0, 'error' => null, 'pinErrors' => $errs], 'Éléments masqués');
            }
            Http::render('privacy', Privacy::settings($uid) + ['pinErrors' => $errs], 'Confidentialité');
        }
        Privacy::setPin($uid, $pin);
        Privacy::unlock();
        Http::flash($first ? 'Code enregistré. Tu peux maintenant masquer des catégories et des groupes.' : 'Code modifié.');
        Http::redirect($first ? 'privacy' : 'privacy');

    case 'lock':
        Privacy::lock();
        Http::flash('Éléments masqués verrouillés.');
        Http::redirect();

    case 'privacy':
        if (!Privacy::isUnlocked()) {
            Http::redirect('unlock');
        }
        Http::render('privacy', Privacy::settings($uid) + ['pinErrors' => []], 'Confidentialité');

    case 'passkey_options':
        header('Content-Type: application/json; charset=utf-8');
        $mode = Http::input('mode');
        if ($mode === 'register') {
            if (!Privacy::isUnlocked()) {
                http_response_code(403);
                exit(json_encode(['error' => 'Déverrouille d\'abord avec ton code.']));
            }
            exit(json_encode(WebAuthn::registrationOptions(Auth::user()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        if (Privacy::blockedFor($uid) > 0) {
            http_response_code(429);
            exit(json_encode(['error' => 'Déverrouillage bloqué temporairement.']));
        }
        exit(json_encode(WebAuthn::authenticationOptions($uid), JSON_UNESCAPED_SLASHES));

    case 'passkey_register':
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (!Privacy::isUnlocked()) {
                throw new RuntimeException('Déverrouille d\'abord avec ton code.');
            }
            WebAuthn::register($uid, $_POST, Http::input('name'));
            Http::flash('Appareil enregistré : tu peux maintenant déverrouiller avec Face ID / Touch ID.');
            exit(json_encode(['ok' => true]));
        } catch (Throwable $e) {
            http_response_code(400);
            exit(json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }

    case 'passkey_unlock':
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (Privacy::blockedFor($uid) > 0) {
                throw new RuntimeException('Déverrouillage bloqué temporairement.');
            }
            WebAuthn::verify($uid, $_POST);
            Privacy::unlock();
            Http::flash('Éléments masqués visibles. Ils se reverrouillent après ' . intdiv(Privacy::timeout(), 60) . ' min d\'inactivité.');
            exit(json_encode(['ok' => true]));
        } catch (Throwable $e) {
            http_response_code(400);
            exit(json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }

    case 'passkey_delete':
        if (!Privacy::isUnlocked()) {
            Http::redirect('unlock');
        }
        Batons\Db::run('DELETE FROM passkeys WHERE id = ? AND user_id = ?', [Http::intInput('id'), $uid]);
        Http::flash('Appareil retiré.');
        Http::redirect('privacy');
}
