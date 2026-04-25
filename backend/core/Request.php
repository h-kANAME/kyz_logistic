<?php
// =============================================================
// KYZ Logística – Request wrapper
// =============================================================

class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly array  $query;
    private array          $body;
    private array          $params = [];

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $uriPath       = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $scriptName    = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $scriptDir     = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        // En subcarpeta (ej. /logistica/backend/index.php) la URI incluye el prefijo; las rutas
        // registradas son /api/... — se recorta el directorio del script para que coincida en local y en prod.
        if ($scriptDir !== '' && str_starts_with($uriPath, $scriptDir)) {
            $uriPath = substr($uriPath, strlen($scriptDir)) ?: '/';
        }
        $this->path = '/' . trim($uriPath, '/');
        $this->query  = $_GET;
        $this->body   = $this->parseBody();
    }

    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?? [];
        }

        return $_POST ?? [];
    }

    /** Valor del body (POST/JSON), con default opcional */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** Valor de query string */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** Parámetros de ruta (ej: /jornadas/{id}) */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function all(): array
    {
        return $this->body;
    }

    /** Verifica que los campos requeridos existan y no estén vacíos */
    public function validate(array $required): ?string
    {
        foreach ($required as $field) {
            $val = $this->body[$field] ?? null;
            if ($val === null || $val === '') {
                return "El campo '{$field}' es requerido.";
            }
        }
        return null;
    }

    /** Devuelve el Bearer token del header Authorization */
    public function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '') {
            // Fallback para entornos donde PHP expone headers por getallheaders()
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
                if (is_array($headers)) {
                    $header = $headers['Authorization']
                        ?? $headers['authorization']
                        ?? '';
                }
            }
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }
}
