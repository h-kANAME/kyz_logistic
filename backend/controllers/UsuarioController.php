<?php
// =============================================================
// KYZ Logística – UsuarioController
// GET    /api/usuarios
// POST   /api/usuarios
// GET    /api/usuarios/{id}
// PUT    /api/usuarios/{id}
// DELETE /api/usuarios/{id}
// PATCH  /api/usuarios/{id}/password
// =============================================================

class UsuarioController
{
    private Usuario $model;

    public function __construct()
    {
        $this->model = new Usuario();
    }

    /** GET /api/usuarios  –  Admin: todos | Supervisor: solo consultores */
    public function index(Request $request): void
    {
        $auth = AuthContext::get();

        if ($auth['rol'] === 'admin') {
            $rol = $request->query('rol');
            $usuarios = $rol
                ? $this->model->findAll($rol)
                : $this->model->findAll();
        } elseif ($auth['rol'] === 'supervisor') {
            $usuarios = $this->model->findAll('consultor');
        } else {
            Response::forbidden();
        }

        Response::success($usuarios);
    }

    /** POST /api/usuarios  –  Admin */
    public function store(Request $request): void
    {
        $error = $request->validate(['nombre', 'email', 'password', 'rol']);
        if ($error) {
            Response::error($error, 422);
        }

        $nombre   = trim((string)$request->input('nombre'));
        $email    = strtolower(trim((string)$request->input('email')));
        $password = (string)$request->input('password');
        $rol      = (string)$request->input('rol');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('El email no es válido.', 422);
        }

        if (!in_array($rol, ['admin', 'supervisor', 'consultor'], true)) {
            Response::error('Rol inválido. Use: admin, supervisor, consultor.', 422);
        }

        if (strlen($password) < 8) {
            Response::error('La contraseña debe tener al menos 8 caracteres.', 422);
        }

        if ($this->model->emailExists($email)) {
            Response::error('El email ya está registrado.', 409);
        }

        $id = $this->model->create($nombre, $email, $password, $rol);
        Response::success(['id' => $id], 'Usuario creado', 201);
    }

    /** GET /api/usuarios/{id} */
    public function show(Request $request): void
    {
        $id   = (int)$request->param('id');
        $auth = AuthContext::get();

        // Consultor solo puede verse a sí mismo
        if ($auth['rol'] === 'consultor' && $auth['sub'] !== $id) {
            Response::forbidden();
        }

        $usuario = $this->model->findById($id);
        if (!$usuario) {
            Response::notFound('Usuario no encontrado.');
        }

        Response::success($usuario);
    }

    /** PUT /api/usuarios/{id}  –  Admin o el propio usuario */
    public function update(Request $request): void
    {
        $id   = (int)$request->param('id');
        $auth = AuthContext::get();

        if ($auth['rol'] === 'consultor' && $auth['sub'] !== $id) {
            Response::forbidden();
        }

        $fields = array_filter([
            'nombre' => $request->input('nombre'),
            'email'  => $request->input('email'),
            'rol'    => ($auth['rol'] === 'admin') ? $request->input('rol') : null,
            'activo' => ($auth['rol'] === 'admin') ? $request->input('activo') : null,
        ], fn($v) => $v !== null);

        if (isset($fields['email'])) {
            $fields['email'] = strtolower(trim($fields['email']));
            if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
                Response::error('El email no es válido.', 422);
            }
            if ($this->model->emailExists($fields['email'], $id)) {
                Response::error('El email ya está en uso.', 409);
            }
        }

        $this->model->update($id, $fields);
        Response::success(null, 'Usuario actualizado');
    }

    /** DELETE /api/usuarios/{id}  –  Admin (desactiva, no borra) */
    public function destroy(Request $request): void
    {
        $id   = (int)$request->param('id');
        $auth = AuthContext::get();

        if ($auth['sub'] === $id) {
            Response::error('No puedes desactivar tu propia cuenta.', 400);
        }

        $this->model->deactivate($id);
        Response::success(null, 'Usuario desactivado');
    }

    /** PATCH /api/usuarios/{id}/password  –  Admin o el propio usuario */
    public function updatePassword(Request $request): void
    {
        $id   = (int)$request->param('id');
        $auth = AuthContext::get();

        if ($auth['rol'] === 'consultor' && $auth['sub'] !== $id) {
            Response::forbidden();
        }

        $error = $request->validate(['password']);
        if ($error) {
            Response::error($error, 422);
        }

        $password = (string)$request->input('password');
        if (strlen($password) < 8) {
            Response::error('La contraseña debe tener al menos 8 caracteres.', 422);
        }

        $this->model->updatePassword($id, $password);
        Response::success(null, 'Contraseña actualizada');
    }
}
