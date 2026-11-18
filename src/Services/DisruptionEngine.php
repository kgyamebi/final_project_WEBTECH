<?php
declare(strict_types=1);

namespace App\Services;

final class DisruptionEngine
{
    public static function syncReport(int $reportId): void
    {
        $report = self::load($reportId);
        if (!$report) {
            return;
        }
        q_exec('UPDATE affected_routes SET active = 0 WHERE report_id = ?', [$reportId]);
        if ($report['status'] !== 'verified' || is_stale_report($report)) {
            return;
        }
        $rule = json_decode((string) $report['route_impact_rule'], true);
        if (!is_array($rule)) {
            return;
        }
        $routes = q_all('SELECT * FROM routes WHERE status = "active"');
        $now = utc_now();
        foreach ($routes as $route) {
            $impact = self::assess($report, $route, $rule);
            if (!$impact) {
                continue;
            }
            q_insert(
                'INSERT INTO affected_routes (report_id, route_id, impact_level, distance_m, reason, calculated_at, active) VALUES (?, ?, ?, ?, ?, ?, 1)',
                [$reportId, (int) $route['id'], $impact['level'], $impact['distance_m'], $impact['reason'], $now]
            );
            self::notifySaved($report, $route, $impact['level']);
        }
    }

    public static function recalculateAll(): int
    {
        $ids = q_all('SELECT id FROM reports');
        foreach ($ids as $row) {
            self::syncReport((int) $row['id']);
        }
        return count($ids);
    }

    public static function assess(array $report, array $route, array $rule): ?array
    {
        $geometry = q_one('SELECT geometry_json FROM route_geometries WHERE route_id = ?', [(int) $route['id']]);
        if (!$geometry) {
            return null;
        }
        $decoded = json_decode((string) $geometry['geometry_json'], true);
        $coordinates = $decoded['coordinates'] ?? null;
        if (!is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }
        $line = [];
        foreach ($coordinates as $pair) {
            if (!is_array($pair) || count($pair) < 2) {
                continue;
            }
            $line[] = [(float) $pair[1], (float) $pair[0]];
        }
        $lineDistance = Geo::pointToLineMeters((float) $report['latitude'], (float) $report['longitude'], $line);
        if ($lineDistance === null) {
            return null;
        }

        $stops = q_all(
            'SELECT s.id, s.name, s.latitude, s.longitude FROM route_stops rs JOIN stops s ON s.id = rs.stop_id WHERE rs.route_id = ? AND s.active = 1',
            [(int) $route['id']]
        );
        $nearest = null;
        $nearestName = 'a stop';
        foreach ($stops as $stop) {
            $distance = Geo::meters((float) $report['latitude'], (float) $report['longitude'], (float) $stop['latitude'], (float) $stop['longitude']);
            if ($nearest === null || $distance < $nearest) {
                $nearest = $distance;
                $nearestName = $stop['name'];
            }
        }
        if ($nearest === null) {
            return null;
        }

        $likelyLine = (float) ($rule['corridor_likely_m'] ?? 0);
        $possibleLine = (float) ($rule['corridor_possible_m'] ?? 0);
        $likelyStop = (float) ($rule['stop_likely_m'] ?? 0);
        $possibleStop = (float) ($rule['stop_possible_m'] ?? 0);
        $level = null;
        if ($lineDistance <= $likelyLine || $nearest <= $likelyStop) {
            $level = 'likely';
        } elseif ($lineDistance <= $possibleLine || $nearest <= $possibleStop) {
            $level = 'possible';
        }
        if ($level === null) {
            return null;
        }
        $max = (string) ($rule['max_level'] ?? 'possible');
        if (!isset(IMPACT_RANK[$max])) {
            $max = 'possible';
        }
        if (IMPACT_RANK[$level] > IMPACT_RANK[$max]) {
            $level = $max;
        }

        $authoritative = !empty($report['authoritative_closure']) && trim((string) ($report['closure_source'] ?? '')) !== '';
        if ($authoritative && $lineDistance <= 60) {
            $level = 'confirmed_closure';
        }

        $distance = (int) round(min($lineDistance, $nearest));
        if ($level === 'confirmed_closure') {
            $reason = 'Confirmed closure recorded with source: ' . trim((string) $report['closure_source'])
                . '. Mapped line is about ' . (int) round($lineDistance) . ' m away. This label requires that source; proximity alone is not treated as a closure.';
        } else {
            $reason = 'Potential disruption: verified ' . strtolower((string) $report['category_name'])
                . ' is about ' . (int) round($lineDistance) . ' m from the mapped route line and '
                . (int) round($nearest) . ' m from the nearest stop (' . $nearestName
                . '). Proximity does not mean the road is closed.';
        }
        if (strlen($reason) > 500) {
            $reason = substr($reason, 0, 497) . '...';
        }
        return ['level' => $level, 'distance_m' => $distance, 'reason' => $reason];
    }

    private static function load(int $reportId): ?array
    {
        return q_one(
            'SELECT r.*, c.name AS category_name, c.slug AS category_slug, c.route_impact_rule
             FROM reports r JOIN report_categories c ON c.id = r.category_id WHERE r.id = ?',
            [$reportId]
        );
    }

    private static function notifySaved(array $report, array $route, string $level): void
    {
        $users = q_all(
            'SELECT u.id FROM saved_routes sr JOIN users u ON u.id = sr.user_id
             WHERE sr.route_id = ? AND u.notify_disruptions = 1 AND u.account_status = "active"',
            [(int) $route['id']]
        );
        $title = 'Possible disruption on ' . $route['code'];
        $body = impact_label($level) . ' near saved route ' . $route['name']
            . '. Report ' . $report['public_ref'] . '. This is a proximity warning, not a confirmed closure unless labelled as one.';
        foreach ($users as $user) {
            notify((int) $user['id'], 'disruption', $title, $body, (int) $report['id'], (int) $route['id']);
        }
    }
}
