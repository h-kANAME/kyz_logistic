<?php
// =============================================================
// KYZ Logística – ImportController
// POST /api/import/domicilios   (Admin)
// =============================================================

class ImportController
{
    private Domicilio $domicilioModel;
    private Seccion   $seccionModel;
    private LoteMensual $loteModel;

    public function __construct()
    {
        $this->domicilioModel = new Domicilio();
        $this->seccionModel   = new Seccion();
        $this->loteModel      = new LoteMensual();
    }

    /**
     * POST /api/import/domicilios
     * Sube y procesa un archivo .xlsx con el formato de data/direcciones.xlsx.
     * Solo Admin.
     *
     * Multipart form-data: file = archivo xlsx
     * Query param: sheet=0 (índice de hoja, default 0)
     */
    public function domicilios(Request $request): void
    {
        $loteId = (int)$request->query('lote_id', 0);
        if ($loteId <= 0) {
            Response::error('Debe indicar lote_id para importar direcciones.', 422);
        }
        $lote = $this->loteModel->findById($loteId);
        if (!$lote) {
            Response::notFound('Lote no encontrado.');
        }

        if (empty($_FILES['file'])) {
            Response::error('No se recibió el archivo.', 422);
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Error al subir el archivo (código: ' . $file['error'] . ').', 400);
        }

        $maxBytes = UPLOAD_MAX_MB * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            Response::error("El archivo supera el tamaño máximo de " . UPLOAD_MAX_MB . " MB.", 413);
        }

        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            Response::error('Solo se aceptan archivos .xlsx.', 415);
        }

        // Mover a directorio de uploads
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0750, true);
        }

        $destName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
        $destPath = UPLOAD_DIR . $destName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::error('No se pudo guardar el archivo en el servidor.', 500);
        }

        // Procesar
        try {
            $sheetIndex = (int)($request->query('sheet', 0));
            $result     = $this->processXlsx($destPath, $sheetIndex);
        } catch (RuntimeException $e) {
            Response::error('Error procesando el archivo: ' . $e->getMessage(), 422);
        }

        $linked = $this->loteModel->linkDomicilios($loteId, $result['inserted_ids'] ?? []);
        $result['lote_id'] = $loteId;
        $result['linked_to_lote'] = $linked;

        // Registrar importación en auditoría
        $this->logImport(
            AuthContext::id(),
            $file['name'],
            $result['inserted'],
            $result['errors']
        );

        Response::success($result, 'Importación completada y vinculada al lote.', 201);
    }

    // ── Procesamiento interno ─────────────────────────────────

    private function processXlsx(string $path, int $sheetIndex): array
    {
        $reader = new XlsxReader($path);
        // skipRows=1 para saltar la fila de encabezados
        $rows = $reader->getRows($sheetIndex, 1);

        if (empty($rows)) {
            throw new RuntimeException('La hoja no contiene datos.');
        }

        // Cachear mapa de secciones: numero → id
        $secciones = $this->seccionModel->findAll();
        $secMap    = array_column($secciones, 'id', 'numero');

        $domicilios = [];
        $skipped    = 0;

        foreach ($rows as $row) {
            // Soporta 2 formatos:
            // A) legacy: [_, calle(1), altura(2), seccion(3), provincia(4), pais(5), servicio(6)]
            // B) completo exportado: [id(0), calle(1), altura(2), seccion(3), provincia(4), pais(5), servicio(6), lat(7), lng(8), geocodificado(9), created_at(10)]
            $calle = trim((string)($row[1] ?? ''));
            $altura = $row[2] ?? null;
            $seccion = isset($row[3]) ? (int)$row[3] : null;
            $provincia = trim((string)($row[4] ?? 'Santa Fe'));
            $pais = trim((string)($row[5] ?? 'Argentina'));
            $servicio = trim((string)($row[6] ?? ''));
            $latitudRaw = $row[7] ?? null;
            $longitudRaw = $row[8] ?? null;
            $geocodificadoRaw = $row[9] ?? null;
            $createdAtRaw = $row[10] ?? null;

            if ($calle === '' || $altura === null || $seccion === null) {
                $skipped++;
                continue;
            }

            if (!isset($secMap[$seccion])) {
                // Crear sección si no existe
                $db   = Database::getInstance();
                $stmt = $db->prepare('INSERT IGNORE INTO secciones (numero) VALUES (:n)');
                $stmt->execute([':n' => $seccion]);
                // Refrescar mapa
                $secObj = $this->seccionModel->findByNumero($seccion);
                if ($secObj) {
                    $secMap[$seccion] = $secObj['id'];
                } else {
                    $skipped++;
                    continue;
                }
            }

            $servicioNorm = $this->normalizeServicio($servicio);
            if ($servicioNorm === null) {
                $skipped++;
                continue;
            }

            $domicilios[] = [
                'calle'      => $calle,
                'altura'     => (int)$altura,
                'seccion_id' => $secMap[$seccion],
                'provincia'  => $provincia ?: 'Santa Fe',
                'pais'       => $pais      ?: 'Argentina',
                'servicio'   => $servicioNorm,
                'latitud'    => $this->nullableFloat($latitudRaw),
                'longitud'   => $this->nullableFloat($longitudRaw),
                'geocodificado' => $this->normalizeGeocodificado($geocodificadoRaw),
                'created_at' => $this->normalizeCreatedAt($createdAtRaw),
            ];
        }

        $result         = $this->domicilioModel->bulkInsert($domicilios);
        $result['skipped'] = $skipped;
        return $result;
    }

    private function normalizeServicio(string $value): ?string
    {
        $map = [
            'gas natural'     => 'Gas Natural',
            'gasnatural'      => 'Gas Natural',
            'gas'             => 'Gas Natural',
            'servicio social' => 'Servicio Social',
            'serviciosocial'  => 'Servicio Social',
            'social'          => 'Servicio Social',
        ];
        return $map[strtolower($value)] ?? null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $txt = trim((string)$value);
        if ($txt === '') {
            return null;
        }
        if (!is_numeric($txt)) {
            return null;
        }
        return (float)$txt;
    }

    private function normalizeGeocodificado(mixed $value): int
    {
        if ($value === null || trim((string)$value) === '') {
            return 0;
        }
        return ((int)$value) === 1 ? 1 : 0;
    }

    private function normalizeCreatedAt(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $txt = trim((string)$value);
        if ($txt === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $txt)) {
            return strlen($txt) === 10 ? $txt . ' 00:00:00' : $txt;
        }
        return null;
    }

    private function logImport(int $userId, string $fileName, int $total, int $errors): void
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'INSERT INTO importaciones (usuario_id, nombre_archivo, total_registros, errores)
                 VALUES (:uid, :file, :total, :errors)'
            );
            $stmt->execute([
                ':uid'    => $userId,
                ':file'   => $fileName,
                ':total'  => $total,
                ':errors' => $errors,
            ]);
        } catch (PDOException) {
            // El fallo de auditoría no debe cortar el flujo principal
        }
    }
}
