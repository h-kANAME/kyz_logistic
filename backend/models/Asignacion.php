<?php
// =============================================================
// KYZ Logística – Modelo Asignacion
// =============================================================

class Asignacion
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
            'SELECT a.*, d.calle, d.altura, d.servicio, d.latitud, d.longitud,
                    s.numero AS seccion_numero
               FROM asignaciones a
               JOIN domicilios  d ON d.id = a.domicilio_id
               JOIN secciones   s ON s.id = d.seccion_id
              WHERE a.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByJornada(int $jornadaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.id, a.orden, a.estado, a.firmado, a.observacion, a.visitado_at,
                    d.id AS domicilio_id, d.calle, d.altura, d.servicio,
                    d.latitud, d.longitud, s.numero AS seccion_numero
               FROM asignaciones a
               JOIN domicilios  d ON d.id = a.domicilio_id
               JOIN secciones   s ON s.id = d.seccion_id
              WHERE a.jornada_id = :jid
              ORDER BY a.orden ASC'
        );
        $stmt->execute([':jid' => $jornadaId]);
        return $stmt->fetchAll();
    }

    /**
     * Solo id, estado y firmado — para resúmenes sin JOIN ni ordenación pesada.
     *
     * @param array<int,int> $ids
     * @return array<int,array{id:int,estado:string,firmado:int}>
     */
    public function findEstadoByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, estado, firmado FROM asignaciones WHERE id IN ($placeholders)"
        );
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['id']] = [
                'id' => (int)$row['id'],
                'estado' => (string)$row['estado'],
                'firmado' => (int)$row['firmado'],
            ];
        }
        return $out;
    }

    public function findByJornadaPaginated(int $jornadaId, int $limit, int $offset): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.id, a.orden, a.estado, a.firmado, a.observacion, a.visitado_at,
                    d.id AS domicilio_id, d.calle, d.altura, d.servicio,
                    d.latitud, d.longitud, s.numero AS seccion_numero
               FROM asignaciones a
               JOIN domicilios  d ON d.id = a.domicilio_id
               JOIN secciones   s ON s.id = d.seccion_id
              WHERE a.jornada_id = :jid
              ORDER BY a.orden ASC
              LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':jid', $jornadaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countByJornada(int $jornadaId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM asignaciones WHERE jornada_id = :jid');
        $stmt->execute([':jid' => $jornadaId]);
        return (int)$stmt->fetchColumn();
    }

    public function findPendienteByJornada(int $jornadaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.id, a.orden, d.calle, d.altura, d.servicio, d.latitud, d.longitud,
                    s.numero AS seccion_numero
               FROM asignaciones a
               JOIN domicilios  d ON d.id = a.domicilio_id
               JOIN secciones   s ON s.id = d.seccion_id
              WHERE a.jornada_id = :jid AND a.estado = \'pendiente\'
              ORDER BY a.orden ASC
              LIMIT 1'
        );
        $stmt->execute([':jid' => $jornadaId]);
        return $stmt->fetch() ?: null;
    }

    // ── Mutaciones ───────────────────────────────────────────

    /**
     * Inserta múltiples asignaciones en una transacción.
     * $items = [['domicilio_id' => N, 'orden' => N], ...]
     */
    public function bulkInsert(int $jornadaId, array $items): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO asignaciones (jornada_id, domicilio_id, orden)
             VALUES (:jid, :did, :orden)'
        );

        $this->db->beginTransaction();
        $inserted = 0;
        try {
            foreach ($items as $item) {
                $stmt->execute([
                    ':jid'   => $jornadaId,
                    ':did'   => (int)$item['domicilio_id'],
                    ':orden' => (int)$item['orden'],
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $inserted;
    }

    /**
     * Actualiza estado, firmado y observación de una asignación.
     * Solo campos provistos en $fields son actualizados.
     */
    public function update(int $id, array $fields): bool
    {
        $allowed = ['estado', 'firmado', 'observacion'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $fields)) {
                $sets[]            = "$field = :$field";
                $params[":$field"] = $fields[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        // Si se marca como visitado, registrar timestamp
        if (isset($fields['estado']) && $fields['estado'] === 'visitado') {
            $sets[]              = 'visitado_at = NOW()';
        }

        $stmt = $this->db->prepare(
            'UPDATE asignaciones SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteByJornada(int $jornadaId): int
    {
        $stmt = $this->db->prepare('DELETE FROM asignaciones WHERE jornada_id = :jid');
        $stmt->execute([':jid' => $jornadaId]);
        return $stmt->rowCount();
    }

    public function getJornadaIdOf(int $asignacionId): ?int
    {
        $stmt = $this->db->prepare('SELECT jornada_id FROM asignaciones WHERE id = :id');
        $stmt->execute([':id' => $asignacionId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['jornada_id'] : null;
    }

    /**
     * Aplica un nuevo orden a las asignaciones de una jornada.
     * $orderedIds debe incluir todos los IDs de esa jornada, sin repetidos.
     */
    public function updateOrdenByIds(int $jornadaId, array $orderedIds): void
    {
        if (empty($orderedIds)) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE asignaciones SET orden = :orden WHERE jornada_id = :jid AND id = :id'
        );

        $this->db->beginTransaction();
        try {
            foreach (array_values($orderedIds) as $idx => $id) {
                $stmt->execute([
                    ':orden' => $idx + 1,
                    ':jid' => $jornadaId,
                    ':id' => (int)$id,
                ]);
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
