<?php
declare(strict_types=1);

namespace Batons;

/**
 * Passkeys (WebAuthn niveau 2) sans bibliothèque externe : Face ID, Touch ID, Windows Hello…
 *
 * Périmètre volontairement réduit à ce dont Bâtons a besoin :
 *  - authentificateur de l'appareil, vérification de l'utilisateur obligatoire (biométrie ou code de l'appareil) ;
 *  - attestation « none » (on ne vérifie pas le modèle d'appareil) ;
 *  - algorithmes ES256 (-7) et RS256 (-257), ceux d'Apple, Google et Microsoft.
 *
 * Le domaine (rpId) est celui de la page qui crée la passkey ; il est enregistré avec elle et
 * contrôlé à chaque utilisation. Les défis sont aléatoires, à usage unique et liés à la session.
 */
final class WebAuthn
{
    private const CHALLENGE_TTL = 300;

    // --- Options transmises au navigateur ------------------------------------------------

    public static function registrationOptions(array $user): array
    {
        $exclude = array_map(
            static fn($id) => ['type' => 'public-key', 'id' => $id],
            array_column(Db::all('SELECT credential_id FROM passkeys WHERE user_id = ?', [$user['id']]), 'credential_id')
        );
        return [
            'challenge' => self::newChallenge('create'),
            'rp' => ['name' => 'Bâtons'],
            'user' => [
                'id' => self::b64u(hash('sha256', 'batons-user-' . $user['id'], true)),
                'name' => $user['username'],
                'displayName' => $user['display_name'] ?: $user['username'],
            ],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'required',
                'residentKey' => 'discouraged',
            ],
            'attestation' => 'none',
            'timeout' => 60000,
            'excludeCredentials' => $exclude,
        ];
    }

    public static function authenticationOptions(int $userId): array
    {
        $allow = array_map(
            static fn($id) => ['type' => 'public-key', 'id' => $id],
            array_column(Db::all('SELECT credential_id FROM passkeys WHERE user_id = ?', [$userId]), 'credential_id')
        );
        return [
            'challenge' => self::newChallenge('get'),
            'allowCredentials' => $allow,
            'userVerification' => 'required',
            'timeout' => 60000,
        ];
    }

    // --- Vérifications -------------------------------------------------------------------------

    /** Vérifie une création de passkey et l'enregistre. Lève une exception en cas de problème. */
    public static function register(int $userId, array $in, string $name): void
    {
        $clientData = self::clientData($in, 'webauthn.create');
        $rpId = self::rpIdFromOrigin($clientData['origin']);

        $att = Cbor::decode(self::unb64u((string) ($in['attestationObject'] ?? '')));
        if (!is_array($att) || !isset($att['authData'])) {
            throw new \RuntimeException('Attestation illisible.');
        }
        $auth = self::parseAuthData($att['authData'], true);
        self::checkRpAndFlags($auth, $rpId);

        $credId = self::b64u($auth['credentialId']);
        if (Db::value('SELECT 1 FROM passkeys WHERE credential_id = ?', [$credId])) {
            throw new \RuntimeException('Cet appareil est déjà enregistré.');
        }
        Db::run(
            'INSERT INTO passkeys (user_id, credential_id, public_key, rp_id, sign_count, name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $credId, self::coseToPem($auth['publicKey']), $rpId, $auth['signCount'],
             mb_substr($name !== '' ? $name : 'Appareil', 0, 60), Time::nowUtc()]
        );
    }

    /** Vérifie une signature de passkey. Renvoie true si l'utilisateur est authentifié. */
    public static function verify(int $userId, array $in): bool
    {
        $clientData = self::clientData($in, 'webauthn.get');
        $credId = (string) ($in['id'] ?? '');
        $key = Db::one('SELECT * FROM passkeys WHERE credential_id = ? AND user_id = ?', [$credId, $userId]);
        if ($key === null) {
            throw new \RuntimeException('Appareil inconnu : enregistre-le d\'abord dans les réglages.');
        }
        if (self::rpIdFromOrigin($clientData['origin']) !== $key['rp_id']) {
            throw new \RuntimeException('Cette passkey a été créée pour une autre adresse (' . $key['rp_id'] . ').');
        }
        $authData = self::unb64u((string) ($in['authenticatorData'] ?? ''));
        $auth = self::parseAuthData($authData, false);
        self::checkRpAndFlags($auth, $key['rp_id']);

        $signed = $authData . hash('sha256', self::unb64u((string) $in['clientDataJSON']), true);
        $sig = self::unb64u((string) ($in['signature'] ?? ''));
        if (openssl_verify($signed, $sig, $key['public_key'], OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('Signature invalide.');
        }
        // Compteur : s'il est utilisé par l'appareil, il doit augmenter (détection de clonage).
        if ($auth['signCount'] > 0 || (int) $key['sign_count'] > 0) {
            if ($auth['signCount'] <= (int) $key['sign_count']) {
                throw new \RuntimeException('Compteur de signature incohérent.');
            }
        }
        Db::run('UPDATE passkeys SET sign_count = ?, last_used_at = ? WHERE id = ?', [$auth['signCount'], Time::nowUtc(), $key['id']]);
        return true;
    }

    // --- Outils internes ---------------------------------------------------------------------

    private static function newChallenge(string $type): string
    {
        $c = self::b64u(random_bytes(32));
        $_SESSION['webauthn'] = ['type' => $type, 'challenge' => $c, 'exp' => time() + self::CHALLENGE_TTL];
        return $c;
    }

    /** Décode et contrôle clientDataJSON (type, défi à usage unique, origine sûre). */
    private static function clientData(array $in, string $type): array
    {
        $expected = $_SESSION['webauthn'] ?? null;
        unset($_SESSION['webauthn']); // usage unique
        $cd = json_decode(self::unb64u((string) ($in['clientDataJSON'] ?? '')), true);
        if (!is_array($cd) || ($cd['type'] ?? '') !== $type) {
            throw new \RuntimeException('Réponse inattendue du navigateur.');
        }
        if (!is_array($expected) || $expected['exp'] < time() || $expected['type'] !== ($type === 'webauthn.create' ? 'create' : 'get')
            || !hash_equals($expected['challenge'], (string) ($cd['challenge'] ?? ''))) {
            throw new \RuntimeException('Défi expiré ou invalide, réessaie.');
        }
        $origin = (string) ($cd['origin'] ?? '');
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = (string) parse_url($origin, PHP_URL_HOST);
        if (!($scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)))) {
            throw new \RuntimeException('Face ID / Touch ID demande une adresse en https.');
        }
        if (!empty($cd['crossOrigin'])) {
            throw new \RuntimeException('Utilisation depuis un autre site refusée.');
        }
        return $cd;
    }

    private static function rpIdFromOrigin(string $origin): string
    {
        return strtolower((string) parse_url($origin, PHP_URL_HOST));
    }

    private static function checkRpAndFlags(array $auth, string $rpId): void
    {
        if (!hash_equals(hash('sha256', $rpId, true), $auth['rpIdHash'])) {
            throw new \RuntimeException('Domaine incohérent.');
        }
        if (!($auth['flags'] & 0x01) || !($auth['flags'] & 0x04)) {
            throw new \RuntimeException('La vérification de l\'utilisateur (biométrie ou code de l\'appareil) est requise.');
        }
    }

    /** authenticatorData : rpIdHash(32) | flags(1) | signCount(4) | [aaguid(16) | idLen(2) | credId | clé COSE] */
    private static function parseAuthData(string $d, bool $withCredential): array
    {
        if (strlen($d) < 37) {
            throw new \RuntimeException('Données d\'authentification trop courtes.');
        }
        $out = [
            'rpIdHash' => substr($d, 0, 32),
            'flags' => ord($d[32]),
            'signCount' => unpack('N', substr($d, 33, 4))[1],
        ];
        if ($withCredential) {
            if (!($out['flags'] & 0x40) || strlen($d) < 55) {
                throw new \RuntimeException('Aucune clé dans la réponse.');
            }
            $len = unpack('n', substr($d, 53, 2))[1];
            $out['credentialId'] = substr($d, 55, $len);
            $offset = 55 + $len;
            $out['publicKey'] = Cbor::decode(substr($d, $offset));
        }
        return $out;
    }

    /** Clé publique COSE (EC2 P-256 ou RSA) -> PEM SPKI utilisable par openssl_verify. */
    private static function coseToPem(mixed $k): string
    {
        if (!is_array($k)) {
            throw new \RuntimeException('Clé publique illisible.');
        }
        $kty = $k[1] ?? null;
        $alg = $k[3] ?? null;
        if ($kty === 2 && $alg === -7 && ($k[-1] ?? null) === 1) {
            $x = $k[-2] ?? ''; $y = $k[-3] ?? '';
            if (strlen($x) !== 32 || strlen($y) !== 32) {
                throw new \RuntimeException('Clé EC invalide.');
            }
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
        } elseif ($kty === 3 && $alg === -257) {
            $n = ltrim((string) ($k[-1] ?? ''), "\0"); $e = ltrim((string) ($k[-2] ?? ''), "\0");
            $int = static function (string $v): string {
                if ($v === '' || (ord($v[0]) & 0x80)) {
                    $v = "\0" . $v; // entier DER positif
                }
                return "\x02" . self::derLen(strlen($v)) . $v;
            };
            $rsaKey = $int($n) . $int($e);
            $rsaKey = "\x30" . self::derLen(strlen($rsaKey)) . $rsaKey;
            $bits = "\x03" . self::derLen(strlen($rsaKey) + 1) . "\0" . $rsaKey;
            $algo = hex2bin('300d06092a864886f70d0101010500');
            $der = "\x30" . self::derLen(strlen($algo) + strlen($bits)) . $algo . $bits;
        } else {
            throw new \RuntimeException('Type de clé non pris en charge.');
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        if (openssl_pkey_get_public($pem) === false) {
            throw new \RuntimeException('Clé publique refusée par OpenSSL.');
        }
        return $pem;
    }

    private static function derLen(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        $bytes = ltrim(pack('N', $len), "\0");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64u(string $s): string
    {
        $bin = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        if ($bin === false) {
            throw new \RuntimeException('Encodage invalide.');
        }
        return $bin;
    }
}
