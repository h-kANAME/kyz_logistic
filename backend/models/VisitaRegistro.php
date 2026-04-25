<?php

class VisitaRegistro
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function upsert(int $hojaRutaId, int $domicilioId, bool $visitado, bool $documentoFirmado, int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO visitas_registro (hoja_ruta_id, domicilio_id, visitado, documento_firmado, created_by)
             VALUES (:hoja_ruta_id, :domicilio_id, :visitado, :documento_firmado, :created_by)
             ON DUPLICATE KEY UPDATE
               visitado = VALUES(visitado),
               documento_firmado = VALUES(documento_firmado),
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':hoja_ruta_id' => $hojaRutaId,
            ':domicilio_id' => $domicilioId,
            ':visitado' => $visitado ? 1 : 0,
            ':documento_firmado' => $documentoFirmado ? 1 : 0,
            ':created_by' => $createdBy,
        ]);

        $sel = $this->db->prepare('SELECT id FROM visitas_registro WHERE hoja_ruta_id = :hoja_ruta_id AND domicilio_id = :domicilio_id');
        $sel->execute([':hoja_ruta_id' => $hojaRutaId, ':domicilio_id' => $domicilioId]);
        return (int)$sel->fetchColumn();
    }

    public function addObservacion(int $visitaRegistroId, string $observacion, int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO visitas_observaciones (visita_registro_id, observacion, created_by)
             VALUES (:visita_registro_id, :observacion, :created_by)'
        );
        $stmt->execute([
            ':visita_registro_id' => $visitaRegistroId,
            ':observacion' => $observacion,
            ':created_by' => $createdBy,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findByHojaAndDomicilio(int $hojaRutaId, int $domicilioId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM visitas_registro WHERE hoja_ruta_id = :hoja_ruta_id AND domicilio_id = :domicilio_id'
        );
        $stmt->execute([
            ':hoja_ruta_id' => $hojaRutaId,
            ':domicilio_id' => $domicilioId,
        ]);
        return $stmt->fetch() ?: null;
    }

    public function findByHoja(int $hojaRutaId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM visitas_registro WHERE hoja_ruta_id = :hoja_ruta_id');
        $stmt->execute([':hoja_ruta_id' => $hojaRutaId]);
        return $stmt->fetchAll();
    }

    public function historyByDomicilio(int $domicilioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT vr.id, vr.hoja_ruta_id, vr.domicilio_id, vr.visitado, vr.documento_firmado, vr.created_at, vr.updated_at,
                    vo.id AS observacion_id, vo.observacion, vo.created_at AS observacion_created_at,
                    hr.titulo AS hoja_titulo
               FROM visitas_registro vr
               LEFT JOIN visitas_observaciones vo ON vo.visita_registro_id = vr.id
               LEFT JOIN hojas_ruta hr ON hr.id = vr.hoja_ruta_id
              WHERE vr.domicilio_id = :domicilio_id
              ORDER BY vr.created_at DESC, vo.created_at DESC'
        );
        $stmt->execute([':domicilio_id' => $domicilioId]);
        return $stmt->fetchAll();
    }
}
