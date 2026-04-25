<?php
// =============================================================
// KYZ Logistica - Planificador de visitas del dia
// Basado en punto de partida + disponibilidad + movilidad.
// =============================================================

class DayRoutePlannerService
{
    /**
     * @param array<int,array<string,mixed>> $asignaciones
     * @param array<string,mixed> $perfil
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function plan(array $asignaciones, array $perfil, array $options = []): array
    {
        $excludedIds = array_values(array_unique(array_map('intval', (array)($options['excluded_asignacion_ids'] ?? []))));
        $forcedFirstId = isset($options['forced_first_asignacion_id']) ? (int)$options['forced_first_asignacion_id'] : null;
        $considerOperationalTime = (bool)($options['consider_operational_time'] ?? false);
        $operationalMarginMinutes = max(0.0, (float)($options['operational_margin_minutes'] ?? 0));
        $pendientes = array_values(array_filter(
            $asignaciones,
            fn($a) => ($a['estado'] ?? '') === 'pendiente' && !in_array((int)$a['id'], $excludedIds, true)
        ));

        if (empty($pendientes)) {
            $origin = $this->originPoint($perfil);
            $returnPoint = $this->returnPoint($perfil, $origin);
            return [
                'stops' => [],
                'resumen' => [
                    'total_pendientes' => 0,
                    'total_planificadas' => 0,
                    'minutos_disponibles' => (int)$perfil['disponibilidad_minutos'],
                    'minutos_estimados' => 0,
                    'minutos_sobrantes' => (int)$perfil['disponibilidad_minutos'],
                    'distancia_km' => 0.0,
                    'minutos_logistica' => 0,
                    'minutos_operativos' => 0,
                    'minutos_totales_con_operativa' => 0,
                ],
                'ruta' => [
                    'inicio' => $origin,
                    'retorno' => $returnPoint,
                    'segmentos' => [],
                    'google_maps_url' => $this->buildGoogleMapsRouteUrl($origin, $returnPoint, [], (string)($perfil['movilidad'] ?? 'a_pie')),
                ],
                'audit' => [
                    'excluded_asignacion_ids' => $excludedIds,
                    'forced_first_asignacion_id' => $forcedFirstId,
                    'consider_operational_time' => $considerOperationalTime,
                    'operational_margin_minutes' => $operationalMarginMinutes,
                    'criterios' => $this->criteriaWeights(),
                    'iteraciones' => [],
                ],
            ];
        }

        $origin = $this->originPoint($perfil);
        $returnPoint = $this->returnPoint($perfil, $origin);
        $speedKmh = $this->speedByMobility((string)$perfil['movilidad']);
        $disponibles = (int)$perfil['disponibilidad_minutos'];

        $current = $origin;
        $pool = $pendientes;
        $stops = [];
        $usedTravelMinutes = 0.0;
        $usedOperationalMinutes = 0.0;
        $distanceKm = 0.0;
        $auditIterations = [];

        if ($forcedFirstId !== null) {
            $forcedIdx = $this->findByAsignacionId($pool, $forcedFirstId);
            if ($forcedIdx >= 0) {
                $forcedCandidate = $pool[$forcedIdx];
                $forcedTravel = $this->travelMinutes($current, $forcedCandidate, $speedKmh);
                $forcedVisit = $this->visitMinutes((string)($forcedCandidate['servicio'] ?? ''));
                $forcedReturn = $this->travelMinutes($forcedCandidate, $returnPoint, $speedKmh);

                $forcedProjectedTravel = $usedTravelMinutes + $forcedTravel + $forcedReturn;
                $forcedProjectedWithOperational = $usedTravelMinutes + $usedOperationalMinutes + $forcedTravel + $forcedVisit + $forcedReturn;
                $forcedAvailable = $considerOperationalTime ? ($disponibles + $operationalMarginMinutes) : $disponibles;
                $forcedFeasible = $considerOperationalTime
                    ? ($forcedProjectedWithOperational <= $forcedAvailable)
                    : ($forcedProjectedTravel <= $forcedAvailable);

                if ($forcedFeasible) {
                    $usedTravelMinutes += $forcedTravel;
                    $usedOperationalMinutes += $forcedVisit;
                    $distanceKm += $this->distanceKm($current, $forcedCandidate);
                    $stops[] = $this->buildStop($forcedCandidate, $usedTravelMinutes, $usedOperationalMinutes, [
                        'selected_by' => 'forced_first_stop',
                        'distance_km' => round($this->distanceKm($current, $forcedCandidate), 3),
                        'travel_minutes' => round($forcedTravel, 1),
                        'visit_minutes' => round($forcedVisit, 1),
                        'projected_return_minutes' => round($forcedReturn, 1),
                        'projected_total_minutes' => round($usedTravelMinutes + $forcedReturn, 1),
                        'score_total' => null,
                        'scores' => null,
                        'alternatives' => [],
                        'decision_note' => 'Parada fijada manualmente por el consultor.',
                        'rejected' => [],
                    ]);
                    $current = $forcedCandidate;
                    array_splice($pool, $forcedIdx, 1);
                }
            }
        }

        while (!empty($pool)) {
            $evaluations = $this->evaluateCandidates(
                $current,
                $pool,
                $returnPoint,
                $usedTravelMinutes,
                $usedOperationalMinutes,
                $disponibles,
                $speedKmh,
                $considerOperationalTime,
                $operationalMarginMinutes
            );
            $feasible = array_values(array_filter($evaluations, fn($e) => $e['feasible']));

            if (empty($feasible)) {
                $auditIterations[] = [
                    'remaining_candidates' => count($pool),
                    'selected_asignacion_id' => null,
                    'reason' => 'No hay candidatas que permitan retorno al origen dentro del tiempo disponible.',
                ];
                break;
            }

            usort($feasible, fn($a, $b) => $b['score_total'] <=> $a['score_total']);
            $selected = $feasible[0];
            $next = $pool[(int)$selected['pool_index']];
            $usedTravelMinutes += (float)$selected['travel_minutes'];
            $usedOperationalMinutes += (float)$selected['visit_minutes'];
            $distanceKm += (float)$selected['distance_km'];

            $alternatives = array_map(function (array $ev): array {
                return [
                    'asignacion_id' => (int)$ev['asignacion_id'],
                    'score_total' => round((float)$ev['score_total'], 2),
                    'distance_km' => round((float)$ev['distance_km'], 3),
                    'projected_total_minutes' => round((float)$ev['projected_total_with_operational_minutes'], 1),
                ];
            }, array_slice($feasible, 1, 3));

            $rejected = array_map(function (array $ev): array {
                return [
                    'asignacion_id' => (int)$ev['asignacion_id'],
                    'reason' => 'Supera disponibilidad al contemplar traslado, operativa y retorno.',
                    'projected_total_minutes' => round((float)$ev['projected_total_with_operational_minutes'], 1),
                    'available_minutes' => (int)$ev['available_minutes'],
                ];
            }, array_values(array_filter($evaluations, fn($e) => !$e['feasible'])));

            $stops[] = $this->buildStop($next, $usedTravelMinutes, $usedOperationalMinutes, [
                'selected_by' => 'score_maximization',
                'distance_km' => round((float)$selected['distance_km'], 3),
                'travel_minutes' => round((float)$selected['travel_minutes'], 1),
                'visit_minutes' => round((float)$selected['visit_minutes'], 1),
                'projected_return_minutes' => round((float)$selected['return_minutes'], 1),
                'projected_total_minutes' => round((float)$selected['projected_total_with_operational_minutes'], 1),
                'score_total' => round((float)$selected['score_total'], 2),
                'scores' => [
                    'distance' => round((float)$selected['distance_score'], 2),
                    'service_priority' => round((float)$selected['service_priority_score'], 2),
                    'section_continuity' => round((float)$selected['section_continuity_score'], 2),
                ],
                'alternatives' => $alternatives,
                'decision_note' => 'Seleccionada por mayor prioridad interna entre candidatas factibles.',
                'rejected' => array_slice($rejected, 0, 4),
            ]);

            $auditIterations[] = [
                'remaining_candidates' => count($pool),
                'selected_asignacion_id' => (int)$selected['asignacion_id'],
                'selected_score' => round((float)$selected['score_total'], 2),
                'top_candidates' => array_slice(array_map(function (array $ev): array {
                    return [
                        'asignacion_id' => (int)$ev['asignacion_id'],
                        'score_total' => round((float)$ev['score_total'], 2),
                        'feasible' => (bool)$ev['feasible'],
                    ];
                }, $evaluations), 0, 5),
            ];

            $current = $next;
            array_splice($pool, (int)$selected['pool_index'], 1);
        }

        $returnDistance = $this->distanceKm($current, $returnPoint);
        $returnMinutes = $this->travelMinutes($current, $returnPoint, $speedKmh);
        $distanceKm += $returnDistance;
        $usedTravelMinutes += $returnMinutes;

        $stopsWithNavigation = $this->attachStopNavigationUrls($stops, $origin, (string)$perfil['movilidad']);
        $segments = $this->buildSegments($origin, $returnPoint, $stopsWithNavigation);

        return [
            'stops' => $stopsWithNavigation,
            'resumen' => [
                'total_pendientes' => count($pendientes),
                'total_planificadas' => count($stopsWithNavigation),
                'minutos_disponibles' => $disponibles,
                'minutos_estimados' => (int)round($usedTravelMinutes),
                'minutos_sobrantes' => max(0, $disponibles - (int)round($usedTravelMinutes)),
                'distancia_km' => round($distanceKm, 2),
                'movilidad' => (string)$perfil['movilidad'],
                'retorno_minutos' => (int)round($returnMinutes),
                'retorno_distancia_km' => round($returnDistance, 2),
                'minutos_logistica' => (int)round($usedTravelMinutes),
                'minutos_operativos' => (int)round($usedOperationalMinutes),
                'minutos_totales_con_operativa' => (int)round($usedTravelMinutes + $usedOperationalMinutes),
            ],
            'ruta' => [
                'inicio' => $origin,
                'retorno' => $returnPoint,
                'segmentos' => $segments,
                'google_maps_url' => $this->buildGoogleMapsRouteUrl($origin, $returnPoint, $stopsWithNavigation, (string)$perfil['movilidad']),
            ],
            'audit' => [
                'excluded_asignacion_ids' => $excludedIds,
                'forced_first_asignacion_id' => $forcedFirstId,
                    'consider_operational_time' => $considerOperationalTime,
                    'operational_margin_minutes' => $operationalMarginMinutes,
                'criterios' => $this->criteriaWeights(),
                'iteraciones' => $auditIterations,
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $pool
     */
    private function findByAsignacionId(array $pool, int $asignacionId): int
    {
        foreach ($pool as $idx => $candidate) {
            if ((int)$candidate['id'] === $asignacionId) {
                return $idx;
            }
        }
        return -1;
    }

