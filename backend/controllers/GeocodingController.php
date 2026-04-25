<?php
// =============================================================
// KYZ Logistica – GeocodingController
// GET  /api/geocoding/domicilios/estado
// POST /api/geocoding/domicilios/lote
// POST /api/geocoding/domicilios/fallback
// POST /api/geocoding/domicilios/reset
// GET  /api/geocoding/domicilios/wizard
// POST /api/geocoding/domicilios/wizard/bulk-propose
// POST /api/geocoding/domicilios/wizard/bulk-save
// POST /api/geocoding/domicilios/wizard/{id}/attempt
// POST /api/geocoding/domicilios/wizard/{id}/manual
// POST /api/geocoding/domicilios/wizard/{id}/fallback
// =============================================================

class GeocodingController
{
    private Domicilio $domicilioModel;
    private GeocodingClient $geocodingClient;

    public function __construct()
    {
        $this->domicilioModel = new Domicilio();
        $this->geocodingClient = new GeocodingClient();
    }

    /** GET /api/geocoding/domicilios/estado */
    public function estado(): void
    {
        $loteId = isset($_GET['lote_id']) ? (int)$_GET['lote_id'] : 0;
        if ($loteId > 0) {
            $total = $this->domicilioModel->countByLote($loteId, false);
            $geocodificados = $this->domicilioModel->countByLote($loteId, true);
        } else {
            $total = $this->domicilioModel->count();
            $geocodificados = $this->domicilioModel->count(['geocodificado' => 1]);
        }

        Response::success([
            'total' => $total,
            'geocodificados' => $geocodificados,
            'pendientes' => max(0, $total - $geocodificados),
            'cobertura_pct' => $total > 0 ? round(($geocodificados / $total) * 100, 2) : 0,
            'lote_id' => $loteId > 0 ? $loteId : null,
        ]);
    }

    /** POST /api/geocoding/domicilios/lote */
    public function lote(Request $request): void
    {
        if (!$this->geocodingClient->isConfigured()) {
            Response::error('Geocodificador no configurado en el entorno.', 503);
        }

        $limit = max(1, min(200, (int)$request->input('limit', 50)));
        $sleepMs = max(0, min(5000, (int)$request->input('sleep_ms', GEOCODING_SLEEP_MS)));

        $pendientes = $this->domicilioModel->findPendingGeocoding($limit);
        if (empty($pendientes)) {
            Response::success([
                'processed' => 0,
                'updated' => 0,
                'failed' => 0,
                'failed_ids' => [],
            ], 'No hay domicilios pendientes de geocodificar.');
        }

        $processed = 0;
        $updated = 0;
        $failed = 0;
        $failedIds = [];

        foreach ($pendientes as $idx => $domicilio) {
            $processed++;
            $attempt = $this->proposeForDomicilio($domicilio, true);
            $geo = $attempt['resolved'];

            if ($geo !== null) {
                $this->domicilioModel->updateGeocoords((int)$domicilio['id'], $geo['lat'], $geo['lng']);
                $updated++;
            } else {
                $failed++;
                $failedIds[] = (int)$domicilio['id'];
            }

            if ($sleepMs > 0 && $idx < count($pendientes) - 1) {
                usleep($sleepMs * 1000);
            }
        }

        $total = $this->domicilioModel->count();
        $geocodificados = $this->domicilioModel->count(['geocodificado' => 1]);

        Response::success([
            'processed' => $processed,
            'updated' => $updated,
            'failed' => $failed,
            'failed_ids' => array_slice($failedIds, 0, 50),
            'estado' => [
                'total' => $total,
                'geocodificados' => $geocodificados,
                'pendientes' => max(0, $total - $geocodificados),
                'cobertura_pct' => $total > 0 ? round(($geocodificados / $total) * 100, 2) : 0,
            ],
        ], 'Lote de geocodificacion procesado.');
    }

    /** POST /api/geocoding/domicilios/wizard/bulk-propose */
    public function wizardBulkPropose(Request $request): void
    {
        $provider = strtolower(trim((string)$request->input('provider', GEOCODING_PROVIDER)));
        if (!in_array($provider, ['nominatim', 'google'], true)) {
            Response::error('Proveedor de geocoding invalido. Use: nominatim o google.', 422);
        }

        if (!$this->geocodingClient->isConfigured($provider)) {
            Response::error('Geocodificador no configurado en el entorno.', 503);
        }

        $ids = array_map('intval', (array)$request->input('domicilio_ids', []));
        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
        if (empty($ids)) {
            Response::error('Debe enviar domicilio_ids.', 422);
        }
        if (count($ids) > 10) {
            Response::error('El geocoding masivo soporta hasta 10 domicilios por lote.', 422);
        }

        $domicilios = $this->domicilioModel->findByIds($ids);
        $items = [];
        foreach ($domicilios as $domicilio) {
            $attempt = $this->proposeForDomicilio($domicilio, true, $provider);
            $resolved = $attempt['resolved'];
            $items[] = [
                'id' => (int)$domicilio['id'],
                'calle' => (string)$domicilio['calle'],
                'altura' => (int)$domicilio['altura'],
                'seccion_numero' => (int)$domicilio['seccion_numero'],
                'proposed_latitud' => $resolved['lat'] ?? null,
                'proposed_longitud' => $resolved['lng'] ?? null,
                'matched' => $resolved !== null,
                'attempts' => $attempt['attempts'],
            ];
        }

        Response::success([
            'items' => $items,
            'count' => count($items),
            'provider' => $provider,
        ], 'Propuestas masivas generadas.');
    }

