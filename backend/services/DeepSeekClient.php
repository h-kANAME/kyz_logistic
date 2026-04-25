<?php
// =============================================================
// KYZ Logistica - Cliente DeepSeek (OpenAI-compatible API)
// =============================================================

class DeepSeekClient
{
    public function isConfigured(): bool
    {
        return DEEPSEEK_ENABLED && DEEPSEEK_API_KEY !== '';
    }

    /**
     * @return array{content:string, usage:array, raw:array}
     */
    public function chat(array $messages, ?float $temperature = null): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('DeepSeek no configurado. Revisar DEEPSEEK_ENABLED y DEEPSEEK_API_KEY.');
        }

        $url = rtrim(DEEPSEEK_BASE_URL, '/') . '/v1/chat/completions';

        $payload = [
            'model' => DEEPSEEK_MODEL,
            'messages' => $messages,
            'temperature' => $temperature ?? DEEPSEEK_TEMPERATURE,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . DEEPSEEK_API_KEY,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => DEEPSEEK_TIMEOUT,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('Error de red al consultar DeepSeek: ' . $error);
        }

        if (!is_string($response) || $response === '') {
            throw new RuntimeException('Respuesta vacia de DeepSeek.');
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            throw new RuntimeException('Respuesta invalida de DeepSeek (JSON malformado).');
        }

        if ($httpCode >= 400) {
            $message = $json['error']['message'] ?? ('HTTP ' . $httpCode);
            throw new RuntimeException('DeepSeek rechazo la solicitud: ' . $message);
        }

        $content = $json['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || $content === '') {
            throw new RuntimeException('DeepSeek no devolvio contenido util.');
        }

        return [
            'content' => $content,
            'usage' => $json['usage'] ?? [],
            'raw' => $json,
        ];
    }
}
