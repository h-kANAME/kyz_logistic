<?php
// =============================================================
// KYZ Logistica - LLM Controller (DeepSeek)
// POST /api/llm/jornadas/{id}/priorizar
// =============================================================

class LlmController
{
    private Jornada $jornadaModel;
    private Asignacion $asignacionModel;
    private LlmSugerencia $auditModel;

    public function __construct()
    {
        $this->jornadaModel = new Jornada();
        $this->asignacionModel = new Asignacion();
        $this->auditModel = new LlmSugerencia();
    }

    public function priorizarJornada(Request $request): void
    {
        if (!DEEPSEEK_ENABLED) {
            Response::error('DeepSeek deshabilitado. Configura DEEPSEEK_ENABLED=true en .env.', 409);
        }
        if (DEEPSEEK_API_KEY === '') {
            Response::error('Falta DEEPSEEK_API_KEY en .env.', 409);
        }

        $jornadaId = (int)$request->param('id');
        $apply = (bool)$request->input('apply', false);

        $jornada = $this->jornadaModel->findById($jornadaId);
        if (!$jornada) {
            Response::notFound('Jornada no encontrada.');
        }

        $this->checkAccess($jornada);

        $service = new RoutePriorityService(new DeepSeekClient(), $this->asignacionModel);
        $suggestion = $service->suggestOrderForJornada($jornadaId);

        if ($apply) {
            $this->asignacionModel->updateOrdenByIds($jornadaId, $suggestion['order_ids']);
            $this->jornadaModel->recalcularTotales($jornadaId);
        }

        $usage = $suggestion['usage'];
        $auditId = $this->auditModel->create([
            'jornada_id' => $jornadaId,
            'usuario_id' => AuthContext::id(),
            'proveedor' => 'deepseek',
            'modelo' => DEEPSEEK_MODEL,
            'objetivo' => 'priorizar_jornada',
            'input_json' => json_encode($suggestion['input'], JSON_UNESCAPED_UNICODE),
            'output_json' => json_encode([
                'order_ids' => $suggestion['order_ids'],
                'rationale' => $suggestion['rationale'],
            ], JSON_UNESCAPED_UNICODE),
            'aplicado' => $apply,
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'latency_ms' => $suggestion['latency_ms'] ?? null,
        ]);

        Response::success([
            'jornada_id' => $jornadaId,
            'apply' => $apply,
            'orden_sugerido' => $suggestion['order_ids'],
            'rationale' => $suggestion['rationale'],
            'audit_id' => $auditId,
            'usage' => $usage,
            'model' => DEEPSEEK_MODEL,
        ], $apply ? 'Priorizacion aplicada con DeepSeek' : 'Priorizacion sugerida con DeepSeek');
    }

    private function checkAccess(array $jornada): void
    {
        $auth = AuthContext::get();
        if ($auth['rol'] === 'admin') {
            return;
        }

        if ($auth['rol'] === 'supervisor' && (int)$jornada['supervisor_id'] === (int)$auth['sub']) {
            return;
        }

        Response::forbidden('Solo admin o supervisor dueno de la jornada pueden ejecutar priorizacion LLM.');
    }
}
