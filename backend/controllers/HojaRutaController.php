<?php
// =============================================================
// KYZ Logistica - HojaRutaController
// GET    /api/hojas-ruta
// GET    /api/hojas-ruta/{id}
// POST   /api/jornadas/{id}/hojas-ruta
// PUT    /api/hojas-ruta/{id}
// DELETE /api/hojas-ruta/{id}
// =============================================================

class HojaRutaController
{
    private HojaRuta $hojaRutaModel;
    private Jornada $jornadaModel;
    private Asignacion $asignacionModel;
    private ConsultorLote $consultorLoteModel;
    private VisitaRegistro $visitaRegistroModel;
    private Domicilio $domicilioModel;

    public function __construct()
    {
        $this->hojaRutaModel = new HojaRuta();
        $this->jornadaModel = new Jornada();
        $this->asignacionModel = new Asignacion();
        $this->consultorLoteModel = new ConsultorLote();
        $this->visitaRegistroModel = new VisitaRegistro();
        $this->domicilioModel = new Domicilio();
    }

    public function index(Request $request): void
    {
        $auth = AuthContext::get();
        $filters = [];

        if ($auth['rol'] === 'consultor') {
            $filters['consultor_id'] = (int)$auth['sub'];
        }
        if ($auth['rol'] === 'supervisor') {
            $filters['supervisor_id'] = (int)$auth['sub'];
        }

        $jid = (int)$request->query('jornada_id', 0);
        if ($jid > 0) {
            $filters['jornada_id'] = $jid;
        }
        $consultorLoteId = (int)$request->query('consultor_lote_id', 0);
        if ($consultorLoteId > 0) {
            $filters['consultor_lote_id'] = $consultorLoteId;
        }

        $perPage = max(1, min(50, (int)$request->query('per_page', 10)));
        $page = max(1, (int)$request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $total = $this->hojaRutaModel->count($filters);
        $items = $this->hojaRutaModel->findPaginated($filters, $perPage, $offset);

        $items = array_map(fn($item) => $this->withStatusSummary($item), $items);

        Response::success([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        $id = (int)$request->param('id');
        $item = $this->hojaRutaModel->findById($id);

        if (!$item) {
            Response::notFound('Hoja de ruta no encontrada.');
        }

        $this->checkAccess($item);
        $item = $this->withStatusSummary($item);
        Response::success($this->enrichPlanStopsWithVisitas($item));
    }

    public function storeFromLote(Request $request): void
    {
        $auth = AuthContext::get();
        $consultorLoteId = (int)$request->param('id');
        $consultorLote = $this->consultorLoteModel->findById($consultorLoteId);
        if (!$consultorLote) {
            Response::notFound('Asignacion de lote no encontrada.');
        }

        if ($auth['rol'] === 'consultor' && (int)$consultorLote['consultor_id'] !== (int)$auth['sub']) {
            Response::forbidden('No tienes acceso a este lote.');
        }
        if ($auth['rol'] === 'supervisor' && (int)$consultorLote['supervisor_id'] !== (int)$auth['sub']) {
            Response::forbidden('No tienes acceso a este lote.');
        }

        $plan = $request->input('plan');
        if (!is_array($plan) || empty($plan['stops'])) {
            Response::error('Debe enviar una propuesta de recorrido valida para guardar la hoja de ruta.', 422);
        }

        $constraints = $request->input('constraints', []);
        $estado = (string)$request->input('estado', 'validada');
        $validEstados = ['validada', 'en_curso', 'completada', 'cancelada'];
        if (!in_array($estado, $validEstados, true)) {
            Response::error('Estado de hoja de ruta invalido.', 422);
        }

        $titulo = trim((string)$request->input('titulo', ''));
        if ($titulo === '') {
            $titulo = 'Hoja lote ' . $consultorLote['numero_lote'] . ' - ' . date('Y-m-d H:i');
        }

        $id = $this->hojaRutaModel->create([
            'jornada_id' => null,
            'consultor_id' => (int)$consultorLote['consultor_id'],
            'consultor_lote_id' => $consultorLoteId,
            'titulo' => $titulo,
            'estado' => $estado,
            'next_offset' => 0,
            'constraints' => is_array($constraints) ? $constraints : [],
            'plan' => $plan,
            'creada_por' => (int)$auth['sub'],
        ]);

        $item = $this->hojaRutaModel->findById($id);
        Response::success(
            $this->enrichPlanStopsWithVisitas($this->withStatusSummary($item)),
            'Hoja de ruta de lote guardada.',
            201
        );
    }

    public function openNextBatch(Request $request): void
    {
        $id = (int)$request->param('id');
        $item = $this->hojaRutaModel->findById($id);
        if (!$item) {
            Response::notFound('Hoja de ruta no encontrada.');
        }
        $this->checkAccess($item);

        $stops = $item['plan']['stops'] ?? [];
        if (!is_array($stops) || empty($stops)) {
            Response::error('La hoja no tiene paradas.', 422);
        }

        $limit = max(1, min(10, (int)$request->input('limit', 10)));
        $offset = (int)($item['next_offset'] ?? 0);
        $batch = array_slice($stops, $offset, $limit);
        if (empty($batch)) {
            Response::success([
                'batch' => [],
                'offset' => $offset,
                'next_offset' => $offset,
                'has_more' => false,
                'google_maps_url' => null,
            ], 'No quedan paradas por cargar en el celular.');
        }

        $nextOffset = min(count($stops), $offset + count($batch));
        $this->hojaRutaModel->updateBatchOffset($id, $nextOffset, $offset);

        $mode = $this->inferTravelMode((string)($item['constraints']['movilidad'] ?? 'a_pie'));
        $googleMapsUrl = $this->buildBatchGoogleMapsUrl($item, $batch, $mode);

        Response::success([
            'batch' => $batch,
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'has_more' => $nextOffset < count($stops),
            'google_maps_url' => $googleMapsUrl,
        ], 'Bloque de paradas listo para cargar en Google Maps.');
    }

    public function storeFromPlan(Request $request): void
    {
        $auth = AuthContext::get();
        $jornadaId = (int)$request->param('id');
        $jornada = $this->jornadaModel->findById($jornadaId);

        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->checkJornadaAccess($jornada);

        $plan = $request->input('plan');
        if (!is_array($plan) || empty($plan['stops'])) {
            Response::error('Debe enviar una propuesta de recorrido valida para guardar la hoja de ruta.', 422);
        }

        $constraints = $request->input('constraints', []);
        $estado = (string)$request->input('estado', 'validada');
        $validEstados = ['validada', 'en_curso', 'completada', 'cancelada'];
        if (!in_array($estado, $validEstados, true)) {
            Response::error('Estado de hoja de ruta invalido.', 422);
        }

        $titulo = trim((string)$request->input('titulo', ''));
        if ($titulo === '') {
            $titulo = 'Hoja ' . $jornada['fecha'] . ' #' . $jornadaId;
        }

        $id = $this->hojaRutaModel->create([
            'jornada_id' => $jornadaId,
            'consultor_id' => (int)$jornada['consultor_id'],
            'titulo' => $titulo,
            'estado' => $estado,
            'constraints' => is_array($constraints) ? $constraints : [],
            'plan' => $plan,
            'creada_por' => (int)$auth['sub'],
        ]);

        $item = $this->hojaRutaModel->findById($id);
        Response::success(
            $this->enrichPlanStopsWithVisitas($this->withStatusSummary($item)),
            'Hoja de ruta validada y guardada.',
            201
        );
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $item = $this->hojaRutaModel->findById($id);

        if (!$item) {
            Response::notFound('Hoja de ruta no encontrada.');
        }

        $this->checkAccess($item);

        $payload = [];
        if ($request->input('titulo') !== null) {
            $payload['titulo'] = trim((string)$request->input('titulo'));
        }
        if ($request->input('estado') !== null) {
            $estado = (string)$request->input('estado');
            $validEstados = ['validada', 'en_curso', 'completada', 'cancelada'];
            if (!in_array($estado, $validEstados, true)) {
                Response::error('Estado de hoja de ruta invalido.', 422);
            }
            $payload['estado'] = $estado;
        }

        $this->hojaRutaModel->updateMeta($id, $payload);
        $updated = $this->hojaRutaModel->findById($id);
        Response::success($this->enrichPlanStopsWithVisitas($this->withStatusSummary($updated)), 'Hoja de ruta actualizada.');
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        $item = $this->hojaRutaModel->findById($id);

        if (!$item) {
            Response::notFound('Hoja de ruta no encontrada.');
        }

        $this->checkAccess($item);
        $this->hojaRutaModel->delete($id);
        Response::success(null, 'Hoja de ruta eliminada.');
    }

    public function registrarVisita(Request $request): void
    {
        $auth = AuthContext::get();
        $id = (int)$request->param('id');
        $item = $this->hojaRutaModel->findById($id);
        if (!$item) {
            Response::notFound('Hoja de ruta no encontrada.');
        }
        $this->checkAccess($item);

        $domicilioId = (int)$request->input('domicilio_id', 0);
        if ($domicilioId <= 0) {
            Response::error('domicilio_id es obligatorio.', 422);
        }

        $visitado = (bool)$request->input('visitado', false);
        $documentoFirmado = (bool)$request->input('documento_firmado', false);
        $observacion = trim((string)$request->input('observacion', ''));

        $visitaId = $this->visitaRegistroModel->upsert($id, $domicilioId, $visitado, $documentoFirmado, (int)$auth['sub']);
        if ($observacion !== '') {
            $this->visitaRegistroModel->addObservacion($visitaId, mb_substr($observacion, 0, 255), (int)$auth['sub']);
        }

        $registro = $this->visitaRegistroModel->findByHojaAndDomicilio($id, $domicilioId);
        Response::success($registro, 'Visita registrada.');
    }

    public function historialDomicilio(Request $request): void
    {
        $domicilioId = (int)$request->param('domicilio_id');
        if ($domicilioId <= 0) {
            Response::error('domicilio_id invalido.', 422);
        }
        $domicilio = $this->domicilioModel->findById($domicilioId);
        if (!$domicilio) {
            Response::notFound('Domicilio no encontrado.');
        }
        $history = $this->visitaRegistroModel->historyByDomicilio($domicilioId);
        Response::success([
            'domicilio' => $domicilio,
            'historial' => $history,
        ]);
    }

    private function checkAccess(array $item): void
    {
        $auth = AuthContext::get();

        if ($auth['rol'] === 'admin') {
            return;
        }
        if ($auth['rol'] === 'supervisor' && (
            (int)($item['supervisor_id'] ?? 0) === (int)$auth['sub']
            || (int)($item['lote_supervisor_id'] ?? 0) === (int)$auth['sub']
        )) {
            return;
        }
        if ($auth['rol'] === 'consultor' && (int)$item['consultor_id'] === (int)$auth['sub']) {
            return;
        }

        Response::forbidden('No tienes acceso a esta hoja de ruta.');
    }

    private function checkJornadaAccess(array $jornada): void
    {
        $auth = AuthContext::get();

        if ($auth['rol'] === 'admin') {
            return;
        }
        if ($auth['rol'] === 'supervisor' && (int)$jornada['supervisor_id'] === (int)$auth['sub']) {
            return;
        }
        if ($auth['rol'] === 'consultor' && (int)$jornada['consultor_id'] === (int)$auth['sub']) {
            return;
        }

        Response::forbidden('No tienes acceso a esta jornada.');
    }

    /**
     * Agrega a cada parada del plan el estado en visitas_registro (si existe).
     *
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function enrichPlanStopsWithVisitas(array $item): array
    {
        $stops = $item['plan']['stops'] ?? null;
        if (!is_array($stops) || $stops === []) {
            return $item;
        }

        $visitas = $this->visitaRegistroModel->findByHoja((int)$item['id']);
        $byDomicilio = [];
        foreach ($visitas as $row) {
            $byDomicilio[(int)$row['domicilio_id']] = $row;
        }

        foreach ($stops as $idx => $stop) {
            $did = (int)($stop['domicilio_id'] ?? 0);
            if ($did > 0 && isset($byDomicilio[$did])) {
                $row = $byDomicilio[$did];
                $stops[$idx]['visita'] = [
                    'visitado' => (int)$row['visitado'] === 1,
                    'documento_firmado' => (int)$row['documento_firmado'] === 1,
                ];
            } else {
                $stops[$idx]['visita'] = null;
            }
        }

        $item['plan']['stops'] = $stops;
        return $item;
    }

    private function withStatusSummary(array $item): array
    {
        $stops = $item['plan']['stops'] ?? [];
        if (!is_array($stops) || empty($stops)) {
            $item['status_summary'] = [
                'visitadas' => 0,
                'no_firmadas' => 0,
                'restan' => 0,
                'texto' => 'Sin paradas registradas',
            ];
            return $item;
        }

        $ids = [];
        $domicilioIds = [];
        foreach ($stops as $stop) {
            $aid = (int)($stop['asignacion_id'] ?? 0);
            $did = (int)($stop['domicilio_id'] ?? 0);
            if ($aid > 0) {
                $ids[] = $aid;
            }
            if ($did > 0) {
                $domicilioIds[] = $did;
            }
        }
        $byId = $this->asignacionModel->findEstadoByIds($ids);
        $visitas = $this->visitaRegistroModel->findByHoja((int)$item['id']);
        $visitasByDomicilio = [];
        foreach ($visitas as $row) {
            $visitasByDomicilio[(int)$row['domicilio_id']] = $row;
        }

        $visitadas = 0;
        $noFirmadas = 0;
        $restan = 0;

        foreach ($stops as $stop) {
            $aid = (int)($stop['asignacion_id'] ?? 0);
            $did = (int)($stop['domicilio_id'] ?? 0);
            $asig = $byId[$aid] ?? null;
            $visita = $visitasByDomicilio[$did] ?? null;

            if (!$asig && !$visita) {
                $restan++;
                continue;
            }

            if (($asig && $asig['estado'] === 'visitado') || ($visita && (int)$visita['visitado'] === 1)) {
                $visitadas++;
                $firmado = $asig ? (int)$asig['firmado'] : (int)$visita['documento_firmado'];
                if ($firmado === 0) {
                    $noFirmadas++;
                }
                continue;
            }

            $restan++;
        }

        $item['status_summary'] = [
            'visitadas' => $visitadas,
            'no_firmadas' => $noFirmadas,
            'restan' => $restan,
            'texto' => $visitadas . ' visitadas, ' . $noFirmadas . ' no fueron firmadas, restan visitar ' . $restan . ' direcciones',
        ];

        return $item;
    }

    private function inferTravelMode(string $movilidad): string
    {
        return match ($movilidad) {
            'vehiculo' => 'driving',
            'bicicleta' => 'bicycling',
            'autobus' => 'transit',
            default => 'walking',
        };
    }

    private function buildBatchGoogleMapsUrl(array $item, array $batch, string $mode): string
    {
        $origin = $item['plan']['ruta']['inicio'] ?? null;
        if (!$origin || !isset($origin['latitud'], $origin['longitud'])) {
            $first = $batch[0];
            $origin = ['latitud' => (float)$first['latitud'], 'longitud' => (float)$first['longitud']];
        }
        $destination = end($batch);
        reset($batch);

        $originStr = number_format((float)$origin['latitud'], 6, '.', '') . ',' . number_format((float)$origin['longitud'], 6, '.', '');
        $destStr = number_format((float)$destination['latitud'], 6, '.', '') . ',' . number_format((float)$destination['longitud'], 6, '.', '');

        $waypoints = [];
        foreach (array_slice($batch, 0, max(0, count($batch) - 1)) as $stop) {
            $waypoints[] = number_format((float)$stop['latitud'], 6, '.', '') . ',' . number_format((float)$stop['longitud'], 6, '.', '');
        }

        $url = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($originStr)
            . '&destination=' . rawurlencode($destStr)
            . '&travelmode=' . rawurlencode($mode);
        if (!empty($waypoints)) {
            $url .= '&waypoints=' . rawurlencode(implode('|', $waypoints));
        }
        return $url;
    }
}
