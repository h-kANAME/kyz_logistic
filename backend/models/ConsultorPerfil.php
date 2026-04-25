<?php
// =============================================================
// KYZ Logistica - Modelo ConsultorPerfil
// =============================================================

class ConsultorPerfil
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByUsuarioId(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT usuario_id, punto_partida_direccion, punto_partida_latitud,
                    punto_partida_longitud, punto_retorno_direccion,
                    punto_retorno_latitud, punto_retorno_longitud,
                    movilidad, disponibilidad_minutos,
                    created_at, updated_at
               FROM consultor_perfiles
              WHERE usuario_id = :uid'
        );
        $stmt->execute([':uid' => $usuarioId]);
        $row = $stmt->fetch();

        if ($row) {
            return $row;
        }

        return [
            'usuario_id' => $usuarioId,
            'punto_partida_direccion' => null,
            'punto_partida_latitud' => null,
            'punto_partida_longitud' => null,
            'punto_retorno_direccion' => null,
            'punto_retorno_latitud' => null,
            'punto_retorno_longitud' => null,
            'movilidad' => 'a_pie',
            'disponibilidad_minutos' => 240,
        ];
    }

    public function upsert(int $usuarioId, array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO consultor_perfiles (
                usuario_id, punto_partida_direccion, punto_partida_latitud,
                     punto_partida_longitud, punto_retorno_direccion,
                     punto_retorno_latitud, punto_retorno_longitud,
                     movilidad, disponibilidad_minutos
             ) VALUES (
                     :uid, :dir, :lat, :lng, :ret_dir, :ret_lat, :ret_lng, :mov, :disp
             )
             ON DUPLICATE KEY UPDATE
                punto_partida_direccion = VALUES(punto_partida_direccion),
                punto_partida_latitud = VALUES(punto_partida_latitud),
                punto_partida_longitud = VALUES(punto_partida_longitud),
                     punto_retorno_direccion = VALUES(punto_retorno_direccion),
                     punto_retorno_latitud = VALUES(punto_retorno_latitud),
                     punto_retorno_longitud = VALUES(punto_retorno_longitud),
                movilidad = VALUES(movilidad),
                disponibilidad_minutos = VALUES(disponibilidad_minutos)'
        );

        $stmt->execute([
            ':uid' => $usuarioId,
            ':dir' => $data['punto_partida_direccion'] ?? null,
            ':lat' => $data['punto_partida_latitud'] ?? null,
            ':lng' => $data['punto_partida_longitud'] ?? null,
            ':ret_dir' => $data['punto_retorno_direccion'] ?? null,
            ':ret_lat' => $data['punto_retorno_latitud'] ?? null,
            ':ret_lng' => $data['punto_retorno_longitud'] ?? null,
            ':mov' => $data['movilidad'] ?? 'a_pie',
            ':disp' => (int)($data['disponibilidad_minutos'] ?? 240),
        ]);
    }
}
