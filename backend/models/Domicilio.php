<?php
// =============================================================
// KYZ Logística – Modelo Domicilio
// =============================================================

class Domicilio
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
            'SELECT d.*, s.numero AS seccion_numero
               FROM domicilios d
               JOIN secciones  s ON s.id = d.seccion_id
              WHERE d.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Listado con filtros opcionales: seccion_id, servicio, busqueda por calle.
     * Ordenado para ruteo: sección → calle → altura.
     */
    public function findAll(array $filters = [], int $limit = 500, int $offset = 0): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['seccion_id'])) {
            $where[]              = 'd.seccion_id = :seccion_id';
            $params[':seccion_id'] = (int)$filters['seccion_id'];
        }

        if (!empty($filters['servicio'])) {
            $where[]            = 'd.servicio = :servicio';
            $params[':servicio'] = $filters['servicio'];
        }

        if (!empty($filters['calle'])) {
            $where[]         = 'd.calle LIKE :calle';
            $params[':calle'] = '%' . $filters['calle'] . '%';
        }

        if (isset($filters['geocodificado'])) {
            $where[]                  = 'd.geocodificado = :geocodificado';
            $params[':geocodificado'] = (int)$filters['geocodificado'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare(
            "SELECT d.id, d.calle, d.altura, d.seccion_id, s.numero AS seccion_numero,
                    d.servicio, d.latitud, d.longitud, d.geocodificado
               FROM domicilios d
               JOIN secciones  s ON s.id = d.seccion_id
              $whereClause
              ORDER BY s.numero ASC, d.calle ASC, d.altura ASC
              LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Todos los domicilios para export (sin paginar).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAllForExport(): array
    {
        $stmt = $this->db->query(
            'SELECT d.id, d.calle, d.altura, s.numero AS seccion_numero, d.provincia, d.pais, d.servicio,
                    d.latitud, d.longitud, d.geocodificado, d.created_at
               FROM domicilios d
               JOIN secciones s ON s.id = d.seccion_id
              ORDER BY s.numero ASC, d.calle ASC, d.altura ASC'
        );
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $where  = [];
        $params = [];

        if (!empty($filters['seccion_id'])) {
            $where[]              = 'seccion_id = :seccion_id';
            $params[':seccion_id'] = (int)$filters['seccion_id'];
        }
        if (!empty($filters['servicio'])) {
            $where[]            = 'servicio = :servicio';
            $params[':servicio'] = $filters['servicio'];
        }

        if (isset($filters['geocodificado'])) {
            $where[]                  = 'geocodificado = :geocodificado';
            $params[':geocodificado'] = (int)$filters['geocodificado'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM domicilios $whereClause");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── Mutaciones ───────────────────────────────────────────

    public function create(array $data): int
    {
        $hasGeo = array_key_exists('latitud', $data) && array_key_exists('longitud', $data);
        $hasCreatedAt = array_key_exists('created_at', $data) && $data['created_at'] !== null && trim((string)$data['created_at']) !== '';

        $columns = ['calle', 'altura', 'seccion_id', 'provincia', 'pais', 'servicio'];
        $values = [':calle', ':altura', ':seccion_id', ':provincia', ':pais', ':servicio'];
        if ($hasGeo) {
            $columns[] = 'latitud';
            $columns[] = 'longitud';
            $columns[] = 'geocodificado';
            $values[] = ':latitud';
            $values[] = ':longitud';
            $values[] = ':geocodificado';
        }
        if ($hasCreatedAt) {
            $columns[] = 'created_at';
            $values[] = ':created_at';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO domicilios (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')'
        );
        $params = [
            ':calle'      => $data['calle'],
            ':altura'     => (int)$data['altura'],
            ':seccion_id' => (int)$data['seccion_id'],
            ':provincia'  => $data['provincia'] ?? 'Santa Fe',
            ':pais'       => $data['pais']      ?? 'Argentina',
            ':servicio'   => $data['servicio'],
        ];
        if ($hasGeo) {
            $params[':latitud'] = (float)$data['latitud'];
            $params[':longitud'] = (float)$data['longitud'];
            $params[':geocodificado'] = isset($data['geocodificado']) ? (int)$data['geocodificado'] : 1;
        }
        if ($hasCreatedAt) {
            $params[':created_at'] = (string)$data['created_at'];
        }
        $stmt->execute($params);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Inserta múltiples domicilios en una sola transacción.
     * Retorna [inserted, errors].
     */
    public function bulkInsert(array $rows): array
    {
        $inserted = 0;
        $errors   = 0;
        $insertedIds = [];

        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                try {
                    $hasGeo = array_key_exists('latitud', $row) && array_key_exists('longitud', $row)
                        && $row['latitud'] !== null && $row['longitud'] !== null;
                    $hasCreatedAt = array_key_exists('created_at', $row) && $row['created_at'] !== null && trim((string)$row['created_at']) !== '';

                    $columns = ['calle', 'altura', 'seccion_id', 'provincia', 'pais', 'servicio'];
                    $values = [':calle', ':altura', ':seccion_id', ':provincia', ':pais', ':servicio'];
                    if ($hasGeo) {
                        $columns[] = 'latitud';
                        $columns[] = 'longitud';
                        $columns[] = 'geocodificado';
                        $values[] = ':latitud';
                        $values[] = ':longitud';
                        $values[] = ':geocodificado';
                    }
                    if ($hasCreatedAt) {
                        $columns[] = 'created_at';
                        $values[] = ':created_at';
                    }
                    $stmt = $this->db->prepare(
                        'INSERT INTO domicilios (' . implode(', ', $columns) . ')
                         VALUES (' . implode(', ', $values) . ')'
                    );

                    $params = [
                        ':calle'      => $row['calle'],
                        ':altura'     => (int)$row['altura'],
                        ':seccion_id' => (int)$row['seccion_id'],
                        ':provincia'  => $row['provincia'] ?? 'Santa Fe',
                        ':pais'       => $row['pais']      ?? 'Argentina',
                        ':servicio'   => $row['servicio'],
                    ];
                    if ($hasGeo) {
                        $params[':latitud'] = (float)$row['latitud'];
                        $params[':longitud'] = (float)$row['longitud'];
                        $params[':geocodificado'] = isset($row['geocodificado']) ? (int)$row['geocodificado'] : 1;
                    }
                    if ($hasCreatedAt) {
                        $params[':created_at'] = (string)$row['created_at'];
                    }

                    $stmt->execute($params);
                    $inserted++;
                    $insertedIds[] = (int)$this->db->lastInsertId();
                } catch (PDOException) {
                    $errors++;
                }
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['inserted' => $inserted, 'errors' => $errors, 'inserted_ids' => $insertedIds];
    }

    public function updateGeocoords(int $id, float $lat, float $lng, int $geocodificado = 1): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE domicilios SET latitud = :lat, longitud = :lng, geocodificado = :geocodificado WHERE id = :id'
        );
        $stmt->execute([
            ':lat' => $lat,
            ':lng' => $lng,
            ':geocodificado' => $geocodificado,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function clearGeocodingData(bool $onlyGeocoded = false): int
    {
        $sql = 'UPDATE domicilios
                   SET latitud = NULL,
                       longitud = NULL,
                       geocodificado = 0';
        if ($onlyGeocoded) {
            $sql .= ' WHERE geocodificado = 1';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function findForWizard(int $limit = 25, int $offset = 0, bool $onlyPending = false): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where = $onlyPending ? 'WHERE d.geocodificado = 0' : '';

        $stmt = $this->db->prepare(
            "SELECT d.id, d.calle, d.altura, d.seccion_id, s.numero AS seccion_numero,
                    d.provincia, d.pais, d.servicio, d.latitud, d.longitud, d.geocodificado
               FROM domicilios d
               JOIN secciones s ON s.id = d.seccion_id
              $where
              ORDER BY d.geocodificado ASC, s.numero ASC, d.calle ASC, d.altura ASC
              LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findForWizardByLote(int $loteId, int $limit = 25, int $offset = 0, bool $onlyPending = false): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $wherePending = $onlyPending ? ' AND d.geocodificado = 0' : '';

        $stmt = $this->db->prepare(
            "SELECT d.id, d.calle, d.altura, d.seccion_id, s.numero AS seccion_numero,
                    d.provincia, d.pais, d.servicio, d.latitud, d.longitud, d.geocodificado
               FROM lote_domicilios ld
               JOIN domicilios d ON d.id = ld.domicilio_id
               JOIN secciones s ON s.id = d.seccion_id
              WHERE ld.lote_id = :lote_id
                $wherePending
              ORDER BY d.geocodificado ASC, ld.orden_base ASC, d.calle ASC, d.altura ASC
              LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':lote_id', $loteId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countByLote(int $loteId, bool $onlyGeocodificados = false): int
    {
        $whereGeo = $onlyGeocodificados ? ' AND d.geocodificado = 1' : '';
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
               FROM lote_domicilios ld
               JOIN domicilios d ON d.id = ld.domicilio_id
              WHERE ld.lote_id = :lote_id
                $whereGeo"
        );
        $stmt->execute([':lote_id' => $loteId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT d.id, d.calle, d.altura, d.seccion_id, s.numero AS seccion_numero,
                    d.provincia, d.pais, d.servicio, d.latitud, d.longitud, d.geocodificado
               FROM domicilios d
               JOIN secciones s ON s.id = d.seccion_id
              WHERE d.id IN ($placeholders)
              ORDER BY d.id ASC"
        );
        foreach ($ids as $idx => $id) {
            $stmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function bulkUpdateGeocoords(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'UPDATE domicilios
                SET latitud = :lat,
                    longitud = :lng,
                    geocodificado = 1
              WHERE id = :id'
        );

        $updated = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute([
                    ':lat' => (float)$row['latitud'],
                    ':lng' => (float)$row['longitud'],
                    ':id' => (int)$row['id'],
                ]);
                $updated += $stmt->rowCount();
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $updated;
    }

    public function findPendingGeocoding(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $this->db->prepare(
            'SELECT d.id, d.calle, d.altura, d.seccion_id, s.numero AS seccion_numero,
                    d.provincia, d.pais, d.servicio
               FROM domicilios d
               JOIN secciones s ON s.id = d.seccion_id
              WHERE d.geocodificado = 0
              ORDER BY s.numero ASC, d.calle ASC, d.altura ASC
              LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function fillPendingWithSectionCentroids(): int
    {
        $stmt = $this->db->prepare(
            'UPDATE domicilios d
               JOIN (
                    SELECT seccion_id, AVG(latitud) AS lat, AVG(longitud) AS lng
                      FROM domicilios
                     WHERE geocodificado = 1
                     GROUP BY seccion_id
               ) c ON c.seccion_id = d.seccion_id
              SET d.latitud = c.lat,
                  d.longitud = c.lng,
                  d.geocodificado = 1
             WHERE d.geocodificado = 0'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function fillPendingWithGlobalPoint(float $lat, float $lng): int
    {
        $stmt = $this->db->prepare(
            'UPDATE domicilios
                SET latitud = :lat,
                    longitud = :lng,
                    geocodificado = 1
              WHERE geocodificado = 0'
        );
        $stmt->execute([':lat' => $lat, ':lng' => $lng]);
        return $stmt->rowCount();
    }
}
