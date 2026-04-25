<?php
// =============================================================
// KYZ Logística – Middleware de autenticación y autorización
// =============================================================

class AuthMiddleware
{
    /**
     * Verifica JWT y adjunta el payload al request.
     * Llama a Response::unauthorized() si el token es inválido.
     */
    public static function auth(Request $request): void
    {
        $token = $request->bearerToken();

        if ($token === null) {
            Response::unauthorized('Token no proporcionado.');
        }

        $payload = JWT::decode($token);

        if ($payload === null) {
            Response::unauthorized('Token inválido o expirado.');
        }

        // Hacemos disponibles sub/rol como atributos de request
        // usando un mecanismo de almacenamiento interno
        AuthContext::set($payload);
    }

    /**
     * Requiere que el usuario autenticado tenga uno de los roles indicados.
     * Se usa después de ::auth().
     */
    public static function role(string ...$roles): callable
    {
        return function (Request $request) use ($roles): void {
            $auth = AuthContext::get();

            if ($auth === null) {
                Response::unauthorized();
            }

            if (!in_array($auth['rol'], $roles, true)) {
                Response::forbidden('No tienes permiso para realizar esta acción.');
            }
        };
    }

    /**
     * Middleware combinado: auth + verificación de rol.
     * Uso: AuthMiddleware::requires('admin', 'supervisor')
     */
    public static function requires(string ...$roles): callable
    {
        return function (Request $request) use ($roles): void {
            self::auth($request);
            $auth = AuthContext::get();

            if (!in_array($auth['rol'], $roles, true)) {
                Response::forbidden('No tienes permiso para realizar esta acción.');
            }
        };
    }
}

// =============================================================
// AuthContext – almacén de sesión del usuario autenticado
// (alternativa simple a inyección de dependencias)
// =============================================================

class AuthContext
{
    private static ?array $payload = null;

    public static function set(array $payload): void
    {
        self::$payload = $payload;
    }

    public static function get(): ?array
    {
        return self::$payload;
    }

    public static function id(): ?int
    {
        return isset(self::$payload['sub']) ? (int)self::$payload['sub'] : null;
    }

    public static function rol(): ?string
    {
        return self::$payload['rol'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(self::$payload['rol'] ?? '', $roles, true);
    }
}
