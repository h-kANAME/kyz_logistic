<?php
// =============================================================
// KYZ Logística – AsignacionController
// GET   /api/asignaciones/{id}
// PATCH /api/asignaciones/{id}
// =============================================================

class AsignacionController
{
    private Asignacion $model;
    private Jornada    $jornadaModel;

    public function __construct()
    {
        $this->model        = new Asignacion();
        $this->jornadaModel = new Jornada();
    }

    /** GET /api/asignaciones/{id} */
    public function show(Request $request): void
    {
        $id          = (int)$request->param('id');
        $asignacion  = $this->model->findById($id);

        if (!$asignacion) {
            Response::notFound('Asignación no encontrada.');
        }

        $this->checkAccess($asignacion);
        Response::success($asignacion);
    }

    /**
     * PATCH /api/asignaciones/{id}
     * El Consultor registra el resultado de la visita.
     * Campos: estado (visitado|ausente|reagendar), firmado (0|1), observacion
     */
    public function update(Request $request): void
    {
        $id         = (int)$request->param('id');
        $asignacion = $this->model->findById($id);

        if (!$asignacion) {
            Response::notFound('Asignación no encontrada.');
        }

        $this->checkAccess($asignacion);

        $estado      = $request->input('estado');
        $firmado     = $request->input('firmado');
        $observacion = $request->input('observacion');

        $estadosValidos = ['pendiente', 'visitado', 'ausente', 'reagendar'];
        if ($estado !== null && !in_array($estado, $estadosValidos, true)) {
            Response::error('Estado inválido. Valores: ' . implode(', ', $estadosValidos), 422);
        }

        $fields = array_filter([
            'estado'      => $estado,
            'firmado'     => $firmado !== null ? (int)(bool)$firmado : null,
            'observacion' => $observacion,
        ], fn($v) => $v !== null);

        if (empty($fields)) {
            Response::error('No se proporcionaron campos para actualizar.', 422);
        }

        $this->model->update($id, $fields);

        // Recalcular totales de la jornada
        $jornadaId = $this->model->getJornadaIdOf($id);
        if ($jornadaId) {
            $this->jornadaModel->recalcularTotales($jornadaId);
        }

        // Devolver la siguiente visita pendiente (UX: el Consultor no necesita navegar)
        $siguiente = $jornadaId
            ? $this->model->findPendienteByJornada($jornadaId)
            : null;

        Response::success([
            'actualizado' => true,
            'siguiente'   => $siguiente,
        ], 'Visita registrada');
    }

    // ── Acceso por rol ────────────────────────────────────────

    private function checkAccess(array $asignacion): void
    {
        $auth = AuthContext::get();
        if ($auth['rol'] === 'admin') return;

        // Supervisor y Consultor necesitan verificar ownership a través de la jornada
        $jornadaId = (int)$asignacion['jornada_id'];
        $jornada   = (new Jornada())->findById($jornadaId);

        if (!$jornada) {
            Response::notFound('Jornada asociada no encontrada.');
        }

        if ($auth['rol'] === 'supervisor' && (int)$jornada['supervisor_id'] === $auth['sub']) return;
        if ($auth['rol'] === 'consultor'  && (int)$jornada['consultor_id']  === $auth['sub']) return;

        Response::forbidden('No tienes acceso a esta asignación.');
    }
}
