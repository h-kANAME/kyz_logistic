<?php
// =============================================================
// KYZ Logística – Entry point (front-controller)
// Todas las peticiones pasan por aquí vía .htaccess
// =============================================================

declare(strict_types=1);

// ── Cabeceras CORS y seguridad ────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Carga de configuración ────────────────────────────────────
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';

// ── Núcleo HTTP (require explícito; en Linux el autoload falla si falta core/ o el nombre no coincide)
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Router.php';

// ── Autoload manual (sin Composer) ───────────────────────────
spl_autoload_register(function (string $class): void {
    $dirs = [
        __DIR__ . '/core/',
        __DIR__ . '/helpers/',
        __DIR__ . '/services/',
        __DIR__ . '/middleware/',
        __DIR__ . '/models/',
        __DIR__ . '/controllers/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (is_readable($file)) {
            require_once $file;
            return;
        }
    }
});

// ── Manejo global de errores ──────────────────────────────────
set_exception_handler(function (Throwable $e): void {
    $status  = ($e instanceof PDOException) ? 503 : 500;
    $message = (APP_ENV === 'development')
        ? $e->getMessage()
        : 'Error interno del servidor.';
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
});

// ── Instancias compartidas ────────────────────────────────────
$request = new Request();
$router  = new Router();

// ── Shortcuts de middleware ───────────────────────────────────
$auth      = [fn($r) => AuthMiddleware::auth($r)];
$adminOnly = [AuthMiddleware::requires('admin')];
$supAdmin  = [AuthMiddleware::requires('admin', 'supervisor')];
$allRoles  = [AuthMiddleware::requires('admin', 'supervisor', 'consultor')];

// ── Rutas ─────────────────────────────────────────────────────

// Auth
$router->post('/api/auth/login', [new AuthController(), 'login']);
$router->get('/api/auth/me',     [new AuthController(), 'me'],    $allRoles);

// Perfil operativo consultor
$router->get('/api/consultor/perfil', [new ConsultorController(), 'getPerfil'], $allRoles);
$router->put('/api/consultor/perfil', [new ConsultorController(), 'updatePerfil'], $allRoles);

// Usuarios
$router->get('/api/usuarios',                    [new UsuarioController(), 'index'],          $supAdmin);
$router->post('/api/usuarios',                   [new UsuarioController(), 'store'],          $adminOnly);
$router->get('/api/usuarios/{id}',               [new UsuarioController(), 'show'],           $allRoles);
$router->put('/api/usuarios/{id}',               [new UsuarioController(), 'update'],         $allRoles);
$router->delete('/api/usuarios/{id}',            [new UsuarioController(), 'destroy'],        $adminOnly);
$router->patch('/api/usuarios/{id}/password',    [new UsuarioController(), 'updatePassword'], $allRoles);

// Secciones
$router->get('/api/secciones',       [new SeccionController(), 'index'],  $allRoles);
$router->get('/api/secciones/{id}',  [new SeccionController(), 'show'],   $allRoles);
$router->put('/api/secciones/{id}',  [new SeccionController(), 'update'], $adminOnly);

// Domicilios
$router->get('/api/domicilios',       [new DomicilioController(), 'index'], $allRoles);
$router->get('/api/domicilios/{id}',  [new DomicilioController(), 'show'],  $allRoles);

// Jornadas
$router->get('/api/jornadas',                         [new JornadaController(), 'index'],          $allRoles);
$router->post('/api/jornadas',                        [new JornadaController(), 'store'],          $supAdmin);
$router->get('/api/jornadas/{id}',                    [new JornadaController(), 'show'],           $allRoles);
$router->put('/api/jornadas/{id}',                    [new JornadaController(), 'update'],         $supAdmin);
$router->delete('/api/jornadas/{id}',                 [new JornadaController(), 'destroy'],        $supAdmin);
$router->patch('/api/jornadas/{id}/estado',           [new JornadaController(), 'updateEstado'],   $supAdmin);
$router->get('/api/jornadas/{id}/asignaciones',       [new JornadaController(), 'getAsignaciones'],$allRoles);
$router->get('/api/jornadas/{id}/asignaciones/paginadas', [new JornadaController(), 'getAsignacionesPaginadas'], $allRoles);
$router->post('/api/jornadas/{id}/asignaciones',      [new JornadaController(), 'generarRuta'],    $supAdmin);
$router->post('/api/jornadas/{id}/plan-dia',          [new JornadaController(), 'planDia'],        $allRoles);
$router->post('/api/jornadas/{id}/hojas-ruta',        [new HojaRutaController(), 'storeFromPlan'], $allRoles);

