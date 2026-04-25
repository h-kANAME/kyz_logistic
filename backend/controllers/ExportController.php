<?php
// =============================================================
// KYZ Logística – ExportController
// GET /api/export/domicilios/xlsx  (Admin)
// =============================================================

class ExportController
{
    private Domicilio $domicilioModel;

    public function __construct()
    {
        $this->domicilioModel = new Domicilio();
    }

    public function domiciliosXlsx(Request $request): void
    {
        $rows = $this->domicilioModel->findAllForExport();

        $data = [];
        $data[] = [
            'ID',
            'Calle',
            'Altura',
            'Seccion',
            'Provincia',
            'Pais',
            'Servicio',
            'Latitud',
            'Longitud',
            'Geocodificado',
            'Creado',
        ];

        foreach ($rows as $r) {
            $data[] = [
                (int)$r['id'],
                (string)$r['calle'],
                (int)$r['altura'],
                (int)$r['seccion_numero'],
                (string)$r['provincia'],
                (string)$r['pais'],
                (string)$r['servicio'],
                $r['latitud'] !== null ? (float)$r['latitud'] : null,
                $r['longitud'] !== null ? (float)$r['longitud'] : null,
                (int)$r['geocodificado'],
                isset($r['created_at']) ? (string)$r['created_at'] : '',
            ];
        }

        $writer = new XlsxWriter();
        $binary = $writer->toString($data, 'Domicilios');
        $name = 'domicilios_' . date('Y-m-d_His') . '.xlsx';
        Response::download($binary, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
