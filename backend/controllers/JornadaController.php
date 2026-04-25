<?php
// =============================================================
// KYZ Logística – JornadaController
// GET   /api/jornadas
// POST  /api/jornadas
// GET   /api/jornadas/{id}
// PUT   /api/jornadas/{id}
// DELETE /api/jornadas/{id}
// PATCH /api/jornadas/{id}/estado
// POST  /api/jornadas/{id}/asignaciones   (asignación masiva)
// GET   /api/jornadas/{id}/asignaciones
// GET   /api/jornadas/{id}/asignaciones/paginadas
// POST  /api/jornadas/{id}/plan-dia
// =============================================================

class JornadaController
{
    private Jornada    $jornadaModel;
    private Asignacion $asignacionModel;
    private Domicilio  $domicilioModel;

    public function __construct()
    {
        $this->jornadaModel    = new Jornada();
        $this->asignacionModel = new Asignacion();
        $this->domicilioModel  = new Domicilio();
    }

    /** GET /api/jornadas */
    public function index(Request $request): void
    {
        $auth    = AuthContext::get();
        $filters = [];

        switch ($auth['rol']) {
            case 'consultor':
                $filters['consultor_id'] = $auth['sub'];
                break;
            case 'supervisor':
                $filters['supervisor_id'] = $auth['sub'];
                break;
            // admin ve todo, sin filtro de base
        }

        // Filtros adicionales opcionales
        if ($f = $request->query('fecha')) {
            $filters['fecha'] = $f;
        }
        if ($e = $request->query('estado')) {
            $filters['estado'] = $e;
        }

        Response::success($this->jornadaModel->findAll($filters));
    }

    /** POST /api/jornadas  –  Supervisor / Admin */
    public function store(Request $request): void
    {
        $error = $request->validate(['consultor_id', 'fecha']);
        if ($error) {
            Response::error($error, 422);
        }

        $consultorId  = (int)$request->input('consultor_id');
        $supervisorId = AuthContext::id();
        $fecha        = (string)$request->input('fecha');

        // Validar formato fecha YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            Response::error('Formato de fecha inválido. Use YYYY-MM-DD.', 422);
        }

        if ($this->jornadaModel->existeParaConsultorFecha($consultorId, $fecha)) {
            Response::error('Ya existe una jornada para ese consultor en esa fecha.', 409);
        }

