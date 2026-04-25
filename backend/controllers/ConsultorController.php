<?php
// =============================================================
// KYZ Logistica - ConsultorController
// GET /api/consultor/perfil
// PUT /api/consultor/perfil
// =============================================================

class ConsultorController
{
    private ConsultorPerfil $perfilModel;
    private GeocodingClient $geocodingClient;

    public function __construct()
    {
        $this->perfilModel = new ConsultorPerfil();
        $this->geocodingClient = new GeocodingClient();
    }

    public function getPerfil(): void
    {
        $auth = AuthContext::get();
        if ($auth['rol'] !== 'consultor') {
            Response::forbidden('Solo consultores pueden gestionar su perfil operativo.');
        }

        Response::success($this->perfilModel->findByUsuarioId((int)$auth['sub']));
    }

    public function updatePerfil(Request $request): void
    {
        $auth = AuthContext::get();
        if ($auth['rol'] !== 'consultor') {
            Response::forbidden('Solo consultores pueden gestionar su perfil operativo.');
        }

        $direccion = trim((string)$request->input('punto_partida_direccion', ''));
        $direccionRetorno = trim((string)$request->input('punto_retorno_direccion', ''));
        $movilidad = (string)$request->input('movilidad', 'a_pie');
        $disponibilidad = (int)$request->input('disponibilidad_minutos', 240);

        $movilidades = ['a_pie', 'vehiculo', 'autobus', 'bicicleta'];
        if (!in_array($movilidad, $movilidades, true)) {
            Response::error('Movilidad invalida.', 422);
        }

        if ($disponibilidad < 60 || $disponibilidad > 720) {
            Response::error('La disponibilidad debe estar entre 60 y 720 minutos.', 422);
        }

        $lat = $request->input('punto_partida_latitud');
        $lng = $request->input('punto_partida_longitud');
        $retLat = $request->input('punto_retorno_latitud');
        $retLng = $request->input('punto_retorno_longitud');

        if ($direccion !== '' && ($lat === null || $lng === null)) {
            $query = $direccion . ', ' . GEOCODING_TARGET_CITY . ', ' . GEOCODING_TARGET_PROVINCE . ', ' . GEOCODING_TARGET_COUNTRY;
            $geo = $this->geocodeSafe($query);
            if ($geo !== null) {
                $lat = $geo['lat'];
                $lng = $geo['lng'];
            }
        }

        if ($direccionRetorno === '' && $direccion !== '') {
            $direccionRetorno = $direccion;
        }

        if ($direccionRetorno !== '' && ($retLat === null || $retLng === null)) {
            $queryRet = $direccionRetorno . ', ' . GEOCODING_TARGET_CITY . ', ' . GEOCODING_TARGET_PROVINCE . ', ' . GEOCODING_TARGET_COUNTRY;
            $geoRet = $this->geocodeSafe($queryRet);
            if ($geoRet !== null) {
                $retLat = $geoRet['lat'];
                $retLng = $geoRet['lng'];
            }
        }

        if (($retLat === null || $retLng === null) && $lat !== null && $lng !== null) {
            $retLat = $lat;
            $retLng = $lng;
        }

        $this->perfilModel->upsert((int)$auth['sub'], [
            'punto_partida_direccion' => $direccion !== '' ? $direccion : null,
            'punto_partida_latitud' => $lat !== null ? (float)$lat : null,
            'punto_partida_longitud' => $lng !== null ? (float)$lng : null,
            'punto_retorno_direccion' => $direccionRetorno !== '' ? $direccionRetorno : ($direccion !== '' ? $direccion : null),
            'punto_retorno_latitud' => $retLat !== null ? (float)$retLat : null,
            'punto_retorno_longitud' => $retLng !== null ? (float)$retLng : null,
            'movilidad' => $movilidad,
            'disponibilidad_minutos' => $disponibilidad,
        ]);

        Response::success($this->perfilModel->findByUsuarioId((int)$auth['sub']), 'Perfil de consultor actualizado.');
    }

    private function geocodeSafe(string $query): ?array
    {
        try {
            return $this->geocodingClient->geocodeAddress($query);
        } catch (RuntimeException) {
            return null;
        }
    }
}
