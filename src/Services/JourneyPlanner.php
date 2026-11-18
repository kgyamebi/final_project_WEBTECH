<?php
declare(strict_types=1);

namespace App\Services;

final class JourneyPlanner
{
    public static function search(array $from, array $to): array
    {
        $access = max(200, setting_int('access_radius_m', 1000));
        $transferMax = max(50, setting_int('transfer_radius_m', 400));
        $graph = self::graph();
        $options = [];

        foreach ($graph as $route) {
            $best = null;
            $stops = $route['stops'];
            $count = count($stops);
            for ($i = 0; $i < $count; $i++) {
                $boardWalk = Geo::meters($from['lat'], $from['lng'], $stops[$i]['lat'], $stops[$i]['lng']);
                if ($boardWalk > $access) {
                    continue;
                }
                for ($j = $i + 1; $j < $count; $j++) {
                    $alightWalk = Geo::meters($to['lat'], $to['lng'], $stops[$j]['lat'], $stops[$j]['lng']);
                    if ($alightWalk > $access) {
                        continue;
                    }
                    $walk = $boardWalk + $alightWalk;
                    if ($best === null || $walk < $best['walk']) {
                        $best = ['walk' => $walk, 'i' => $i, 'j' => $j, 'board' => $boardWalk, 'alight' => $alightWalk];
                    }
                }
            }
            if ($best) {
                $leg = self::leg($route, $best['i'], $best['j']);
                $options[] = self::option(0, [$leg], $best['walk'], [
                    ['from' => $from['label'], 'to' => $stops[$best['i']]['name'], 'meters' => $best['board']],
                    ['from' => $stops[$best['j']]['name'], 'to' => $to['label'], 'meters' => $best['alight']],
                ]);
            }
        }

        $byStop = [];
        foreach ($graph as $route) {
            foreach ($route['stops'] as $index => $stop) {
                $byStop[$stop['id']][] = ['route' => $route, 'index' => $index];
            }
        }

        foreach ($graph as $first) {
            $count = count($first['stops']);
            for ($i = 0; $i < $count; $i++) {
                $boardWalk = Geo::meters($from['lat'], $from['lng'], $first['stops'][$i]['lat'], $first['stops'][$i]['lng']);
                if ($boardWalk > $access) {
                    continue;
                }
                for ($k = $i + 1; $k < $count; $k++) {
                    $transferStop = $first['stops'][$k];
                    foreach ($byStop as $stopId => $services) {
                        $candidate = null;
                        foreach ($graph as $route) {
                            foreach ($route['stops'] as $stop) {
                                if ((int) $stop['id'] === (int) $stopId) {
                                    $candidate = $stop;
                                    break 2;
                                }
                            }
                        }
                        if (!$candidate) {
                            continue;
                        }
                        $walkTransfer = Geo::meters($transferStop['lat'], $transferStop['lng'], $candidate['lat'], $candidate['lng']);
                        if ($walkTransfer > $transferMax) {
                            continue;
                        }
                        foreach ($services as $service) {
                            $second = $service['route'];
                            if ((int) $second['id'] === (int) $first['id']) {
                                continue;
                            }
                            $m = $service['index'];
                            $secondStops = $second['stops'];
                            for ($j = $m + 1, $n2 = count($secondStops); $j < $n2; $j++) {
                                $alightWalk = Geo::meters($to['lat'], $to['lng'], $secondStops[$j]['lat'], $secondStops[$j]['lng']);
                                if ($alightWalk > $access) {
                                    continue;
                                }
                                $signature = $first['id'] . ':' . $first['stops'][$i]['id'] . ':' . $transferStop['id']
                                    . '|' . $second['id'] . ':' . $candidate['id'] . ':' . $secondStops[$j]['id'];
                                $walk = $boardWalk + $walkTransfer + $alightWalk;
                                $leg1 = self::leg($first, $i, $k);
                                $leg2 = self::leg($second, $m, $j);
                                $option = self::option(1, [$leg1, $leg2], $walk, [
                                    ['from' => $from['label'], 'to' => $first['stops'][$i]['name'], 'meters' => $boardWalk],
                                    ['from' => $transferStop['name'], 'to' => $candidate['name'], 'meters' => $walkTransfer],
                                    ['from' => $secondStops[$j]['name'], 'to' => $to['label'], 'meters' => $alightWalk],
                                ]);
                                $option['signature'] = $signature;
                                $options[] = $option;
                            }
                        }
                    }
                }
            }
        }

        $deduped = [];
        foreach ($options as $option) {
            $key = $option['signature'] ?? self::signature($option);
            if (!isset($deduped[$key]) || $option['walk_m'] < $deduped[$key]['walk_m']) {
                $deduped[$key] = $option;
            }
        }
        $options = array_values($deduped);
        usort($options, static function (array $a, array $b): int {
            return [$a['transfers'], $a['walk_m'], $a['spacing_m']] <=> [$b['transfers'], $b['walk_m'], $b['spacing_m']];
        });

        $direct = array_values(array_filter($options, static fn (array $o): bool => $o['transfers'] === 0));
        $one = array_values(array_filter($options, static fn (array $o): bool => $o['transfers'] === 1));
        $options = array_slice(array_merge(array_slice($direct, 0, 4), array_slice($one, 0, 4)), 0, 6);

        $warnings = self::warnings(array_map(static fn (array $o): array => $o['legs'], $options));
        $suppress = setting('suppress_confirmed_closures', '0') === '1';
        $suppressed = 0;
        $visible = [];
        foreach ($options as $option) {
            $blocked = false;
            foreach ($option['legs'] as &$leg) {
                $leg['warnings'] = $warnings[$leg['route_id']] ?? [];
                $leg['fare_label'] = self::fareLabel((int) $leg['route_id']);
                foreach ($leg['warnings'] as $warning) {
                    if ($suppress && $warning['impact_level'] === 'confirmed_closure') {
                        $blocked = true;
                    }
                }
            }
            unset($leg);
            $option['instructions'] = self::instructions($option);
            $option['limitations'] = self::limitations($option);
            unset($option['signature']);
            if ($blocked) {
                $suppressed++;
                continue;
            }
            $visible[] = $option;
        }

        return [
            'options' => $visible,
            'suppressed' => $suppressed,
            'access_radius_m' => $access,
            'transfer_radius_m' => $transferMax,
            'coverage_note' => COVERAGE_NOTE,
            'rank_note' => 'Results are ranked by fewer transfers, then shorter straight-line walking distance. Ride duration is not used because this dataset has no verified journey times.',
        ];
    }

