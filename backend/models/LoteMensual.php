<?php

class LoteMensual
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(int $anio, int $mes, int $numeroLote, string $titulo, ?string $observacion, int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO lotes_mensuales (anio, mes, numero_lote, titulo, observacion, created_by)
             VALUES (:anio, :mes, :numero_lote, :titulo, :observacion, :created_by)'
        );
        $stmt->execute([
            ':anio' => $anio,
            ':mes' => $mes,
            ':numero_lote' => $numeroLote,
            ':titulo' => $titulo,
            ':observacion' => $observacion,
            ':created_by' => $createdBy,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findAllByPeriod(int $anio, int $mes): array
    {
        $stmt = $this->db->prepare(
            'SELECT lm.*,
                    (SELECT COUNT(*) FROM lote_domicilios ld WHERE ld.lote_id = lm.id) AS total_domicilios
               FROM lotes_mensuales lm
              WHERE lm.anio = :anio AND lm.mes = :mes
              ORDER BY lm.numero_lote ASC'
        );
        $stmt->execute([':anio' => $anio, ':mes' => $mes]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM lotes_mensuales WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function replaceDomicilios(int $loteId, array $domicilioIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $domicilioIds), fn($id) => $id > 0)));
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM lote_domicilios WHERE lote_id = :lote_id');
            $del->execute([':lote_id' => $loteId]);

            if (empty($ids)) {
                $this->db->commit();
                return 0;
            }

            $ins = $this->db->prepare(
                'INSERT INTO lote_domicilios (lote_id, domicilio_id, orden_base)
                 VALUES (:lote_id, :domicilio_id, :orden_base)'
            );
            $count = 0;
            foreach (array_values($ids) as $idx => $domicilioId) {
                $ins->execute([
                    ':lote_id' => $loteId,
                    ':domicilio_id' => $domicilioId,
                    ':orden_base' => $idx + 1,
                ]);
                $count++;
            }
            $this->db->commit();
            return $count;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function linkDomicilios(int $loteId, array $domicilioIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $domicilioIds), fn($id) => $id > 0)));
        if (empty($ids)) {
            return 0;
        }

        $stmtMax = $this->db->prepare('SELECT COALESCE(MAX(orden_base), 0) FROM lote_domicilios WHERE lote_id = :lote_id');
        $stmtMax->execute([':lote_id' => $loteId]);
        $base = (int)$stmtMax->fetchColumn();

        $ins = $this->db->prepare(
            'INSERT IGNORE INTO lote_domicilios (lote_id, domicilio_id, orden_base)
             VALUES (:lote_id, :domicilio_id, :orden_base)'
        );

        $count = 0;
        foreach ($ids as $idx => $domicilioId) {
            $ins->execute([
                ':lote_id' => $loteId,
                ':domicilio_id' => $domicilioId,
                ':orden_base' => $base + $idx + 1,
            ]);
            $count += $ins->rowCount();
        }
        return $count;
    }

    public function domicilioIds(int $loteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT domicilio_id
               FROM lote_domicilios
              WHERE lote_id = :lote_id
              ORDER BY orden_base ASC, id ASC'
        );
        $stmt->execute([':lote_id' => $loteId]);
        return array_map(fn($r) => (int)$r['domicilio_id'], $stmt->fetchAll());
    }
}
