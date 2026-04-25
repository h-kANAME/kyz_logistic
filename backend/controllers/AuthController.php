<?php
// =============================================================
// KYZ Logística – AuthController
// POST /api/auth/login
// GET  /api/auth/me
// =============================================================

class AuthController
{
    private Usuario $model;

    public function __construct()
    {
        $this->model = new Usuario();
    }

    /** POST /api/auth/login */
    public function login(Request $request): void
    {
        $error = $request->validate(['email', 'password']);
        if ($error) {
            Response::error($error, 422);
        }

        $email    = trim((string)$request->input('email'));
        $password = (string)$request->input('password');

        $usuario = $this->model->findByEmail($email);

        if (!$usuario || !$this->model->verifyPassword($password, $usuario['password_hash'])) {
            Response::error('Credenciales incorrectas.', 401);
        }

        if (!(bool)$usuario['activo']) {
            Response::error('Usuario inactivo. Contacte al administrador.', 403);
        }

        $token = JWT::createToken($usuario['id'], $usuario['rol'], $usuario['nombre']);

        Response::success([
            'token'  => $token,
            'expiry' => JWT_EXPIRY,
            'user'   => [
                'id'     => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email'  => $usuario['email'],
                'rol'    => $usuario['rol'],
            ],
        ], 'Login exitoso');
    }

    /** GET /api/auth/me */
    public function me(Request $request): void
    {
        $auth    = AuthContext::get();
        $usuario = $this->model->findById($auth['sub']);

        if (!$usuario) {
            Response::notFound('Usuario no encontrado.');
        }

        Response::success($usuario);
    }
}
