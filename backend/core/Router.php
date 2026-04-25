<?php
// =============================================================
// KYZ Logística – Router simple (front-controller)
// Soporta rutas estáticas y con parámetros: /recurso/{id}
// =============================================================

class Router
{
    private array $routes = [];

    // ── Registro de rutas ────────────────────────────────────

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, callable $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    // ── Despacho ─────────────────────────────────────────────

    public function dispatch(Request $request): void
    {
        $method      = $request->method;
        $requestPath = $request->path;

        // Soporte para method override via _method en formularios
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['path'], $requestPath);
            if ($params === null) {
                continue;
            }

            $request->setParams($params);

            // Ejecutar middleware en cadena
            foreach ($route['middleware'] as $mw) {
                $mw($request);
            }

            // Invocar el handler
            ($route['handler'])($request);
            return;
        }

        Response::notFound('Ruta no encontrada');
    }

    /**
     * Compara la ruta registrada con el path real.
     * Retorna array de parámetros capturados, o null si no coincide.
     */
    private function match(string $routePath, string $requestPath): ?array
    {
        // Eliminar prefijo /api si existe en ambos (o sólo en requestPath)
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        // Extraer nombres de parámetros
        preg_match_all('/\{([a-zA-Z_]+)\}/', $routePath, $names);
        $params = [];
        foreach ($names[1] as $i => $name) {
            $params[$name] = $matches[$i + 1];
        }

        return $params;
    }
}
