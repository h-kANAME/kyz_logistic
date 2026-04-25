<?php
// =============================================================
// KYZ Logística – JWT puro en PHP (sin librerías externas)
// Algoritmo: HMAC-SHA256
// =============================================================

class JWT
{
    // ── Codificación ─────────────────────────────────────────

    public static function encode(array $payload): string
    {
        $header    = self::b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload   = self::b64u(json_encode($payload));
        $signature = self::b64u(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

        return "$header.$payload.$signature";
    }

    // ── Decodificación ───────────────────────────────────────

    /**
     * Retorna el payload si el token es válido y no expiró.
     * Retorna null si la firma es inválida o el token expiró.
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $sig] = $parts;

        $expected = self::b64u(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

        // Comparación segura en tiempo constante (previene timing attacks)
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $data = json_decode(self::b64uDecode($payload), true);

        if (!is_array($data)) {
            return null;
        }

        // Verificar expiración
        if (isset($data['exp']) && $data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    // ── Creación de token de acceso ──────────────────────────

    public static function createToken(int $userId, string $rol, string $nombre): string
    {
        return self::encode([
            'sub'    => $userId,
            'rol'    => $rol,
            'nombre' => $nombre,
            'iat'    => time(),
            'exp'    => time() + JWT_EXPIRY,
        ]);
    }

    // ── Helpers base64url ────────────────────────────────────

    private static function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64uDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
