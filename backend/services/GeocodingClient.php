<?php
// =============================================================
// KYZ Logistica - Cliente de geocodificacion (Nominatim)
// =============================================================

class GeocodingClient
{
    public function isConfigured(?string $provider = null): bool
    {
        if (!GEOCODING_ENABLED) {
            return false;
        }

        $provider = $provider !== null ? strtolower(trim($provider)) : strtolower((string)GEOCODING_PROVIDER);
        if ($provider === 'nominatim') {
            return true;
        }
        if ($provider === 'google') {
            return GEOCODING_GOOGLE_API_KEY !== '';
        }
        return false;
    }

    /**
     * Devuelve ['lat' => float, 'lng' => float, 'display_name' => string, 'raw' => array] o null.
     */
    public function geocodeAddress(string $query, ?string $provider = null): ?array
    {
        $provider = $provider !== null ? strtolower(trim($provider)) : strtolower((string)GEOCODING_PROVIDER);

        if (!$this->isConfigured($provider)) {
            throw new RuntimeException('Geocoding no configurado. Revisar GEOCODING_ENABLED/GEOCODING_PROVIDER.');
        }

        if ($provider === 'google') {
            return $this->geocodeGoogle($query);
        }
        if ($provider === 'nominatim') {
            return $this->geocodeNominatim($query);
        }

        throw new RuntimeException('Proveedor de geocoding no soportado: ' . $provider);
    }

    private function geocodeNominatim(string $query): ?array
    {
        $url = rtrim(GEOCODING_BASE_URL, '/') . '/search?' . http_build_query([
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 1,
            'q' => $query,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . GEOCODING_USER_AGENT,
            ],
            CURLOPT_TIMEOUT => GEOCODING_TIMEOUT,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('Error de red en geocodificador: ' . $error);
        }

        if (!is_string($response) || $response === '') {
            return null;
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            throw new RuntimeException('Respuesta invalida del geocodificador.');
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('Geocodificador rechazo la solicitud (HTTP ' . $httpCode . ').');
        }

        if (empty($json[0])) {
            return null;
        }

        $item = $json[0];
        $lat = isset($item['lat']) ? (float)$item['lat'] : null;
        $lng = isset($item['lon']) ? (float)$item['lon'] : null;

        if ($lat === null || $lng === null) {
            return null;
        }

        if (!$this->isInsideTargetArea($item['address'] ?? [])) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'display_name' => (string)($item['display_name'] ?? ''),
            'raw' => $item,
        ];
    }

    private function geocodeGoogle(string $query): ?array
    {
        $url = rtrim(GEOCODING_GOOGLE_BASE_URL, '?') . '?' . http_build_query([
            'address' => $query,
            'key' => GEOCODING_GOOGLE_API_KEY,
            'region' => 'ar',
            'language' => 'es',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . GEOCODING_USER_AGENT,
            ],
            CURLOPT_TIMEOUT => GEOCODING_TIMEOUT,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('Error de red en Google Geocoding: ' . $error);
        }
        if (!is_string($response) || $response === '') {
            return null;
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            throw new RuntimeException('Respuesta invalida de Google Geocoding.');
        }
        if ($httpCode >= 400) {
            throw new RuntimeException('Google Geocoding rechazo la solicitud (HTTP ' . $httpCode . ').');
        }

        $status = (string)($json['status'] ?? '');
        if ($status !== 'OK') {
            if ($status === 'ZERO_RESULTS') {
                return null;
            }
            throw new RuntimeException('Google Geocoding status: ' . $status);
        }

        $item = $json['results'][0] ?? null;
        if (!is_array($item)) {
            return null;
        }

        $lat = $item['geometry']['location']['lat'] ?? null;
        $lng = $item['geometry']['location']['lng'] ?? null;
        if ($lat === null || $lng === null) {
            return null;
        }

        if (!$this->isInsideTargetAreaGoogle((array)($item['address_components'] ?? []))) {
            return null;
        }

        return [
            'lat' => (float)$lat,
            'lng' => (float)$lng,
            'display_name' => (string)($item['formatted_address'] ?? ''),
            'raw' => $item,
        ];
    }

    private function isInsideTargetArea(array $address): bool
    {
        $country = strtolower((string)($address['country'] ?? ''));
        $state = strtolower((string)($address['state'] ?? ''));
        $city = strtolower((string)($address['city'] ?? $address['town'] ?? $address['municipality'] ?? ''));

        $countryOk = $country === '' || str_contains($country, strtolower(GEOCODING_TARGET_COUNTRY));
        $stateOk = $state === '' || str_contains($state, strtolower(GEOCODING_TARGET_PROVINCE));
        $cityOk = $city === '' || str_contains($city, strtolower(GEOCODING_TARGET_CITY));

        return $countryOk && $stateOk && $cityOk;
    }

    /**
     * @param array<int,array<string,mixed>> $components
     */
    private function isInsideTargetAreaGoogle(array $components): bool
    {
        $country = '';
        $state = '';
        $city = '';

        foreach ($components as $component) {
            $types = $component['types'] ?? [];
            if (!is_array($types)) {
                continue;
            }
            $longName = strtolower((string)($component['long_name'] ?? ''));

            if (in_array('country', $types, true)) {
                $country = $longName;
            } elseif (in_array('administrative_area_level_1', $types, true)) {
                $state = $longName;
            } elseif (in_array('locality', $types, true) || in_array('administrative_area_level_2', $types, true)) {
                $city = $longName;
            }
        }

        $countryOk = $country === '' || str_contains($country, strtolower(GEOCODING_TARGET_COUNTRY));
        $stateOk = $state === '' || str_contains($state, strtolower(GEOCODING_TARGET_PROVINCE));
        // Google puede devolver locality de barrio/zona (ej. "AMX") en vez de "Santa Fe".
        // Si país + provincia matchean, aceptamos aunque locality no contenga GEOCODING_TARGET_CITY.
        $cityOk = $city === '' || str_contains($city, strtolower(GEOCODING_TARGET_CITY)) || $stateOk;
        return $countryOk && $stateOk && $cityOk;
    }
}
