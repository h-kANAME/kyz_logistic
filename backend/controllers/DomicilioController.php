<?php
// =============================================================
// KYZ Logística – DomicilioController
// GET  /api/domicilios
// GET  /api/domicilios/{id}
// =============================================================

class DomicilioController
{
    private Domicilio $model;

    public function __construct()
    {
        $this->model = new Domicilio();
    }

    /** GET /api/domicilios?seccion_id=&servicio=&calle=&page=&per_page= */
    public function index(Request $request): void
    {
        $filters = [
            'seccion_id' => $request->query('seccion_id'),
            'servicio'   => $request->query('servicio'),
            'calle'      => $request->query('calle'),
            'geocodificado' => $request->query('geocodificado'),
        ];

        $perPage = max(1, min(500, (int)($request->query('per_page', 100))));
        $page    = max(1, (int)($request->query('page', 1)));
        $offset  = ($page - 1) * $perPage;

        $total  = $this->model->count($filters);
        $items  = $this->model->findAll($filters, $perPage, $offset);

        Response::success([
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / $perPage),
        ]);
    }

    /** GET /api/domicilios/{id} */
    public function show(Request $request): void
    {
        $id        = (int)$request->param('id');
        $domicilio = $this->model->findById($id);

        if (!$domicilio) {
            Response::notFound('Domicilio no encontrado.');
        }

        Response::success($domicilio);
    }
}