        $id = $this->jornadaModel->create($consultorId, $supervisorId, $fecha);
        Response::success(['id' => $id], 'Jornada creada', 201);
    }

    /** GET /api/jornadas/{id} */
    public function show(Request $request): void
    {
        $id      = (int)$request->param('id');
        $jornada = $this->jornadaModel->findById($id);

        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->checkAccess($jornada);
        Response::success($jornada);
    }

    /** PATCH /api/jornadas/{id}/estado  –  Supervisor / Admin */
    public function updateEstado(Request $request): void
    {
        $id     = (int)$request->param('id');
        $estado = (string)$request->input('estado', '');

        $validos = ['borrador', 'activa', 'completada', 'cancelada'];
        if (!in_array($estado, $validos, true)) {
            Response::error('Estado inválido. Valores: ' . implode(', ', $validos), 422);
        }

        $jornada = $this->jornadaModel->findById($id);
        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->jornadaModel->updateEstado($id, $estado);
        Response::success(null, 'Estado actualizado');
    }

    /** PUT /api/jornadas/{id}  –  Supervisor / Admin */
    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $error = $request->validate(['consultor_id', 'fecha']);
        if ($error) {
            Response::error($error, 422);
        }

        $jornada = $this->jornadaModel->findById($id);
        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->checkAccess($jornada);

        $consultorId = (int)$request->input('consultor_id');
        $fecha = (string)$request->input('fecha');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            Response::error('Formato de fecha inválido. Use YYYY-MM-DD.', 422);
        }

        if ($this->jornadaModel->existeParaConsultorFechaExcepto($consultorId, $fecha, $id)) {
            Response::error('Ya existe otra jornada para ese consultor en esa fecha.', 409);
        }

        $this->jornadaModel->update($id, $consultorId, $fecha);
        Response::success(null, 'Jornada actualizada');
    }

    /** DELETE /api/jornadas/{id}  –  Supervisor / Admin */
    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        $jornada = $this->jornadaModel->findById($id);

        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->checkAccess($jornada);

        // Limpiar dependencias antes de borrar la jornada.
        $this->asignacionModel->deleteByJornada($id);
        $this->jornadaModel->delete($id);

        Response::success(null, 'Jornada eliminada');
    }

    // ── Asignaciones ─────────────────────────────────────────

    /**
     * GET /api/jornadas/{id}/asignaciones
     * Devuelve la lista ordenada de visitas de la jornada.
     */
    public function getAsignaciones(Request $request): void
    {
        $id      = (int)$request->param('id');
        $jornada = $this->jornadaModel->findById($id);

        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->checkAccess($jornada);
        $asignaciones = $this->asignacionModel->findByJornada($id);
        Response::success($asignaciones);
    }

    /** GET /api/jornadas/{id}/asignaciones/paginadas */
    public function getAsignacionesPaginadas(Request $request): void
    {
        $id      = (int)$request->param('id');
        $jornada = $this->jornadaModel->findById($id);

        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->checkAccess($jornada);

        $perPage = max(1, min(100, (int)$request->query('per_page', 10)));
        $page = max(1, (int)$request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $total = $this->asignacionModel->countByJornada($id);
        $items = $this->asignacionModel->findByJornadaPaginated($id, $perPage, $offset);

        Response::success([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int)ceil($total / $perPage),
        ]);
    }

    /** POST /api/jornadas/{id}/plan-dia */
    public function planDia(Request $request): void
    {
        $id = (int)$request->param('id');
        $jornada = $this->jornadaModel->findById($id);

        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->checkAccess($jornada);

        $perfilModel = new ConsultorPerfil();
        $perfil = $perfilModel->findByUsuarioId((int)$jornada['consultor_id']);

        // Normalizar retorno para cubrir casos de string vacio enviado por cliente.
        $requestRetornoDireccion = trim((string)$request->input('punto_retorno_direccion', ''));
        $fallbackRetornoDireccion = $perfil['punto_retorno_direccion'] ?? $perfil['punto_partida_direccion'];
        $retornoDireccion = $requestRetornoDireccion !== '' ? $requestRetornoDireccion : $fallbackRetornoDireccion;

        // Permitir override puntual desde UI para simulaciones del dia.
        $override = [
            'punto_partida_direccion' => $request->input('punto_partida_direccion', $perfil['punto_partida_direccion']),
            'punto_partida_latitud' => $request->input('punto_partida_latitud', $perfil['punto_partida_latitud']),
            'punto_partida_longitud' => $request->input('punto_partida_longitud', $perfil['punto_partida_longitud']),
            'punto_retorno_direccion' => $retornoDireccion,
            'punto_retorno_latitud' => $request->input('punto_retorno_latitud', $perfil['punto_retorno_latitud'] ?? $perfil['punto_partida_latitud']),
            'punto_retorno_longitud' => $request->input('punto_retorno_longitud', $perfil['punto_retorno_longitud'] ?? $perfil['punto_partida_longitud']),
            'movilidad' => $request->input('movilidad', $perfil['movilidad']),
            'disponibilidad_minutos' => (int)$request->input('disponibilidad_minutos', $perfil['disponibilidad_minutos']),
        ];

        $options = [
            'excluded_asignacion_ids' => array_map('intval', (array)$request->input('excluded_asignacion_ids', [])),
            'forced_first_asignacion_id' => $request->input('forced_first_asignacion_id'),
            'consider_operational_time' => (bool)$request->input('consider_operational_time', false),
            'operational_margin_minutes' => max(0, (int)$request->input('operational_margin_minutes', 0)),
        ];

        $asignaciones = $this->asignacionModel->findByJornada($id);
        $planner = new DayRoutePlannerService();
        $plan = $planner->plan($asignaciones, $override, $options);

        Response::success([
            'jornada_id' => $id,
            'consultor_id' => (int)$jornada['consultor_id'],
            'perfil' => $override,
            'constraints' => $options,
            'plan' => $plan,
        ], 'Hoja de ruta diaria generada.');
    }

    /**
     * POST /api/jornadas/{id}/asignaciones
     * Genera y asigna la ruta óptima a la jornada.
     *
     * Body: { domicilio_ids: [1, 2, 3, ...] }  — si se pasa vacío, toma todos los de la sección.
    * Algoritmo V2: greedy multicriterio (distancia real + prioridad de servicio + continuidad de sección).
     */
    public function generarRuta(Request $request): void
    {
        $id      = (int)$request->param('id');
        $jornada = $this->jornadaModel->findById($id);

        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        if ($jornada['estado'] !== 'borrador') {
            Response::error('Solo se puede generar la ruta en jornadas en estado borrador.', 409);
        }

        $domicilioIds = $request->input('domicilio_ids', []);

        // Si no se especifican IDs, tomar los parámetros de filtro
        if (empty($domicilioIds)) {
            $filters = [
                'seccion_id' => $request->input('seccion_id'),
                'servicio'   => $request->input('servicio'),
            ];
            $domicilios = $this->domicilioModel->findAll(
                array_filter($filters, fn($v) => $v !== null)
            );
        } else {
            // Validar que sean IDs numéricos
            $domicilioIds = array_map('intval', (array)$domicilioIds);
            $domicilios   = array_filter(
                array_map(fn($did) => $this->domicilioModel->findById($did), $domicilioIds)
            );
        }

        if (empty($domicilios)) {
            Response::error('No hay domicilios para asignar.', 400);
        }

        $meta = [
            'algorithm' => 'route_v1_section_street_height',
            'weights' => null,
        ];

        if (ROUTE_V2_ENABLED) {
            $optimizer = new RouteOptimizationService();
            $optimized = $optimizer->optimize($domicilios);
            $domicilios = $optimized['ordered'];
            $meta = $optimized['meta'];
        } else {
            // Fallback legacy para comparativas o troubleshooting.
            usort($domicilios, function (array $a, array $b): int {
                $secA = (int)($a['seccion_numero'] ?? 0);
                $secB = (int)($b['seccion_numero'] ?? 0);
                if ($secA !== $secB) return $secA - $secB;

                $calleComp = strcmp($a['calle'], $b['calle']);
                if ($calleComp !== 0) return $calleComp;

                return (int)$a['altura'] - (int)$b['altura'];
            });
        }

        // Eliminar asignaciones previas de la jornada
        $this->asignacionModel->deleteByJornada($id);

        // Insertar ordenadas
        $items = [];
        foreach (array_values($domicilios) as $orden => $dom) {
            $items[] = ['domicilio_id' => $dom['id'], 'orden' => $orden + 1];
        }

        $inserted = $this->asignacionModel->bulkInsert($id, $items);
        $this->jornadaModel->recalcularTotales($id);

        Response::success([
            'jornada_id' => $id,
            'asignados'  => $inserted,
            'route_meta' => $meta,
        ], 'Ruta generada correctamente', 201);
    }

    // ── Acceso por rol ────────────────────────────────────────

    private function checkAccess(array $jornada): void
    {
        $auth = AuthContext::get();
        if ($auth['rol'] === 'admin') return;

        if ($auth['rol'] === 'supervisor' && (int)$jornada['supervisor_id'] === $auth['sub']) return;

        if ($auth['rol'] === 'consultor' && (int)$jornada['consultor_id'] === $auth['sub']) return;

        Response::forbidden('No tienes acceso a esta jornada.');
    }
}
