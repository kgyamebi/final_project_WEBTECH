<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\JourneyPlanner;
use App\Services\MapData;
use App\Services\ModerationService;
use App\Services\NetworkAdmin;
use App\Services\PlaceSearch;
use App\Services\ReportService;

final class ApiController
{
    public function health(): void
    {
        q_one('SELECT 1 AS ok');
        json_out(['ok' => true, 'data' => ['app' => 'InsideAccra', 'database' => true]]);
    }

    public function map(): void
    {
        json_out(['ok' => true, 'data' => MapData::payload([
            'category' => trim((string) ($_GET['category'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ])]);
    }

    public function places(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        json_out(['ok' => true, 'data' => PlaceSearch::suggest($q)]);
    }

    public function routes(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $q = trim((string) ($_GET['q'] ?? ''));
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(code LIKE ? OR name LIKE ? OR origin_label LIKE ? OR destination_label LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like, $like];
        }
        $total = (int) q_one("SELECT COUNT(*) AS c FROM routes WHERE $where", $params)['c'];
        $pager = pager($total, $page, 20);
        $rows = q_all(
            "SELECT id, code, name, origin_label, destination_label, direction, status, source_name, source_type, confidence, is_demo, verified_at, limitation_notes FROM routes WHERE $where ORDER BY code LIMIT " . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset'],
            $params
        );
        json_out(['ok' => true, 'data' => $rows, 'meta' => $pager]);
    }

    public function route(array $params): void
    {
        $route = q_one('SELECT * FROM routes WHERE id = ?', [(int) $params['id']]);
        if (!$route) {
            abort(404, 'Route not found.');
        }
        $stops = q_all(
            'SELECT s.id, s.name, s.locality, s.latitude, s.longitude, rs.sequence_no, rs.distance_from_prev_m FROM route_stops rs JOIN stops s ON s.id = rs.stop_id WHERE rs.route_id = ? ORDER BY rs.sequence_no',
            [(int) $route['id']]
        );
        $geometry = q_one('SELECT geometry_json, source_name, verified_at FROM route_geometries WHERE route_id = ?', [(int) $route['id']]);
        json_out(['ok' => true, 'data' => [
            'route' => $route,
            'stops' => $stops,
            'geometry' => $geometry ? json_decode((string) $geometry['geometry_json'], true) : null,
            'impacts' => ReportService::publicImpacts((int) $params['id']),
            'fare' => 'Fare not verified',
            'duration' => 'Duration not verified',
        ]]);
    }

    public function stops(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $where = 'active = 1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (name LIKE ? OR locality LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like];
        }
        if (isset($_GET['south'], $_GET['north'], $_GET['west'], $_GET['east'])) {
            $where .= ' AND latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?';
            array_push($params, (float) $_GET['south'], (float) $_GET['north'], (float) $_GET['west'], (float) $_GET['east']);
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) q_one("SELECT COUNT(*) AS c FROM stops WHERE $where", $params)['c'];
        $pager = pager($total, $page, 30);
        $rows = q_all(
            "SELECT id, name, locality, latitude, longitude, description, facilities, accessibility_notes, source_name, source_type, confidence, is_demo, verified_at FROM stops WHERE $where ORDER BY name LIMIT " . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset'],
            $params
        );
        json_out(['ok' => true, 'data' => $rows, 'meta' => $pager]);
    }

    public function journeys(): void
    {
        $from = null;
        $to = null;
        if (isset($_GET['from_lat'], $_GET['from_lng'], $_GET['to_lat'], $_GET['to_lng']) && is_numeric($_GET['from_lat']) && is_numeric($_GET['to_lat'])) {
            $from = ['label' => 'Origin', 'lat' => (float) $_GET['from_lat'], 'lng' => (float) $_GET['from_lng'], 'kind' => 'coordinates'];
            $to = ['label' => 'Destination', 'lat' => (float) $_GET['to_lat'], 'lng' => (float) $_GET['to_lng'], 'kind' => 'coordinates'];
            if (!in_accra($from['lat'], $from['lng']) || !in_accra($to['lat'], $to['lng'])) {
                json_out(['ok' => true, 'data' => ['status' => 'outside', 'message' => 'Those coordinates are outside the covered Accra area.', 'options' => []]]);
            }
        } else {
            $fromRes = PlaceSearch::resolve((string) ($_GET['from'] ?? ''));
            $toRes = PlaceSearch::resolve((string) ($_GET['to'] ?? ''));
            if ($fromRes['status'] !== 'ok' || $toRes['status'] !== 'ok') {
                json_out(['ok' => true, 'data' => ['from' => $fromRes, 'to' => $toRes, 'options' => []]]);
            }
            $from = $fromRes['place'];
            $to = $toRes['place'];
        }
        $result = JourneyPlanner::search($from, $to);
        $result['from'] = $from;
        $result['to'] = $to;
        json_out(['ok' => true, 'data' => $result]);
    }

    public function incidents(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        [$where, $params] = MapData::incidentFilters([
            'category' => (string) ($_GET['category'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'from' => (string) ($_GET['from'] ?? ''),
            'to' => (string) ($_GET['to'] ?? ''),
        ]);
        $total = (int) q_one('SELECT COUNT(*) AS c FROM reports r JOIN report_categories c ON c.id = r.category_id WHERE ' . implode(' AND ', $where), $params)['c'];
        $pager = pager($total, $page, 20);
        $rows = q_all(
            'SELECT r.id, r.public_ref, r.title, r.description, r.latitude, r.longitude, r.place_label, r.observed_at, r.created_at, r.updated_at, r.resolved_at, r.status, r.severity, r.is_demo, r.duplicate_of_id, c.name AS category_name, c.slug AS category_slug
             FROM reports r JOIN report_categories c ON c.id = r.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset'],
            $params
        );
        json_out(['ok' => true, 'data' => array_map(static fn (array $row): array => public_incident($row), $rows), 'meta' => $pager]);
    }

    public function incident(array $params): void
    {
        $report = ReportService::find((int) $params['id']);
        if (!$report || !report_is_public($report)) {
            abort(404, 'Incident not found.');
        }
        json_out(['ok' => true, 'data' => public_incident(
            $report,
            ReportService::publicMedia((int) $report['id']),
            $report['status'] === 'verified' ? ReportService::publicImpacts((int) $report['id']) : []
        ) + ['history' => ReportService::visibleHistory((int) $report['id'])]]);
    }

    public function createReport(): void
    {
        $user = require_user();
        $report = ReportService::create($user, request_data(), $_FILES);
        json_out(['ok' => true, 'data' => public_incident($report)], 201);
    }

    public function myReports(): void
    {
        $user = require_user();
        $rows = q_all(
            'SELECT r.*, c.name AS category_name, c.slug AS category_slug FROM reports r JOIN report_categories c ON c.id = r.category_id WHERE r.reporter_id = ? ORDER BY r.created_at DESC',
            [(int) $user['id']]
        );
        json_out(['ok' => true, 'data' => array_map(static fn (array $row): array => public_incident($row), $rows)]);
    }

    public function myReport(array $params): void
    {
        $user = require_user();
        $report = ReportService::find((int) $params['id']);
        if (!$report || (int) $report['reporter_id'] !== (int) $user['id']) {
            abort(403, 'You cannot open another person\'s report.');
        }
        json_out(['ok' => true, 'data' => public_incident($report, ReportService::publicMedia((int) $report['id']), ReportService::publicImpacts((int) $report['id'])) + [
            'history' => ReportService::visibleHistory((int) $report['id']),
            'reporter_notes' => ReportService::reporterNotes((int) $report['id']),
        ]]);
    }

    public function updateReport(array $params): void
    {
        $user = require_user();
        $input = request_data();
        ReportService::addClarification($user, (int) $params['id'], (string) ($input['note'] ?? ''));
        json_out(['ok' => true, 'data' => ['id' => (int) $params['id']]]);
    }

    public function me(): void
    {
        $user = require_user();
        json_out(['ok' => true, 'data' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role_name'],
            'notify_disruptions' => (bool) $user['notify_disruptions'],
        ]]);
    }

    public function notifications(): void
    {
        $user = require_user();
        $rows = q_all('SELECT id, type, title, body, related_report_id, related_route_id, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50', [(int) $user['id']]);
        json_out(['ok' => true, 'data' => $rows]);
    }

    public function adminReports(): void
    {
        require_ability('report.moderate');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) q_one('SELECT COUNT(*) AS c FROM reports')['c'];
        $pager = pager($total, $page, 20);
        $rows = q_all(
            'SELECT r.id, r.public_ref, r.title, r.status, r.severity, r.created_at, r.latitude, r.longitude, c.name AS category_name FROM reports r JOIN report_categories c ON c.id = r.category_id ORDER BY r.created_at DESC LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset']
        );
        json_out(['ok' => true, 'data' => $rows, 'meta' => $pager]);
    }