    /**
     * @param array<int,array<string,mixed>> $pool
     * @return array<int,array<string,mixed>>
     */
    private function evaluateCandidates(
        array $current,
        array $pool,
        array $returnPoint,
        float $usedTravelMinutes,
        float $usedOperationalMinutes,
        int $disponibles,
        float $speedKmh,
        bool $considerOperationalTime,
        float $operationalMarginMinutes
    ): array
    {
        $weights = $this->criteriaWeights();
        $evaluations = [];

        foreach ($pool as $idx => $candidate) {
            $distance = $this->distanceKm($current, $candidate);
            $travel = $this->travelMinutes($current, $candidate, $speedKmh);
            $visit = $this->visitMinutes((string)($candidate['servicio'] ?? ''));
            $return = $this->travelMinutes($candidate, $returnPoint, $speedKmh);
            $projectedTravelTotal = $usedTravelMinutes + $travel + $return;
            $projectedWithOperational = $usedTravelMinutes + $usedOperationalMinutes + $travel + $visit + $return;
            $availableMinutes = $considerOperationalTime ? ($disponibles + $operationalMarginMinutes) : $disponibles;
            $feasible = $considerOperationalTime
                ? ($projectedWithOperational <= $availableMinutes)
                : ($projectedTravelTotal <= $availableMinutes);

            $distanceScore = 100.0 / (1.0 + $distance);
            $servicePriorityScore = $this->servicePriority((string)($candidate['servicio'] ?? ''));
            $sectionContinuityScore = $this->sameSection($current, $candidate) ? 10.0 : 0.0;

            $totalScore = (
                $distanceScore * $weights['distance'] +
                $servicePriorityScore * $weights['service_priority'] +
                $sectionContinuityScore * $weights['section_continuity']
            );

            $evaluations[] = [
                'pool_index' => $idx,
                'asignacion_id' => (int)$candidate['id'],
                'distance_km' => $distance,
                'travel_minutes' => $travel,
                'visit_minutes' => $visit,
                'return_minutes' => $return,
                'projected_total_minutes' => $projectedTravelTotal,
                'projected_total_with_operational_minutes' => $projectedWithOperational,
                'available_minutes' => $availableMinutes,
                'distance_score' => $distanceScore,
                'service_priority_score' => $servicePriorityScore,
                'section_continuity_score' => $sectionContinuityScore,
                'score_total' => $totalScore,
                'feasible' => $feasible,
            ];
        }

        usort($evaluations, fn($a, $b) => $b['score_total'] <=> $a['score_total']);
        return $evaluations;
    }

