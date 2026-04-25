<?php
// =============================================================
// KYZ Logistica - Auditoria de sugerencias LLM
// =============================================================

class LlmSugerencia
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO llm_sugerencias (
                jornada_id, usuario_id, proveedor, modelo, objetivo,
                input_json, output_json, aplicado,
                prompt_tokens, completion_tokens, total_tokens, latency_ms, created_at
             ) VALUES (
                :jornada_id, :usuario_id, :proveedor, :modelo, :objetivo,
                :input_json, :output_json, :aplicado,
                :prompt_tokens, :completion_tokens, :total_tokens, :latency_ms, NOW()
             )'
        );

        $stmt->execute([
            ':jornada_id' => $data['jornada_id'],
            ':usuario_id' => $data['usuario_id'],
            ':proveedor' => $data['proveedor'],
            ':modelo' => $data['modelo'],
            ':objetivo' => $data['objetivo'],
            ':input_json' => $data['input_json'],
            ':output_json' => $data['output_json'],
            ':aplicado' => $data['aplicado'] ? 1 : 0,
            ':prompt_tokens' => $data['prompt_tokens'] ?? null,
            ':completion_tokens' => $data['completion_tokens'] ?? null,
            ':total_tokens' => $data['total_tokens'] ?? null,
            ':latency_ms' => $data['latency_ms'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
