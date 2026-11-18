<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\JourneyPlanner;
use App\Services\MapData;
use App\Services\PlaceSearch;

final class SiteController
{
    public function home(): void
    {
        $highlights = q_all(
            'SELECT r.id, r.public_ref, r.title, r.status, r.severity, r.place_label, r.updated_at, r.is_demo, c.name AS category_name
             FROM reports r JOIN report_categories c ON c.id = r.category_id
             WHERE r.status <> "rejected" ORDER BY r.updated_at DESC LIMIT 4'
        );
        $disruptions = q_all(
            'SELECT a.impact_level, a.calculated_at, a.reason, rt.id AS route_id, rt.code, rt.name, r.id AS report_id, r.public_ref, r.title
             FROM affected_routes a
             JOIN routes rt ON rt.id = a.route_id
             JOIN reports r ON r.id = a.report_id
             WHERE a.active = 1 AND r.status = "verified"
             ORDER BY FIELD(a.impact_level, "confirmed_closure", "likely", "possible", "information"), a.calculated_at DESC
             LIMIT 5'
        );
        view('pages/home', [
            'title' => 'InsideAccra',
            'highlights' => $highlights,
            'disruptions' => $disruptions,
            'payload' => MapData::payload([]),
        ]);
    }

    public function explore(): void
    {
        $filters = [
            'category' => trim((string) ($_GET['category'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ];
        view('pages/explore', [
            'title' => 'Explore map',
            'filters' => $filters,
            'categories' => q_all('SELECT slug, name FROM report_categories WHERE active = 1 ORDER BY name'),
            'payload' => MapData::payload($filters),
        ]);
    }

    public function journey(): void
    {
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $option = max(0, (int) ($_GET['option'] ?? 0));
        $fromRes = $from !== '' ? PlaceSearch::resolve($from) : null;
        $toRes = $to !== '' ? PlaceSearch::resolve($to) : null;
        $result = null;
        if ($fromRes && $toRes && $fromRes['status'] === 'ok' && $toRes['status'] === 'ok') {
            $result = JourneyPlanner::search($fromRes['place'], $toRes['place']);
            if ($result['options'] && $option >= count($result['options'])) {
                $option = 0;
            }
        }
        view('pages/journey', [
            'title' => 'Plan a journey',
            'from' => $from,
            'to' => $to,
            'fromRes' => $fromRes,
            'toRes' => $toRes,
            'result' => $result,
            'option' => $option,
        ]);
    }

    public function route(array $params): void
    {
        $route = q_one('SELECT * FROM routes WHERE id = ?', [(int) $params['id']]);
        if (!$route) {
            abort(404, 'That route is not in the dataset.');
        }
        $stops = q_all(
            'SELECT s.*, rs.sequence_no, rs.distance_from_prev_m FROM route_stops rs JOIN stops s ON s.id = rs.stop_id WHERE rs.route_id = ? ORDER BY rs.sequence_no',
            [(int) $route['id']]
        );
        $geometry = q_one('SELECT geometry_json, source_name, verified_at FROM route_geometries WHERE route_id = ?', [(int) $route['id']]);
        $impacts = q_all(
            'SELECT a.*, r.public_ref, r.title, r.is_demo FROM affected_routes a JOIN reports r ON r.id = a.report_id WHERE a.route_id = ? AND a.active = 1 AND r.status = "verified" ORDER BY a.id',
            [(int) $route['id']]
        );
        $user = current_user();
        $saved = $user && q_one('SELECT id FROM saved_routes WHERE user_id = ? AND route_id = ?', [(int) $user['id'], (int) $route['id']]);
        view('pages/route', [
            'title' => $route['code'] . ' ' . $route['name'],
            'route' => $route,
            'stops' => $stops,
            'geometry' => $geometry ? json_decode((string) $geometry['geometry_json'], true) : null,
            'impacts' => $impacts,
            'saved' => (bool) $saved,
        ]);
    }

    public function stop(array $params): void
    {
        $stop = q_one('SELECT * FROM stops WHERE id = ?', [(int) $params['id']]);
        if (!$stop) {
            abort(404, 'That stop is not in the dataset.');
        }
        $routes = q_all(
            'SELECT rt.* FROM route_stops rs JOIN routes rt ON rt.id = rs.route_id WHERE rs.stop_id = ? ORDER BY rt.code',
            [(int) $stop['id']]
        );
        $reports = q_all(
            'SELECT r.id, r.public_ref, r.title, r.status, r.latitude, r.longitude, r.is_demo, r.updated_at, c.name AS category_name
             FROM reports r JOIN report_categories c ON c.id = r.category_id
             WHERE r.status <> "rejected" ORDER BY r.updated_at DESC LIMIT 200'
        );
        $nearby = [];
        foreach ($reports as $report) {
            $distance = \App\Services\Geo::meters((float) $stop['latitude'], (float) $stop['longitude'], (float) $report['latitude'], (float) $report['longitude']);
            if ($distance <= 500) {
                $report['distance_m'] = (int) round($distance);
                $nearby[] = $report;
            }
        }
        $user = current_user();
        $saved = $user && q_one('SELECT id FROM saved_stops WHERE user_id = ? AND stop_id = ?', [(int) $user['id'], (int) $stop['id']]);
        view('pages/stop', [
            'title' => $stop['name'],
            'stop' => $stop,
            'routes' => $routes,
            'nearby' => $nearby,
            'saved' => (bool) $saved,
        ]);
    }

    public function privacy(): void
    {
        view('pages/privacy', ['title' => 'Privacy and data']);
    }
}