    /**
     * @param array<string,mixed> $candidate
     * @param array<string,mixed> $explanation
     * @return array<string,mixed>
     */
    private function buildStop(array $candidate, float $usedTravelMinutes, float $usedOperationalMinutes, array $explanation): array
    {
        return [
            'asignacion_id' => (int)$candidate['id'],
            'domicilio_id' => (int)($candidate['domicilio_id'] ?? $candidate['id']),
            'orden_original' => (int)$candidate['orden'],
            'calle' => (string)$candidate['calle'],
            'altura' => (int)$candidate['altura'],
            'seccion_numero' => (int)$candidate['seccion_numero'],
            'servicio' => (string)$candidate['servicio'],
            'latitud' => $candidate['latitud'] !== null ? (float)$candidate['latitud'] : null,
            'longitud' => $candidate['longitud'] !== null ? (float)$candidate['longitud'] : null,
            'eta_min_desde_origen' => (int)round($usedTravelMinutes),
            'eta_operativa_min_desde_origen' => (int)round($usedTravelMinutes + $usedOperationalMinutes),
            'explanation' => $explanation,
        ];
    }

    private function servicePriority(string $servicio): float
    {
        return match ($servicio) {
            'Gas Natural' => 9.0,
            'Servicio Social' => 7.0,
            default => 6.0,
        };
    }

