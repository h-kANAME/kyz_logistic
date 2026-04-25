<?php
// =============================================================
// KYZ Logística – Modelo Usuario
// =============================================================

class Usuario
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
            'SELECT id, nombre, email, rol, activo, created_at FROM usuarios WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, email, password_hash, rol, activo FROM usuarios WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lista usuarios con filtro opcional de rol.
     * Supervisores solo ven sus consultores (no implementamos la relación aquí,
     * el filtrado lo hace el controller según el contexto de auth).
     */
    public function findAll(?string $rol = null): array
    {
        $sql    = 'SELECT id, nombre, email, rol, activo, created_at FROM usuarios';
        $params = [];

        if ($rol !== null) {
            $sql   .= ' WHERE rol = :rol';
            $params = [':rol' => $rol];
        }

        $sql .= ' ORDER BY nombre ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByRoles(array $roles): array
    {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, nombre, email, rol, activo FROM usuarios WHERE rol IN ($placeholders) ORDER BY nombre ASC"
        );
        $stmt->execute($roles);
        return $stmt->fetchAll();
    }

    // ── Mutaciones ───────────────────────────────────────────

    public function create(string $nombre, string $email, string $password, string $rol): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (:nombre, :email, :hash, :rol)'
        );
        $stmt->execute([
            ':nombre' => $nombre,
            ':email'  => $email,
            ':hash'   => password_hash($password, PASSWORD_BCRYPT),
            ':rol'    => $rol,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $fields): bool
    {
        $allowed = ['nombre', 'email', 'rol', 'activo'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $fields)) {
                $sets[]          = "$field = :$field";
                $params[":$field"] = $fields[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET password_hash = :hash WHERE id = :id'
        );
        $stmt->execute([
            ':hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':id'   => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET activo = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM usuarios WHERE email = :email';
        $params = [':email' => $email];

        if ($excludeId !== null) {
            $sql   .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}
