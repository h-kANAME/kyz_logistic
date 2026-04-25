<?php
// =============================================================
// KYZ Logística – Response helper
// =============================================================

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): void
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        self::json($body, $status);
    }

    public static function unauthorized(string $message = 'No autorizado'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acceso denegado'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 404);
    }

    /**
     * Respuesta binaria (descarga). Cierra la petición sin JSON.
     */
    public static function download(string $body, string $filename, string $contentType): void
    {
        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . (string)strlen($body));
        header('Cache-Control: no-store');
        echo $body;
        exit;
    }
}
