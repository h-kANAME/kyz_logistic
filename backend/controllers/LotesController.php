<?php

class LotesController
{
    private LoteMensual $loteModel;
    private ConsultorLote $consultorLoteModel;
    private Domicilio $domicilioModel;
    private HojaRuta $hojaRutaModel;
    private DayRoutePlannerService $planner;

    public function __construct()
    {
        $this->loteModel = new LoteMensual();
        $this->consultorLoteModel = new ConsultorLote();
        $this->domicilioModel = new Domicilio();
        $this->hojaRutaModel = new HojaRuta();
        $this->planner = new DayRoutePlannerService();
    }

    public function index(Request $request): void
    {
        $anio = (int)$request->query('anio', (int)date('Y'));
        $mes = (int)$request->query('mes', (int)date('n'));
        $items = $this->loteModel->findAllByPeriod($anio, $mes);
        Response::success($items);
    }

    public function store(Request $request): void
    {
        $anio = (int)$request->input('anio', (int)date('Y'));
        $mes = (int)$request->input('mes', (int)date('n'));
        $numeroLote = (int)$request->input('numero_lote', 1);
        $titulo = trim((string)$request->input('titulo', ''));
        $observacion = trim((string)$request->input('observacion', ''));
        if ($mes < 1 || $mes > 12) {
            Response::error('Mes invalido.', 422);
        }
        if ($numeroLote < 1 || $numeroLote > 3) {
            Response::error('numero_lote debe estar entre 1 y 3.', 422);
        }
        if ($titulo === '') {
            $titulo = sprintf('Lote %d/%d #%d', $mes, $anio, $numeroLote);
        }

        $id = $this->loteModel->create($anio, $mes, $numeroLote, $titulo, $observacion !== '' ? $observacion : null, AuthContext::id());
        $row = $this->loteModel->findById($id);
        Response::success($row, 'Lote mensual creado.', 201);
    }

    public function setDomicilios(Request $request): void
    {
        $loteId = (int)$request->param('id');
        $lote = $this->loteModel->findById($loteId);
        if (!$lote) {
            Response::notFound('Lote no encontrado.');
        }

        $domicilioIds = (array)$request->input('domicilio_ids', []);
        $count = $this->loteModel->replaceDomicilios($loteId, $domicilioIds);
        Response::success(['lote_id' => $loteId, 'total' => $count], 'Domicilios del lote actualizados.');
    }

    public function bootstrapFromDomicilios(Request $request): void
    {
        $loteId = (int)$request->param('id');
        $lote = $this->loteModel->findById($loteId);
        if (!$lote) {
            Response::notFound('Lote no encontrado.');
        }

        $rows = $this->domicilioModel->findAllForExport();
        $domicilioIds = array_map(fn($r) => (int)$r['id'], $rows);
        $count = $this->loteModel->replaceDomicilios($loteId, $domicilioIds);
        Response::success([
            'lote_id' => $loteId,
            'total' => $count,
        ], 'Lote cargado con domicilios actuales.');
    }

    public function assignToConsultor(Request $request): void
    {
        $auth = AuthContext::get();
        $loteId = (int)$request->param('id');
        $consultorId = (int)$request->input('consultor_id', 0);
        if ($consultorId <= 0) {
            Response::error('consultor_id es obligatorio.', 422);
        }
        $lote = $this->loteModel->findById($loteId);
        if (!$lote) {
            Response::notFound('Lote no encontrado.');
        }
        $id = $this->consultorLoteModel->assign($consultorId, $loteId, (int)$auth['sub'], date('Y-m-d'));
        $item = $this->consultorLoteModel->findById($id);
        Response::success($item, 'Lote asignado al consultor.');
    }

    public function myLotes(Request $request): void
    {
        $auth = AuthContext::get();
        $consultorId = (int)$auth['sub'];
        $anio = (int)$request->query('anio', (int)date('Y'));
        $mes = (int)$request->query('mes', (int)date('n'));
        $items = $this->consultorLoteModel->findByConsultorAndPeriod($consultorId, $anio, $mes);
        Response::success($items);
    }

    public function planLoteDia(Request $request): void
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

        $perfilModel = new ConsultorPerfil();
        $perfil = $perfilModel->findByUsuarioId((int)$consultorLote['consultor_id']);
        $requestRetornoDireccion = trim((string)$request->input('punto_retorno_direccion', ''));
        $fallbackRetornoDireccion = $perfil['punto_retorno_direccion'] ?? $perfil['punto_partida_direccion'];
        $retornoDireccion = $requestRetornoDireccion !== '' ? $requestRetornoDireccion : $fallbackRetornoDireccion;

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

        $reservadosPorHojas = $this->hojaRutaModel->domicilioIdsCoveredByHojasDeLote($consultorLoteId);
        $excluidosRequest = array_map('intval', (array)$request->input('excluded_asignacion_ids', []));
        $options = [
            'excluded_asignacion_ids' => array_values(
                array_unique(array_merge($excluidosRequest, $reservadosPorHojas))
            ),
            'forced_first_asignacion_id' => $request->input('forced_first_asignacion_id'),
            'consider_operational_time' => (bool)$request->input('consider_operational_time', false),
            'operational_margin_minutes' => max(0, (int)$request->input('operational_margin_minutes', 0)),
        ];

        $domicilioIds = $this->loteModel->domicilioIds((int)$consultorLote['lote_id']);
        $domicilios = $this->domicilioModel->findByIds($domicilioIds);
        $pool = [];
        foreach ($domicilios as $idx => $d) {
            $pool[] = [
                'id' => (int)$d['id'],
                'domicilio_id' => (int)$d['id'],
                'orden' => $idx + 1,
                'estado' => 'pendiente',
                'calle' => $d['calle'],
                'altura' => (int)$d['altura'],
                'servicio' => $d['servicio'],
                'latitud' => $d['latitud'],
                'longitud' => $d['longitud'],
                'seccion_numero' => $d['seccion_numero'],
            ];
        }

        $plan = $this->planner->plan($pool, $override, $options);
        Response::success([
            'consultor_lote_id' => $consultorLoteId,
            'consultor_id' => (int)$consultorLote['consultor_id'],
            'perfil' => $override,
            'constraints' => $options,
            'excluidas_por_hojas_previas' => count($reservadosPorHojas),
            'plan' => $plan,
        ], 'Hoja de ruta diaria generada desde lote.');
    }
}