// Hojas de ruta persistidas
$router->get('/api/hojas-ruta',                       [new HojaRutaController(), 'index'],         $allRoles);
$router->get('/api/hojas-ruta/{id}',                  [new HojaRutaController(), 'show'],          $allRoles);
$router->put('/api/hojas-ruta/{id}',                  [new HojaRutaController(), 'update'],        $allRoles);
$router->delete('/api/hojas-ruta/{id}',               [new HojaRutaController(), 'destroy'],       $allRoles);
$router->post('/api/hojas-ruta/{id}/open-next-batch', [new HojaRutaController(), 'openNextBatch'], $allRoles);
$router->post('/api/hojas-ruta/{id}/visitas',         [new HojaRutaController(), 'registrarVisita'], $allRoles);
$router->get('/api/hojas-ruta/historial/domicilio/{domicilio_id}', [new HojaRutaController(), 'historialDomicilio'], $allRoles);

// Lotes mensuales
$router->get('/api/lotes',                             [new LotesController(), 'index'],            $supAdmin);
$router->post('/api/lotes',                            [new LotesController(), 'store'],            $adminOnly);
$router->put('/api/lotes/{id}/domicilios',             [new LotesController(), 'setDomicilios'],    $adminOnly);
$router->post('/api/lotes/{id}/bootstrap-domicilios',  [new LotesController(), 'bootstrapFromDomicilios'], $adminOnly);
$router->post('/api/lotes/{id}/asignar',               [new LotesController(), 'assignToConsultor'], $supAdmin);
$router->get('/api/consultor/lotes',                   [new LotesController(), 'myLotes'],          $allRoles);
$router->post('/api/consultor/lotes/{id}/plan-dia',    [new LotesController(), 'planLoteDia'],      $allRoles);
$router->post('/api/consultor/lotes/{id}/hojas-ruta',  [new HojaRutaController(), 'storeFromLote'], $allRoles);

// Asignaciones
$router->get('/api/asignaciones/{id}',   [new AsignacionController(), 'show'],   $allRoles);
$router->patch('/api/asignaciones/{id}', [new AsignacionController(), 'update'], $allRoles);

// Import
$router->post('/api/import/domicilios', [new ImportController(), 'domicilios'], $adminOnly);

// Export
$router->get('/api/export/domicilios/xlsx', [new ExportController(), 'domiciliosXlsx'], $adminOnly);

// Geocoding
$router->get('/api/geocoding/domicilios/estado', [new GeocodingController(), 'estado'], $adminOnly);
$router->post('/api/geocoding/domicilios/lote', [new GeocodingController(), 'lote'], $adminOnly);
$router->post('/api/geocoding/domicilios/fallback', [new GeocodingController(), 'fallback'], $adminOnly);
$router->post('/api/geocoding/domicilios/reset', [new GeocodingController(), 'reset'], $adminOnly);
$router->get('/api/geocoding/domicilios/wizard', [new GeocodingController(), 'wizardQueue'], $adminOnly);
$router->post('/api/geocoding/domicilios/wizard/bulk-propose', [new GeocodingController(), 'wizardBulkPropose'], $adminOnly);
$router->post('/api/geocoding/domicilios/wizard/bulk-save', [new GeocodingController(), 'wizardBulkSave'], $adminOnly);
$router->post('/api/geocoding/domicilios/wizard/{id}/attempt', [new GeocodingController(), 'wizardAttempt'], $adminOnly);
$router->post('/api/geocoding/domicilios/wizard/{id}/manual', [new GeocodingController(), 'wizardManual'], $adminOnly);
$router->post('/api/geocoding/domicilios/wizard/{id}/fallback', [new GeocodingController(), 'wizardFallback'], $adminOnly);

// LLM (DeepSeek)
$router->post('/api/llm/jornadas/{id}/priorizar', [new LlmController(), 'priorizarJornada'], $supAdmin);

// ── Despacho ──────────────────────────────────────────────────
$router->dispatch($request);