    private function sameSection(array $current, array $candidate): bool
    {
        if (!isset($current['seccion_numero']) || !isset($candidate['seccion_numero'])) {
            return false;
        }
        return (int)$current['seccion_numero'] === (int)$candidate['seccion_numero'];
    }

    /**
     * @return array<string,float>
     */
    private function criteriaWeights(): array
    {
        return [
            'distance' => 0.60,
            'service_priority' => 0.25,
            'section_continuity' => 0.15,
        ];
    }

    private function travelMinutes(array $from, array $to, float $speedKmh): float
    {
        $km = $this->distanceKm($from, $to);
        if ($speedKmh <= 0) {
            return 0;
        }
        return ($km / $speedKmh) * 60.0;
    }

    private function visitMinutes(string $servicio): float
    {
        return 5.0;
    }

    private function speedByMobility(string $movilidad): float
    {
        return match ($movilidad) {
            'a_pie' => 4.5,
            'bicicleta' => 14.0,
            'autobus' => 18.0,
            'vehiculo' => 28.0,
            default => 4.5,
        };
    }

    private function googleMapsTravelMode(string $movilidad): string
    {
        return match ($movilidad) {
            'a_pie' => 'walking',
            'bicicleta' => 'bicycling',
            'autobus' => 'transit',
            'vehiculo' => 'driving',
            default => 'walking',
        };
    }

    private function originPoint(array $perfil): array
    {
        return [
            'direccion' => (string)($perfil['punto_partida_direccion'] ?? ''),
            'latitud' => $perfil['punto_partida_latitud'] !== null ? (float)$perfil['punto_partida_latitud'] : GEOCODING_FALLBACK_LAT,
            'longitud' => $perfil['punto_partida_longitud'] !== null ? (float)$perfil['punto_partida_longitud'] : GEOCODING_FALLBACK_LNG,
        ];
    }

