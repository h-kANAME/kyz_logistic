<?php
// =============================================================
// KYZ Logística – Modelo Jornada
// =============================================================

class Jornada
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Consultas ────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT j.*,
                    c.nombre AS consultor_nombre, c.email AS consultor_email,
                    s.nombre AS supervisor_nombre
               FROM jornadas j
               JOIN usuarios c ON c.id = j.consultor_id
               JOIN usuarios s ON s.id = j.supervisor_id
              WHERE j.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Listado con filtros: consultor_id, supervisor_id, fecha, estado.
     * El controller restringe según el rol del usuario autenticado.
     */
    public function findAll(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['consultor_id'])) {
            $where[]                = 'j.consultor_id = :consultor_id';
            $params[':consultor_id'] = (int)$filters['consultor_id'];
        }

        if (!empty($filters['supervisor_id'])) {
            $where[]                 = 'j.supervisor_id = :supervisor_id';
            $params[':supervisor_id'] = (int)$filters['supervisor_id'];
        }

        if (!empty($filters['fecha'])) {
            $where[]        = 'j.fecha = :fecha';
            $params[':fecha'] = $filters['fecha'];
        }

        if (!empty($filters['estado'])) {
            $where[]         = 'j.estado = :estado';
            $params[':estado'] = $filters['estado'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare(
            "SELECT j.id, j.fecha, j.estado, j.total_asignados, j.total_visitados,
                    j.consultor_id, c.nombre AS consultor_nombre,
                    j.supervisor_id, s.nombre AS supervisor_nombre,
                    j.created_at
               FROM jornadas j
               JOIN usuarios c ON c.id = j.consultor_id
               JOIN usuarios s ON s.id = j.supervisor_id
              $whereClause
              ORDER BY j.fecha DESC, c.nombre ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Mutaciones ───────────────────────────────────────────

    public function create(int $consultorId, int $supervisorId, string $fecha): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO jornadas (consultor_id, supervisor_id, fecha) VALUES (:cid, :sid, :fecha)'
        );
        $stmt->execute([':cid' => $consultorId, ':sid' => $supervisorId, ':fecha' => $fecha]);
        return (int)$this->db->lastInsertId();
    }

    public function updateEstado(int $id, string $estado): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE jornadas SET estado = :estado WHERE id = :id'
        );
        $stmt->execute([':estado' => $estado, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function update(int $id, int $consultorId, string $fecha): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE jornadas
                SET consultor_id = :cid,
                    fecha = :fecha
              WHERE id = :id'
        );
        $stmt->execute([
            ':cid' => $consultorId,
            ':fecha' => $fecha,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM jornadas WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Recalcula totales desde la tabla asignaciones */
    public function recalcularTotales(int $id): void
    {
        $this->db->prepare(
            'UPDATE jornadas j
                SET total_asignados = (
                        SELECT COUNT(*) FROM asignaciones WHERE jornada_id = :id1
                    ),
                    total_visitados = (
                        SELECT COUNT(*) FROM asignaciones
                         WHERE jornada_id = :id2 AND estado = \'visitado\'
                    )
              WHERE j.id = :id3'
        )->execute([':id1' => $id, ':id2' => $id, ':id3' => $id]);
    }

    public function existeParaConsultorFecha(int $consultorId, string $fecha): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM jornadas WHERE consultor_id = :cid AND fecha = :fecha'
        );
        $stmt->execute([':cid' => $consultorId, ':fecha' => $fecha]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function existeParaConsultorFechaExcepto(int $consultorId, string $fecha, int $jornadaId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
               FROM jornadas
              WHERE consultor_id = :cid
                AND fecha = :fecha
                AND id <> :jid'
        );
        $stmt->execute([
            ':cid' => $consultorId,
            ':fecha' => $fecha,
            ':jid' => $jornadaId,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
