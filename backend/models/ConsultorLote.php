<?php

class ConsultorLote
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function assign(int $consultorId, int $loteId, int $supervisorId, string $fechaAsignacion): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO consultor_lotes (consultor_id, lote_id, supervisor_id, fecha_asignacion)
             VALUES (:consultor_id, :lote_id, :supervisor_id, :fecha_asignacion)
             ON DUPLICATE KEY UPDATE supervisor_id = VALUES(supervisor_id), fecha_asignacion = VALUES(fecha_asignacion)'
        );
        $stmt->execute([
            ':consultor_id' => $consultorId,
            ':lote_id' => $loteId,
            ':supervisor_id' => $supervisorId,
            ':fecha_asignacion' => $fechaAsignacion,
        ]);

        $stmtSel = $this->db->prepare('SELECT id FROM consultor_lotes WHERE consultor_id = :consultor_id AND lote_id = :lote_id');
        $stmtSel->execute([':consultor_id' => $consultorId, ':lote_id' => $loteId]);
        return (int)$stmtSel->fetchColumn();
    }

    public function findByConsultorAndPeriod(int $consultorId, int $anio, int $mes): array
    {
        $stmt = $this->db->prepare(
            'SELECT cl.*, lm.anio, lm.mes, lm.numero_lote, lm.titulo,
                    (SELECT COUNT(*) FROM lote_domicilios ld WHERE ld.lote_id = lm.id) AS total_domicilios
               FROM consultor_lotes cl
               JOIN lotes_mensuales lm ON lm.id = cl.lote_id
              WHERE cl.consultor_id = :consultor_id
                AND lm.anio = :anio
                AND lm.mes = :mes
              ORDER BY lm.numero_lote ASC'
        );
        $stmt->execute([
            ':consultor_id' => $consultorId,
            ':anio' => $anio,
            ':mes' => $mes,
        ]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT cl.*, lm.anio, lm.mes, lm.numero_lote, lm.titulo
               FROM consultor_lotes cl
               JOIN lotes_mensuales lm ON lm.id = cl.lote_id
              WHERE cl.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySupervisorAndPeriod(int $supervisorId, int $anio, int $mes): array
    {
        $stmt = $this->db->prepare(
            'SELECT cl.*, lm.numero_lote, lm.titulo, u.nombre AS consultor_nombre, u.apellido AS consultor_apellido
               FROM consultor_lotes cl
               JOIN lotes_mensuales lm ON lm.id = cl.lote_id
               JOIN usuarios u ON u.id = cl.consultor_id
              WHERE cl.supervisor_id = :supervisor_id
                AND lm.anio = :anio
                AND lm.mes = :mes
              ORDER BY lm.numero_lote ASC, u.apellido ASC, u.nombre ASC'
        );
        $stmt->execute([
            ':supervisor_id' => $supervisorId,
            ':anio' => $anio,
            ':mes' => $mes,
        ]);
        return $stmt->fetchAll();
    }
}
