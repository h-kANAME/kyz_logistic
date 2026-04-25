<?php
// =============================================================
// KYZ Logistica - Optimizador de ruta V2
// Greedy multicriterio: distancia + prioridad de servicio +
// continuidad de seccion + calidad de geocodificacion.
// =============================================================

class RouteOptimizationService
{
    /**
     * @param array<int,array<string,mixed>> $domicilios
     * @return array{ordered:array<int,array<string,mixed>>, meta:array<string,mixed>}
     */
    public function optimize(array $domicilios): array
    {
        if (count($domicilios) <= 1) {
            return [
                'ordered' => $domicilios,
                'meta' => [
                    'algorithm' => 'route_v2_greedy',
                    'total' => count($domicilios),
                    'geocoded_ratio' => count($domicilios) === 0 ? 0.0 : $this->geocodedCount($domicilios) / count($domicilios),
                ],
            ];
        }

        $pending = array_values($domicilios);
        $ordered = [];

        // Seleccion inicial: mayor prioridad de negocio + mejor calidad de dato.
        $startIdx = $this->pickBestInitialIndex($pending);
        $current = $pending[$startIdx];
        $ordered[] = $current;
        array_splice($pending, $startIdx, 1);

        while (!empty($pending)) {
            $bestIdx = 0;
            $bestScore = -INF;

            foreach ($pending as $idx => $candidate) {
                $score = $this->transitionScore($current, $candidate);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIdx = $idx;
                }
            }

            $current = $pending[$bestIdx];
            $ordered[] = $current;
            array_splice($pending, $bestIdx, 1);
        }

        return [
            'ordered' => $ordered,
            'meta' => [
                'algorithm' => 'route_v2_greedy',
                'weights' => [
                    'distance' => ROUTE_WEIGHT_DISTANCE,
                    'service' => ROUTE_WEIGHT_SERVICE,
                    'section' => ROUTE_WEIGHT_SECTION,
                    'geocoded' => ROUTE_WEIGHT_GEOCODED,
                ],
                'total' => count($domicilios),
                'geocoded_ratio' => $this->geocodedCount($domicilios) / count($domicilios),
                'services' => $this->serviceDistribution($domicilios),
            ],
        ];
    }

    private function pickBestInitialIndex(array $items): int
    {
        $bestIdx = 0;
        $best = -INF;

        foreach ($items as $idx => $item) {
            $service = $this->serviceScore($item);
            $geo = $this->isGeocoded($item) ? 1.0 : 0.0;
            $sec = (int)($item['seccion_numero'] ?? 0);

            // Menor numero de seccion suele reducir traslados inter-zona en este dataset.
            $sectionPreference = 1.0 / (1.0 + max(0, $sec));
            $score = ($service * 0.6) + ($geo * 0.25) + ($sectionPreference * 0.15);

            if ($score > $best) {
                $best = $score;
                $bestIdx = $idx;
            }
        }

        return $bestIdx;
    }

    private function transitionScore(array $current, array $candidate): float
    {
        $distance = $this->distanceScore($current, $candidate);
        $service = $this->serviceScore($candidate);
        $section = ((int)($current['seccion_numero'] ?? 0) === (int)($candidate['seccion_numero'] ?? 0)) ? 1.0 : 0.0;
        $geocoded = $this->isGeocoded($candidate) ? 1.0 : 0.0;

        return ($distance * ROUTE_WEIGHT_DISTANCE)
            + ($service * ROUTE_WEIGHT_SERVICE)
            + ($section * ROUTE_WEIGHT_SECTION)
            + ($geocoded * ROUTE_WEIGHT_GEOCODED);
    }

    private function distanceScore(array $a, array $b): float
    {
        if ($this->isGeocoded($a) && $this->isGeocoded($b)) {
            $km = $this->haversineKm(
                (float)$a['latitud'],
                (float)$a['longitud'],
                (float)$b['latitud'],
                (float)$b['longitud']
            );

            // 0 km => 1.0, 8 km => 0.5 aprox, tiende a 0 al crecer.
            return 1.0 / (1.0 + ($km / 8.0));
        }

        $secA = (int)($a['seccion_numero'] ?? 0);
        $secB = (int)($b['seccion_numero'] ?? 0);
        $secGap = abs($secA - $secB);

        $alturaA = (int)($a['altura'] ?? 0);
        $alturaB = (int)($b['altura'] ?? 0);
        $alturaGap = min(1.0, abs($alturaA - $alturaB) / 1200.0);

        $sameStreet = (string)($a['calle'] ?? '') === (string)($b['calle'] ?? '') ? 1.0 : 0.0;
        $sectionScore = 1.0 / (1.0 + $secGap);

        return ($sectionScore * 0.65) + ((1.0 - $alturaGap) * 0.2) + ($sameStreet * 0.15);
    }

    private function serviceScore(array $item): float
    {
        $servicio = (string)($item['servicio'] ?? '');

        return match ($servicio) {
            'Servicio Social' => 1.0,
            'Gas Natural' => 0.85,
            default => 0.7,
        };
    }

    private function isGeocoded(array $item): bool
    {
        return $item['latitud'] !== null && $item['longitud'] !== null;
    }

    private function geocodedCount(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            if ($this->isGeocoded($item)) {
                $count++;
            }
        }
        return $count;
    }

    private function serviceDistribution(array $items): array
    {
        $dist = [];
        foreach ($items as $item) {
            $key = (string)($item['servicio'] ?? 'Sin servicio');
            $dist[$key] = ($dist[$key] ?? 0) + 1;
        }
        ksort($dist);
        return $dist;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }
}
