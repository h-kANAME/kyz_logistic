<?php
// =============================================================
// KYZ Logística – Modelo Seccion
// =============================================================

class Seccion
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        return $this->db
            ->query('SELECT id, numero, descripcion FROM secciones ORDER BY numero ASC')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, numero, descripcion FROM secciones WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByNumero(int $numero): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, numero, descripcion FROM secciones WHERE numero = :numero'
        );
        $stmt->execute([':numero' => $numero]);
        return $stmt->fetch() ?: null;
    }

    public function updateDescripcion(int $id, string $descripcion): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE secciones SET descripcion = :descripcion WHERE id = :id'
        );
        $stmt->execute([':descripcion' => $descripcion, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
