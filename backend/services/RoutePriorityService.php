<?php
// =============================================================
// KYZ Logistica - Servicio de priorizacion con DeepSeek
// =============================================================

class RoutePriorityService
{
    public function __construct(
        private DeepSeekClient $client,
        private Asignacion $asignacionModel
    ) {}

    /**
     * @return array{order_ids:int[], rationale:string, raw:array, usage:array, input:array}
     */
    public function suggestOrderForJornada(int $jornadaId): array
    {
        $items = $this->asignacionModel->findByJornada($jornadaId);
        if (empty($items)) {
            throw new RuntimeException('La jornada no tiene asignaciones.');
        }

        $pendientes = array_values(array_filter($items, fn($i) => $i['estado'] === 'pendiente'));
        if (empty($pendientes)) {
            throw new RuntimeException('No hay visitas pendientes para priorizar.');
        }

        $dataset = array_map(function ($it): array {
            return [
                'asignacion_id' => (int)$it['id'],
                'domicilio_id' => (int)$it['domicilio_id'],
                'seccion' => (int)$it['seccion_numero'],
                'calle' => (string)$it['calle'],
                'altura' => (int)$it['altura'],
                'servicio' => (string)$it['servicio'],
                'latitud' => $it['latitud'] !== null ? (float)$it['latitud'] : null,
                'longitud' => $it['longitud'] !== null ? (float)$it['longitud'] : null,
            ];
        }, $pendientes);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Eres un optimizador logistico para cobranzas domiciliarias. Debes responder SOLO JSON valido con los campos: order_ids (array de asignacion_id en orden de visita) y rationale (string breve). Prioriza minimizar desplazamientos por cercania geografica. Si faltan coordenadas, usa seccion, calle y altura.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'task' => 'Priorizar ruta de visitas pendientes',
                    'city' => 'Santa Fe, Argentina',
                    'records' => $dataset,
                    'constraints' => [
                        'Usar cada asignacion_id una sola vez',
                        'No omitir ningun registro',
                        'Devolver solo JSON',
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        $started = microtime(true);
        $resp = $this->client->chat($messages);
        $latencyMs = (int)round((microtime(true) - $started) * 1000);

        $parsed = json_decode($resp['content'], true);
        if (!is_array($parsed) || !isset($parsed['order_ids']) || !is_array($parsed['order_ids'])) {
            throw new RuntimeException('DeepSeek devolvio formato no valido para order_ids.');
        }

        $ids = array_map('intval', $parsed['order_ids']);
        $ids = array_values(array_unique($ids));

        $expected = array_map(fn($r) => (int)$r['asignacion_id'], $dataset);
        sort($expected);
        $check = $ids;
        sort($check);
        if ($check !== $expected) {
            throw new RuntimeException('DeepSeek devolvio IDs incompletos o inconsistentes.');
        }

        return [
            'order_ids' => $ids,
            'rationale' => (string)($parsed['rationale'] ?? 'Sin justificacion'),
            'raw' => $resp['raw'],
            'usage' => $resp['usage'],
            'input' => $dataset,
            'latency_ms' => $latencyMs,
        ];
    }
}