    private function returnPoint(array $perfil, array $origin): array
    {
        return [
            'direccion' => (string)($perfil['punto_retorno_direccion'] ?? $origin['direccion'] ?? ''),
            'latitud' => $perfil['punto_retorno_latitud'] !== null ? (float)$perfil['punto_retorno_latitud'] : (float)$origin['latitud'],
            'longitud' => $perfil['punto_retorno_longitud'] !== null ? (float)$perfil['punto_retorno_longitud'] : (float)$origin['longitud'],
        ];
    }

    private function buildSegments(array $origin, array $returnPoint, array $stops): array
    {
        $segments = [];
        $current = $origin;

        foreach ($stops as $stop) {
            $segments[] = [
                'from' => [
                    'latitud' => (float)$current['latitud'],
                    'longitud' => (float)$current['longitud'],
                ],
                'to' => [
                    'latitud' => (float)$stop['latitud'],
                    'longitud' => (float)$stop['longitud'],
                ],
                'asignacion_id' => (int)$stop['asignacion_id'],
            ];
            $current = $stop;
        }

        $segments[] = [
            'from' => [
                'latitud' => (float)$current['latitud'],
                'longitud' => (float)$current['longitud'],
            ],
            'to' => [
                'latitud' => (float)$returnPoint['latitud'],
                'longitud' => (float)$returnPoint['longitud'],
            ],
            'asignacion_id' => null,
            'return_to_point' => true,
        ];

        return $segments;
    }

    /**
     * @param array<int,array<string,mixed>> $stops
     * @return array<int,array<string,mixed>>
     */
    private function attachStopNavigationUrls(array $stops, array $origin, string $movilidad): array
    {
        $mode = $this->googleMapsTravelMode($movilidad);
        $out = [];
        $current = $origin;

        foreach ($stops as $stop) {
            $originStr = $this->latLng((float)$current['latitud'], (float)$current['longitud']);
            $destStr = $this->latLng((float)$stop['latitud'], (float)$stop['longitud']);
            $stop['google_maps_step_url'] = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($originStr)
                . '&destination=' . rawurlencode($destStr)
                . '&travelmode=' . rawurlencode($mode);
            $out[] = $stop;
            $current = $stop;
        }

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $stops
     */
    private function buildGoogleMapsRouteUrl(array $origin, array $returnPoint, array $stops, string $movilidad): string
    {
        $mode = $this->googleMapsTravelMode($movilidad);
        $originStr = $this->latLng((float)$origin['latitud'], (float)$origin['longitud']);
        $returnStr = $this->latLng((float)$returnPoint['latitud'], (float)$returnPoint['longitud']);

        if (empty($stops)) {
            return 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($originStr)
                . '&destination=' . rawurlencode($returnStr)
                . '&travelmode=' . rawurlencode($mode);
        }

        end($stops);
        reset($stops);

        $waypoints = [];
        foreach (array_slice($stops, 0, 23) as $stop) {
            $waypoints[] = $this->latLng((float)$stop['latitud'], (float)$stop['longitud']);
        }

        $url = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($originStr)
            . '&destination=' . rawurlencode($returnStr)
            . '&travelmode=' . rawurlencode($mode);

        if (!empty($waypoints)) {
            $url .= '&waypoints=' . rawurlencode(implode('|', $waypoints));
        }

        return $url;
    }

    private function latLng(float $lat, float $lng): string
    {
        return number_format($lat, 6, '.', '') . ',' . number_format($lng, 6, '.', '');
    }

    private function distanceKm(array $a, array $b): float
    {
        $lat1 = $a['latitud'] !== null ? (float)$a['latitud'] : GEOCODING_FALLBACK_LAT;
        $lon1 = $a['longitud'] !== null ? (float)$a['longitud'] : GEOCODING_FALLBACK_LNG;
        $lat2 = $b['latitud'] !== null ? (float)$b['latitud'] : GEOCODING_FALLBACK_LAT;
        $lon2 = $b['longitud'] !== null ? (float)$b['longitud'] : GEOCODING_FALLBACK_LNG;

        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $x = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $earth * asin(min(1.0, sqrt($x)));
    }
}