    /** POST /api/geocoding/domicilios/wizard/bulk-save */
    public function wizardBulkSave(Request $request): void
    {
        $rows = (array)$request->input('rows', []);
        if (empty($rows)) {
            Response::error('Debe enviar rows para guardar.', 422);
        }
        if (count($rows) > 10) {
            Response::error('Solo se pueden guardar hasta 10 domicilios por lote.', 422);
        }

        $normalized = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            $lat = $row['latitud'] ?? null;
            $lng = $row['longitud'] ?? null;
            if ($id <= 0 || $lat === null || $lng === null) {
                Response::error('Cada fila debe incluir id, latitud y longitud.', 422);
            }
            $normalized[] = [
                'id' => $id,
                'latitud' => (float)$lat,
                'longitud' => (float)$lng,
            ];
        }

        $updated = $this->domicilioModel->bulkUpdateGeocoords($normalized);
        Response::success([
            'updated' => $updated,
            'requested' => count($normalized),
        ], 'Lote guardado con geocodificacion manual.');
    }

    /** POST /api/geocoding/domicilios/fallback */
    public function fallback(Request $request): void
    {
        $applyGlobal = (bool)$request->input('apply_global', true);

        $fromSection = $this->domicilioModel->fillPendingWithSectionCentroids();
        $fromGlobal = 0;

        if ($applyGlobal) {
            $fromGlobal = $this->domicilioModel->fillPendingWithGlobalPoint(
                GEOCODING_FALLBACK_LAT,
                GEOCODING_FALLBACK_LNG
            );
        }

        $total = $this->domicilioModel->count();
        $geocodificados = $this->domicilioModel->count(['geocodificado' => 1]);

        Response::success([
            'updated_from_section_centroid' => $fromSection,
            'updated_from_global_centroid' => $fromGlobal,
            'estado' => [
                'total' => $total,
                'geocodificados' => $geocodificados,
                'pendientes' => max(0, $total - $geocodificados),
                'cobertura_pct' => $total > 0 ? round(($geocodificados / $total) * 100, 2) : 0,
            ],
        ], 'Fallback de geocodificacion aplicado.');
    }

    /** POST /api/geocoding/domicilios/reset */
    public function reset(Request $request): void
    {
        $onlyGeocoded = (bool)$request->input('only_geocoded', false);
        $updated = $this->domicilioModel->clearGeocodingData($onlyGeocoded);

        Response::success([
            'updated' => $updated,
            'only_geocoded' => $onlyGeocoded,
        ], 'Datos de geocodificacion limpiados.');
    }

    /** GET /api/geocoding/domicilios/wizard */
    public function wizardQueue(Request $request): void
    {
        $limit = max(1, min(100, (int)$request->query('limit', 25)));
        $page = max(1, (int)$request->query('page', 1));
        $offset = ($page - 1) * $limit;
        $onlyPending = ((int)$request->query('only_pending', 1)) === 1;
        $loteId = (int)$request->query('lote_id', 0);

        if ($loteId > 0) {
            $items = $this->domicilioModel->findForWizardByLote($loteId, $limit, $offset, $onlyPending);
            $total = $this->domicilioModel->countByLote($loteId, $onlyPending);
        } else {
            $items = $this->domicilioModel->findForWizard($limit, $offset, $onlyPending);
            $total = $this->domicilioModel->count($onlyPending ? ['geocodificado' => 0] : []);
        }

        Response::success([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $limit,
            'pages' => (int)ceil($total / $limit),
            'only_pending' => $onlyPending,
            'lote_id' => $loteId > 0 ? $loteId : null,
        ]);
    }

    /** POST /api/geocoding/domicilios/wizard/{id}/attempt */
    public function wizardAttempt(Request $request): void
    {
        if (!$this->geocodingClient->isConfigured()) {
            Response::error('Geocodificador no configurado en el entorno.', 503);
        }

        $id = (int)$request->param('id');
        $domicilio = $this->domicilioModel->findById($id);
        if (!$domicilio) {
            Response::notFound('Domicilio no encontrado.');
        }

        $manualQuery = trim((string)$request->input('query', ''));
        $useVariants = (bool)$request->input('use_variants', true);

        $attempt = $manualQuery !== ''
            ? $this->proposeFromQueries([$manualQuery])
            : $this->proposeForDomicilio($domicilio, $useVariants);
        $attempts = $attempt['attempts'];
        $resolved = $attempt['resolved'];

        if ($resolved !== null) {
            $this->domicilioModel->updateGeocoords($id, (float)$resolved['lat'], (float)$resolved['lng'], 1);
        }

        Response::success([
            'domicilio_id' => $id,
            'success' => $resolved !== null,
            'resolved' => $resolved,
            'attempts' => $attempts,
            'domicilio' => $this->domicilioModel->findById($id),
        ], $resolved !== null ? 'Domicilio geocodificado.' : 'No se encontro geocodificacion para las variantes.');
    }

    /** POST /api/geocoding/domicilios/wizard/{id}/manual */
    public function wizardManual(Request $request): void
    {
        $id = (int)$request->param('id');
        $lat = $request->input('latitud');
        $lng = $request->input('longitud');

        if ($lat === null || $lng === null) {
            Response::error('Debe enviar latitud y longitud.', 422);
        }

        $domicilio = $this->domicilioModel->findById($id);
        if (!$domicilio) {
            Response::notFound('Domicilio no encontrado.');
        }

        // Si el guardado manual fue aceptado, el domicilio queda geocodificado.
        $this->domicilioModel->updateGeocoords($id, (float)$lat, (float)$lng, 1);
        Response::success($this->domicilioModel->findById($id), 'Coordenadas guardadas manualmente.');
    }

    /** POST /api/geocoding/domicilios/wizard/{id}/fallback */
    public function wizardFallback(Request $request): void
    {
        $id = (int)$request->param('id');
        $domicilio = $this->domicilioModel->findById($id);
        if (!$domicilio) {
            Response::notFound('Domicilio no encontrado.');
        }

        $this->domicilioModel->updateGeocoords($id, GEOCODING_FALLBACK_LAT, GEOCODING_FALLBACK_LNG, 0);
        Response::success($this->domicilioModel->findById($id), 'Domicilio marcado con fallback global.');
    }

    private function safeGeocode(string $query, ?string $provider = null): ?array
    {
        try {
            return $this->geocodingClient->geocodeAddress($query, $provider);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @return array<int,string>
     */
    private function buildQueryVariants(string $calle, int $altura, bool $useVariants): array
    {
        $base = trim($calle);
        $normalized = $this->normalizeStreetName($base);

        $variants = [
            $base,
            $normalized,
        ];

        if (!$useVariants) {
            $variants = [$base];
        }

        $queries = [];
        foreach (array_values(array_unique(array_filter($variants))) as $street) {
            // Variante compacta solicitada para mejorar hit-rate local.
            $queries[] = sprintf('%s %s %s', $street, $altura, GEOCODING_TARGET_PROVINCE);
            $queries[] = sprintf('%s %s, %s, %s, %s', $street, $altura, GEOCODING_TARGET_CITY, GEOCODING_TARGET_PROVINCE, GEOCODING_TARGET_COUNTRY);
            $queries[] = sprintf('%s, %s, %s, %s', $street, GEOCODING_TARGET_CITY, GEOCODING_TARGET_PROVINCE, GEOCODING_TARGET_COUNTRY);
        }

        return array_values(array_unique($queries));
    }

    /**
     * @param array<string,mixed> $domicilio
     * @return array{attempts: array<int,array<string,mixed>>, resolved: ?array}
     */
    private function proposeForDomicilio(array $domicilio, bool $useVariants, ?string $provider = null): array
    {
        $queries = $this->buildQueryVariants((string)$domicilio['calle'], (int)$domicilio['altura'], $useVariants);
        return $this->proposeFromQueries($queries, $provider);
    }

    /**
     * @param array<int,string> $queries
     * @return array{attempts: array<int,array<string,mixed>>, resolved: ?array}
     */
    private function proposeFromQueries(array $queries, ?string $provider = null): array
    {
        $attempts = [];
        $resolved = null;
        foreach ($queries as $query) {
            $geo = $this->safeGeocode($query, $provider);
            $attempts[] = [
                'query' => $query,
                'matched' => $geo !== null,
                'lat' => $geo['lat'] ?? null,
                'lng' => $geo['lng'] ?? null,
                'display_name' => $geo['display_name'] ?? null,
            ];
            if ($geo !== null) {
                $resolved = $geo;
                break;
            }
        }

        return [
            'attempts' => $attempts,
            'resolved' => $resolved,
        ];
    }

    private function normalizeStreetName(string $street): string
    {
        $street = preg_replace('/\s+/', ' ', trim($street)) ?? $street;
        $street = preg_replace('/\bR\s*(\d+)\s*de\s*infanteria\b/i', 'Regimiento $1 de Infanteria', $street) ?? $street;
        $street = preg_replace('/\bGral\.?\b/i', 'General', $street) ?? $street;
        $street = preg_replace('/\bAv\.?\b/i', 'Avenida', $street) ?? $street;
        return trim($street);
    }
}