    private static function graph(): array
    {
        $routes = q_all('SELECT * FROM routes WHERE status = "active" ORDER BY code, id');
        if ($routes === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $routes);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = q_all(
            "SELECT rs.route_id, rs.sequence_no, rs.distance_from_prev_m, s.id, s.name, s.locality, s.latitude, s.longitude
             FROM route_stops rs JOIN stops s ON s.id = rs.stop_id
             WHERE s.active = 1 AND rs.route_id IN ($placeholders)
             ORDER BY rs.route_id, rs.sequence_no",
            $ids
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['route_id']][] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'locality' => $row['locality'],
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'sequence' => (int) $row['sequence_no'],
                'spacing_m' => $row['distance_from_prev_m'] !== null ? (int) $row['distance_from_prev_m'] : null,
            ];
        }
        $graph = [];
        foreach ($routes as $route) {
            $stops = $grouped[(int) $route['id']] ?? [];
            if (count($stops) < 2) {
                continue;
            }
            $route['stops'] = $stops;
            $graph[] = $route;
        }
        return $graph;
    }

    private static function leg(array $route, int $fromIndex, int $toIndex): array
    {
        $slice = array_slice($route['stops'], $fromIndex, $toIndex - $fromIndex + 1);
        $spacing = 0;
        foreach ($slice as $index => $stop) {
            if ($index > 0 && $stop['spacing_m'] !== null) {
                $spacing += $stop['spacing_m'];
            }
        }
        $geometry = [];
        foreach ($slice as $stop) {
            $geometry[] = [round($stop['lng'], 6), round($stop['lat'], 6)];
        }
        return [
            'route_id' => (int) $route['id'],
            'code' => $route['code'],
            'name' => $route['name'],
            'direction' => $route['direction'],
            'is_demo' => (bool) $route['is_demo'],
            'source_name' => $route['source_name'],
            'source_type' => $route['source_type'],
            'verified_at' => $route['verified_at'],
            'limitation_notes' => $route['limitation_notes'],
            'board' => $slice[0],
            'alight' => $slice[count($slice) - 1],
            'stops' => $slice,
            'geometry' => $geometry,
            'spacing_m' => $spacing,
            'warnings' => [],
            'fare_label' => 'Fare not verified',
        ];
    }

    private static function option(int $transfers, array $legs, float $walk, array $walks): array
    {
        $spacing = 0;
        foreach ($legs as $leg) {
            $spacing += $leg['spacing_m'];
        }
        return [
            'transfers' => $transfers,
            'walk_m' => (int) round($walk),
            'spacing_m' => $spacing,
            'legs' => $legs,
            'walks' => $walks,
            'instructions' => [],
            'limitations' => [],
        ];
    }

    private static function signature(array $option): string
    {
        $parts = [];
        foreach ($option['legs'] as $leg) {
            $parts[] = $leg['route_id'] . ':' . $leg['board']['id'] . ':' . $leg['alight']['id'];
        }
        return implode('|', $parts);
    }

    private static function warnings(array $legGroups): array
    {
        $ids = [];
        foreach ($legGroups as $legs) {
            foreach ($legs as $leg) {
                $ids[$leg['route_id']] = true;
            }
        }
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = q_all(
            "SELECT a.route_id, a.impact_level, a.distance_m, a.reason, a.calculated_at, r.id AS report_id, r.public_ref, r.title, r.is_demo
             FROM affected_routes a
             JOIN reports r ON r.id = a.report_id
             WHERE a.active = 1 AND r.status = 'verified' AND a.route_id IN ($placeholders)
             ORDER BY FIELD(a.impact_level, 'confirmed_closure', 'likely', 'possible', 'information')",
            array_keys($ids)
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['route_id']][] = [
                'report_id' => (int) $row['report_id'],
                'public_ref' => $row['public_ref'],
                'title' => $row['title'],
                'impact_level' => $row['impact_level'],
                'impact_label' => impact_label($row['impact_level']),
                'distance_m' => (int) $row['distance_m'],
                'reason' => $row['reason'],
                'calculated_at' => $row['calculated_at'],
                'is_demo' => (bool) $row['is_demo'],
                'summary' => self::warningSummary($row['impact_level']),
            ];
        }
        return $grouped;
    }

    public static function warningSummary(string $level): string
    {
        return match ($level) {
            'confirmed_closure' => 'Confirmed closure from an authoritative source recorded on the report. This label is not inferred from a nearby complaint alone.',
            'likely' => 'Potential disruption reported near this route.',
            'possible' => 'Potential disruption reported near this route.',
            default => 'Nearby report. Impact on travel is uncertain.',
        };
    }

    private static function fareLabel(int $routeId): string
    {
        $fare = q_one(
            'SELECT amount_ghs, source_name, effective_date, confidence, is_demo FROM fare_estimates WHERE route_id = ? ORDER BY effective_date DESC LIMIT 1',
            [$routeId]
        );
        if (!$fare) {
            return 'Fare not verified';
        }
        $prefix = $fare['is_demo'] ? 'Demo fare estimate' : 'Fare estimate';
        return $prefix . ': GHS ' . number_format((float) $fare['amount_ghs'], 2)
            . ' · ' . $fare['source_name'] . ' · ' . $fare['effective_date']
            . ' · confidence ' . $fare['confidence'];
    }

    private static function instructions(array $option): array
    {
        $lines = [];
        $walks = $option['walks'];
        $firstWalk = $walks[0] ?? null;
        if ($firstWalk && $firstWalk['meters'] >= 35) {
            $lines[] = 'Walk about ' . (int) round($firstWalk['meters']) . ' m in a straight line from ' . $firstWalk['from'] . ' to ' . $firstWalk['to'] . '. This is not a surveyed walking path.';
        }
        foreach ($option['legs'] as $index => $leg) {
            $lines[] = 'Board ' . $leg['code'] . ' ' . $leg['name'] . ' (' . $leg['direction'] . ') at ' . $leg['board']['name'] . '.';
            $middle = array_slice($leg['stops'], 1, -1);
            foreach ($middle as $stop) {
                $lines[] = 'Pass ' . $stop['name'] . '.';
            }
            $lines[] = 'Alight at ' . $leg['alight']['name'] . '.';
            if ($index < count($option['legs']) - 1) {
                $transfer = $walks[$index + 1] ?? null;
                if ($transfer && $transfer['meters'] >= 35) {
                    $lines[] = 'Transfer: walk about ' . (int) round($transfer['meters']) . ' m in a straight line from ' . $transfer['from'] . ' to ' . $transfer['to'] . '.';
                } else {
                    $lines[] = 'Transfer at ' . $leg['alight']['name'] . '.';
                }
            }
        }
        $lastWalk = $walks[count($walks) - 1] ?? null;
        if ($lastWalk && $lastWalk['meters'] >= 35 && count($option['legs']) >= 1) {
            $lines[] = 'Walk about ' . (int) round($lastWalk['meters']) . ' m in a straight line from ' . $lastWalk['from'] . ' toward ' . $lastWalk['to'] . '.';
        }
        return $lines;
    }

    private static function limitations(array $option): array
    {
        $notes = ['Duration not verified', 'Fare not verified unless a sourced estimate is shown on a leg'];
        $demo = false;
        foreach ($option['legs'] as $leg) {
            if ($leg['is_demo']) {
                $demo = true;
            }
        }
        if ($demo) {
            array_unshift($notes, 'SAMPLE / DEMO DATA');
        }
        $notes[] = 'Walking distances are straight-line estimates, not a pedestrian network.';
        $notes[] = 'Mapped stop spacing is the straight line between stored stop coordinates, not a driven distance or a timetable.';
        return $notes;
    }
}