    public function adminStatus(array $params): void
    {
        $actor = require_ability('report.moderate');
        $report = ModerationService::changeStatus($actor, (int) $params['id'], request_data());
        json_out(['ok' => true, 'data' => ['id' => (int) $report['id'], 'status' => $report['status']]]);
    }

    public function adminAnalytics(): void
    {
        require_ability('admin.manage');
        json_out(['ok' => true, 'data' => (new AdminController())->analyticsData()]);
    }

    public function adminCreateRoute(): void
    {
        $actor = require_ability('admin.manage');
        $id = NetworkAdmin::saveRoute(request_data(), null, (int) $actor['id']);
        json_out(['ok' => true, 'data' => ['id' => $id]], 201);
    }

    public function adminUpdateRoute(array $params): void
    {
        $actor = require_ability('admin.manage');
        $id = NetworkAdmin::saveRoute(request_data(), (int) $params['id'], (int) $actor['id']);
        json_out(['ok' => true, 'data' => ['id' => $id]]);
    }

    public function adminCreateStop(): void
    {
        $actor = require_ability('admin.manage');
        $id = NetworkAdmin::saveStop(request_data(), null, (int) $actor['id']);
        json_out(['ok' => true, 'data' => ['id' => $id]], 201);
    }

    public function adminUpdateStop(array $params): void
    {
        $actor = require_ability('admin.manage');
        $id = NetworkAdmin::saveStop(request_data(), (int) $params['id'], (int) $actor['id']);
        json_out(['ok' => true, 'data' => ['id' => $id]]);
    }
}
