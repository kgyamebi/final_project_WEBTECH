<?php
declare(strict_types=1);

namespace App\Services;

final class MapData
{
    public static function payload(array $filters): array
    {
        $routes = q_all('SELECT * FROM routes ORDER BY code, id');
        $routeIds = array_map(static fn (array $row): int => (int) $row['id'], $routes);
        $stopsByRoute = [];
        if ($routeIds) {
            $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
            $stopRows = q_all(
                "SELECT rs.route_id, rs.sequence_no, s.id, s.name, s.locality, s.latitude, s.longitude, s.active
                 FROM route_stops rs JOIN stops s ON s.id = rs.stop_id
                 WHERE rs.route_id IN ($placeholders) ORDER BY rs.route_id, rs.sequence_no",
                $routeIds
            );
            foreach ($stopRows as $row) {
                $stopsByRoute[(int) $row['route_id']][] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'locality' => $row['locality'],
                    'latitude' => (float) $row['latitude'],
                    'longitude' => (float) $row['longitude'],
                    'sequence' => (int) $row['sequence_no'],
                    'active' => (bool) $row['active'],
                ];
            }
        }
        $impacts = q_all(
            'SELECT a.route_id, a.impact_level, a.distance_m, a.calculated_at, r.id AS report_id, r.public_ref, r.title
             FROM affected_routes a JOIN reports r ON r.id = a.report_id
             WHERE a.active = 1 AND r.status = "verified"'
        );
        $impactByRoute = [];
        foreach ($impacts as $impact) {
            $impactByRoute[(int) $impact['route_id']][] = [
                'report_id' => (int) $impact['report_id'],
                'public_ref' => $impact['public_ref'],
                'title' => $impact['title'],
                'impact_level' => $impact['impact_level'],
                'impact_label' => impact_label($impact['impact_level']),
                'distance_m' => (int) $impact['distance_m'],
                'calculated_at' => $impact['calculated_at'],
                'summary' => JourneyPlanner::warningSummary($impact['impact_level']),
            ];
        }

        $routePayload = [];
        foreach ($routes as $route) {
            $geometry = q_one('SELECT geometry_json, source_name, verified_at FROM route_geometries WHERE route_id = ?', [(int) $route['id']]);
            $routePayload[] = [
                'id' => (int) $route['id'],
                'code' => $route['code'],
                'name' => $route['name'],
                'direction' => $route['direction'],
                'origin_label' => $route['origin_label'],
                'destination_label' => $route['destination_label'],
                'status' => $route['status'],
                'is_demo' => (bool) $route['is_demo'],
                'source_name' => $route['source_name'],
                'verified_at' => $route['verified_at'],
                'color' => route_color((int) $route['id']),
                'geometry' => $geometry ? json_decode((string) $geometry['geometry_json'], true) : null,
                'stops' => $stopsByRoute[(int) $route['id']] ?? [],
                'impacts' => $impactByRoute[(int) $route['id']] ?? [],
            ];
        }

        $stops = q_all('SELECT id, name, locality, latitude, longitude, is_demo, active, verified_at, source_name FROM stops ORDER BY name');
        $stopPayload = array_map(static fn (array $stop): array => [
            'id' => (int) $stop['id'],
            'name' => $stop['name'],
            'locality' => $stop['locality'],
            'latitude' => (float) $stop['latitude'],
            'longitude' => (float) $stop['longitude'],
            'is_demo' => (bool) $stop['is_demo'],
            'active' => (bool) $stop['active'],
            'verified_at' => $stop['verified_at'],
            'source_name' => $stop['source_name'],
        ], $stops);

        [$where, $params] = self::incidentFilters($filters);
        $incidentRows = q_all(
            'SELECT r.id, r.public_ref, r.title, r.description, r.latitude, r.longitude, r.place_label, r.observed_at, r.created_at, r.updated_at, r.resolved_at, r.status, r.severity, r.is_demo, r.duplicate_of_id, c.name AS category_name, c.slug AS category_slug
             FROM reports r JOIN report_categories c ON c.id = r.category_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY r.updated_at DESC LIMIT 300',
            $params
        );
        $incidents = array_map(static fn (array $row): array => public_incident($row), $incidentRows);

        return [
            'demo' => true,
            'coverage_note' => COVERAGE_NOTE,
            'center' => ['latitude' => 5.603, 'longitude' => -0.205],
            'routes' => $routePayload,
            'stops' => $stopPayload,
            'incidents' => $incidents,
        ];
    }

    public static function incidentFilters(array $filters): array
    {
        $where = ['r.status <> "rejected"'];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && isset(REPORT_STATUSES[$status]) && $status !== 'rejected') {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        $category = (string) ($filters['category'] ?? '');
        if ($category !== '') {
            $where[] = 'c.slug = ?';
            $params[] = $category;
        }
        $from = (string) ($filters['from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'r.observed_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        $to = (string) ($filters['to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'r.observed_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        return [$where, $params];
    }
}
