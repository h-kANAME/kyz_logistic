<?php
// =============================================================
// KYZ Logistica - Modelo HojaRuta
// =============================================================

class HojaRuta
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $payload): int
    {
        $consultorLoteId = isset($payload['consultor_lote_id']) ? (int)$payload['consultor_lote_id'] : null;
        $jornadaId = isset($payload['jornada_id']) ? (int)$payload['jornada_id'] : null;
        $stmt = $this->db->prepare(
            'INSERT INTO hojas_ruta (
                jornada_id, consultor_id, consultor_lote_id, titulo, estado, next_offset,
                constraints_json, plan_json, creada_por
            ) VALUES (
                :jornada_id, :consultor_id, :consultor_lote_id, :titulo, :estado, :next_offset,
                :constraints_json, :plan_json, :creada_por
            )'
        );

        $stmt->execute([
            ':jornada_id' => $jornadaId,
            ':consultor_id' => (int)$payload['consultor_id'],
            ':consultor_lote_id' => $consultorLoteId,
            ':titulo' => (string)$payload['titulo'],
            ':estado' => (string)($payload['estado'] ?? 'validada'),
            ':next_offset' => (int)($payload['next_offset'] ?? 0),
            ':constraints_json' => json_encode($payload['constraints'] ?? [], JSON_UNESCAPED_UNICODE),
            ':plan_json' => json_encode($payload['plan'] ?? [], JSON_UNESCAPED_UNICODE),
            ':creada_por' => (int)$payload['creada_por'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT hr.*, j.fecha, j.supervisor_id, cl.supervisor_id AS lote_supervisor_id,
                    cl.lote_id, lm.anio AS lote_anio, lm.mes AS lote_mes, lm.numero_lote
               FROM hojas_ruta hr
               LEFT JOIN jornadas j ON j.id = hr.jornada_id
               LEFT JOIN consultor_lotes cl ON cl.id = hr.consultor_lote_id
               LEFT JOIN lotes_mensuales lm ON lm.id = cl.lote_id
              WHERE hr.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->decodeJsonColumns($row);
    }

    public function findPaginated(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['consultor_id'])) {
            $where[] = 'hr.consultor_id = :consultor_id';
            $params[':consultor_id'] = (int)$filters['consultor_id'];
        }
        if (!empty($filters['supervisor_id'])) {
            $where[] = '(j.supervisor_id = :supervisor_id OR cl.supervisor_id = :supervisor_id)';
            $params[':supervisor_id'] = (int)$filters['supervisor_id'];
        }
        if (!empty($filters['jornada_id'])) {
            $where[] = 'hr.jornada_id = :jornada_id';
            $params[':jornada_id'] = (int)$filters['jornada_id'];
        }

        if (!empty($filters['consultor_lote_id'])) {
            $where[] = 'hr.consultor_lote_id = :consultor_lote_id';
            $params[':consultor_lote_id'] = (int)$filters['consultor_lote_id'];
        }

        $sql = 'SELECT hr.id, hr.jornada_id, hr.consultor_id, hr.titulo, hr.estado,
                       hr.consultor_lote_id, hr.next_offset, hr.last_opened_batch_start, hr.last_opened_batch_at,
                       hr.constraints_json, hr.plan_json, hr.created_at, hr.updated_at,
                       j.fecha, j.supervisor_id, cl.supervisor_id AS lote_supervisor_id,
                       cl.lote_id, lm.anio AS lote_anio, lm.mes AS lote_mes, lm.numero_lote
                  FROM hojas_ruta hr
                  LEFT JOIN jornadas j ON j.id = hr.jornada_id
                  LEFT JOIN consultor_lotes cl ON cl.id = hr.consultor_lote_id
                  LEFT JOIN lotes_mensuales lm ON lm.id = cl.lote_id';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY hr.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return array_map(fn($row) => $this->decodeJsonColumns($row), $rows);
    }

    public function count(array $filters): int
    {
        $where = [];
        $params = [];

        if (!empty($filters['consultor_id'])) {
            $where[] = 'hr.consultor_id = :consultor_id';
            $params[':consultor_id'] = (int)$filters['consultor_id'];
        }
        if (!empty($filters['supervisor_id'])) {
            $where[] = '(j.supervisor_id = :supervisor_id OR cl.supervisor_id = :supervisor_id)';
            $params[':supervisor_id'] = (int)$filters['supervisor_id'];
        }
        if (!empty($filters['jornada_id'])) {
            $where[] = 'hr.jornada_id = :jornada_id';
            $params[':jornada_id'] = (int)$filters['jornada_id'];
        }

        if (!empty($filters['consultor_lote_id'])) {
            $where[] = 'hr.consultor_lote_id = :consultor_lote_id';
            $params[':consultor_lote_id'] = (int)$filters['consultor_lote_id'];
        }

        $sql = 'SELECT COUNT(*)
                  FROM hojas_ruta hr
                  LEFT JOIN jornadas j ON j.id = hr.jornada_id
                  LEFT JOIN consultor_lotes cl ON cl.id = hr.consultor_lote_id';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function updateMeta(int $id, array $payload): bool
    {
        $sets = [];
        $params = [':id' => $id];

        if (array_key_exists('titulo', $payload)) {
            $sets[] = 'titulo = :titulo';
            $params[':titulo'] = trim((string)$payload['titulo']);
        }

        if (array_key_exists('estado', $payload)) {
            $sets[] = 'estado = :estado';
            $params[':estado'] = (string)$payload['estado'];
        }

        if (empty($sets)) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE hojas_ruta SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM hojas_ruta WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateBatchOffset(int $id, int $nextOffset, ?int $lastOpenedBatchStart = null): bool
    {
        if ($lastOpenedBatchStart === null) {
            $stmt = $this->db->prepare(
                'UPDATE hojas_ruta
                    SET next_offset = :next_offset,
                        last_opened_batch_at = NOW()
                  WHERE id = :id'
            );
            $stmt->execute([':id' => $id, ':next_offset' => $nextOffset]);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE hojas_ruta
                    SET next_offset = :next_offset,
                        last_opened_batch_start = :batch_start,
                        last_opened_batch_at = NOW()
                  WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':next_offset' => $nextOffset,
                ':batch_start' => $lastOpenedBatchStart,
            ]);
        }
        return $stmt->rowCount() > 0;
    }

    /**
     * Domicilios que ya forman parte de una hoja guardada para este lote.
     * Las hojas en estado "cancelada" no reservan direcciones.
     *
     * @return list<int>
     */
    public function domicilioIdsCoveredByHojasDeLote(int $consultorLoteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT plan_json
               FROM hojas_ruta
              WHERE consultor_lote_id = :clid
                AND estado != 'cancelada'"
        );
        $stmt->execute([':clid' => $consultorLoteId]);
        $ids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $plan = json_decode((string)($row['plan_json'] ?? ''), true) ?: [];
            $stops = $plan['stops'] ?? null;
            if (!is_array($stops)) {
                continue;
            }
            foreach ($stops as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $did = (int)($s['domicilio_id'] ?? 0);
                if ($did > 0) {
                    $ids[$did] = $did;
                }
            }
        }
        return array_map('intval', array_values($ids));
    }

    private function decodeJsonColumns(array $row): array
    {
        $row['constraints'] = json_decode((string)$row['constraints_json'], true) ?: [];
        $row['plan'] = json_decode((string)$row['plan_json'], true) ?: [];
        unset($row['constraints_json'], $row['plan_json']);
        return $row;
    }
}
