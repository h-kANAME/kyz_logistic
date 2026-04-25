<?php
// =============================================================
// KYZ Logística – SeccionController
// GET  /api/secciones
// GET  /api/secciones/{id}
// PUT  /api/secciones/{id}   (Admin: editar descripción)
// =============================================================

class SeccionController
{
    private Seccion $model;

    public function __construct()
    {
        $this->model = new Seccion();
    }

    /** GET /api/secciones */
    public function index(Request $request): void
    {
        Response::success($this->model->findAll());
    }

    /** GET /api/secciones/{id} */
    public function show(Request $request): void
    {
        $id      = (int)$request->param('id');
        $seccion = $this->model->findById($id);

        if (!$seccion) {
            Response::notFound('Sección no encontrada.');
        }

        Response::success($seccion);
    }

    /** PUT /api/secciones/{id}  –  Admin */
    public function update(Request $request): void
    {
        $id          = (int)$request->param('id');
        $descripcion = trim((string)$request->input('descripcion', ''));

        if ($descripcion === '') {
            Response::error('La descripción no puede estar vacía.', 422);
        }

        $this->model->updateDescripcion($id, $descripcion);
        Response::success(null, 'Sección actualizada');
    }
}
