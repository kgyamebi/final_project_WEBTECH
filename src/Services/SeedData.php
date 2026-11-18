<?php
declare(strict_types=1);

namespace App\Services;

final class SeedData
{
    public const LIMITATION = 'SAMPLE / DEMO DATA. Illustrative stop sequence for a selected Accra corridor. Not an official GPRTU, municipal, or operator listing. Geometry is a straight line between demo stop coordinates, not a surveyed road centreline. Fares and journey times are not included.';

    public static function run(): void
    {
        $pdo = db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'submission_attempts', 'login_attempts', 'audit_logs', 'notifications', 'saved_stops', 'saved_routes',
            'affected_routes', 'report_flags', 'report_notes', 'report_status_history', 'report_media', 'reports',
            'fare_estimates', 'route_geometries', 'route_stops', 'places', 'stops', 'routes', 'report_categories',
            'settings', 'users', 'roles',
        ] as $table) {
            $pdo->exec('TRUNCATE TABLE `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $now = utc_now();
        $checked = '2026-11-18 08:00:00';
        foreach ([
            [1, 'user', 'Registered resident or commuter'],
            [2, 'moderator', 'Reviews community reports'],
            [3, 'admin', 'Manages routes, stops, users, and settings'],
        ] as [$id, $name, $description]) {
            q_insert('INSERT INTO roles (id, name, description) VALUES (?, ?, ?)', [$id, $name, $description]);
        }
        $password = password_hash('DemoPass123!', PASSWORD_DEFAULT);
        $users = [
            ['Ama Boateng', 'ama@example.com', 1],
            ['Kofi Mensah', 'kofi.mod@example.com', 2],
            ['Abena Owusu', 'admin@example.com', 3],
        ];
        $userIds = [];
        foreach ($users as [$name, $email, $role]) {
            $userIds[$email] = q_insert(
                'INSERT INTO users (name, email, password_hash, role_id, account_status, notify_disruptions, created_at, updated_at) VALUES (?, ?, ?, ?, "active", 1, ?, ?)',
                [$name, $email, $password, $role, $now, $now]
            );
        }
        foreach ([
            'access_radius_m' => '1000',
            'transfer_radius_m' => '400',
            'stale_days' => '21',
            'suppress_confirmed_closures' => '0',
        ] as $key => $value) {
            q_insert('INSERT INTO settings (skey, svalue, updated_at) VALUES (?, ?, ?)', [$key, $value, $now]);
        }

        $categories = [
            ['Road damage', 'road_damage', 'Potholes, damaged surface, unsafe road edges.', ['corridor_likely_m' => 40, 'corridor_possible_m' => 110, 'stop_likely_m' => 35, 'stop_possible_m' => 80, 'max_level' => 'likely']],
            ['Flooding / drainage', 'flooding', 'Flooded road, blocked drain, standing water, overflow.', ['corridor_likely_m' => 55, 'corridor_possible_m' => 150, 'stop_likely_m' => 40, 'stop_possible_m' => 100, 'max_level' => 'likely']],
            ['Traffic infrastructure', 'traffic_infrastructure', 'Broken traffic lights, damaged signs, missing road markings.', ['corridor_likely_m' => 20, 'corridor_possible_m' => 80, 'stop_likely_m' => 20, 'stop_possible_m' => 60, 'max_level' => 'information']],
            ['Transport stop / station', 'transport_stop', 'Damaged facilities, unsafe access, or an obstruction around a station or stop.', ['corridor_likely_m' => 30, 'corridor_possible_m' => 70, 'stop_likely_m' => 50, 'stop_possible_m' => 120, 'max_level' => 'likely']],
            ['Obstruction / hazard', 'obstruction', 'Fallen object, debris, road obstruction, or travel hazard.', ['corridor_likely_m' => 35, 'corridor_possible_m' => 100, 'stop_likely_m' => 30, 'stop_possible_m' => 80, 'max_level' => 'likely']],
            ['Sanitation / environment', 'sanitation', 'Waste accumulation or public-space conditions affecting movement.', ['corridor_likely_m' => 15, 'corridor_possible_m' => 70, 'stop_likely_m' => 15, 'stop_possible_m' => 50, 'max_level' => 'information']],
        ];
        $categoryIds = [];
        foreach ($categories as [$name, $slug, $description, $rule]) {
            $categoryIds[$slug] = q_insert(
                'INSERT INTO report_categories (name, slug, description, route_impact_rule, active) VALUES (?, ?, ?, ?, 1)',
                [$name, $slug, $description, json_encode($rule)]
            );
        }

        $stopDefs = [
            ['madina', 'Madina Station', 'Madina', 5.6836, -0.1667],
            ['atomic', 'Atomic Junction', 'Atomic', 5.6684, -0.1654],
            ['legon', 'Legon Station', 'Legon', 5.6505, -0.1864],
            ['okponglo', 'Okponglo', 'Okponglo', 5.6368, -0.1788],
            ['accra_mall', 'Accra Mall', 'Tetteh Quarshie', 5.6222, -0.1736],
            ['airport', 'Airport City', 'Airport', 5.6058, -0.1772],
            ['thirtyseven', '37 Military Hospital', '37', 5.5896, -0.1852],
            ['nima', 'Nima', 'Nima', 5.5822, -0.1978],
            ['circle', 'Kwame Nkrumah Circle', 'Circle', 5.5710, -0.2110],
            ['kaneshie', 'Kaneshie Market', 'Kaneshie', 5.5732, -0.2368],
            ['odorkor', 'Odorkor', 'Odorkor', 5.5784, -0.2594],
            ['mallam', 'Mallam Junction', 'Mallam', 5.5738, -0.2892],
            ['tudu', 'Tudu', 'Tudu', 5.5548, -0.2118],
            ['tema_station', 'Tema Station', 'Accra Central', 5.5526, -0.2086],
            ['makola', 'Makola', 'Makola', 5.5496, -0.2062],
            ['osu', 'Osu Oxford Street', 'Osu', 5.5568, -0.1816],
            ['labadi', 'Labadi Beach Road', 'La', 5.5608, -0.1502],
            ['achimota', 'Achimota', 'Achimota', 5.6202, -0.2308],
            ['lapaz', 'Lapaz', 'Lapaz', 5.6054, -0.2476],
            ['abeka', 'Abeka', 'Abeka', 5.5948, -0.2412],
            ['tesano', 'Tesano', 'Tesano', 5.5886, -0.2248],
        ];
        $stopIds = [];
        foreach ($stopDefs as [$key, $name, $locality, $lat, $lng]) {
            $stopIds[$key] = q_insert(
                'INSERT INTO stops (name, locality, latitude, longitude, location, description, facilities, accessibility_notes, source_name, source_type, confidence, is_demo, active, verified_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ' . point_expr() . ', ?, ?, ?, ?, "demo", "demo", 1, 1, ?, ?, ?)',
                [
                    $name, $locality, $lat, $lng, $lng, $lat,
                    'Demo stop used for sample journey search. Coordinates are approximate public landmarks, not a surveyed bay.',
                    'Facilities are not field-verified.',
                    'Step-free access is not verified.',
                    'InsideAccra sample dataset',
                    $checked, $now, $now,
                ]
            );
            q_insert(
                'INSERT INTO places (name, latitude, longitude, kind, is_demo) VALUES (?, ?, ?, "stop-alias", 1)',
                [$name, $lat, $lng]
            );
            q_insert(
                'INSERT INTO places (name, latitude, longitude, kind, is_demo) VALUES (?, ?, ?, "locality", 1)',
                [$locality, $lat, $lng]
            );
        }

        $routes = [
            ['IA-01', 'Madina – Circle', 'Madina', 'Kwame Nkrumah Circle', 'Madina to Circle', ['madina', 'atomic', 'legon', 'okponglo', 'accra_mall', 'airport', 'thirtyseven', 'nima', 'circle']],
            ['IA-02', 'Circle – Madina', 'Kwame Nkrumah Circle', 'Madina', 'Circle to Madina', array_reverse(['madina', 'atomic', 'legon', 'okponglo', 'accra_mall', 'airport', 'thirtyseven', 'nima', 'circle'])],
            ['IA-03', 'Circle – Mallam', 'Kwame Nkrumah Circle', 'Mallam', 'Circle to Mallam', ['circle', 'kaneshie', 'odorkor', 'mallam']],
            ['IA-04', 'Mallam – Circle', 'Mallam', 'Kwame Nkrumah Circle', 'Mallam to Circle', ['mallam', 'odorkor', 'kaneshie', 'circle']],
            ['IA-05', 'Circle – Labadi', 'Kwame Nkrumah Circle', 'La', 'Circle to Labadi', ['circle', 'tudu', 'tema_station', 'makola', 'osu', 'labadi']],
            ['IA-06', 'Labadi – Circle', 'La', 'Kwame Nkrumah Circle', 'Labadi to Circle', ['labadi', 'osu', 'makola', 'tema_station', 'tudu', 'circle']],
            ['IA-07', 'Achimota – Circle', 'Achimota', 'Kwame Nkrumah Circle', 'Achimota to Circle', ['achimota', 'lapaz', 'abeka', 'tesano', 'circle']],
            ['IA-08', 'Circle – Achimota', 'Kwame Nkrumah Circle', 'Achimota', 'Circle to Achimota', ['circle', 'tesano', 'abeka', 'lapaz', 'achimota']],
        ];
        $routeIds = [];
        foreach ($routes as [$code, $name, $origin, $destination, $direction, $keys]) {
            $routeId = q_insert(
                'INSERT INTO routes (code, name, origin_label, destination_label, direction, description, status, source_name, source_type, confidence, limitation_notes, is_demo, verified_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, "active", ?, "demo", "demo", ?, 1, ?, ?, ?)',
                [$code, $name, $origin, $destination, $direction, self::LIMITATION, 'InsideAccra sample dataset', self::LIMITATION, $checked, $now, $now]
            );
            $ordered = [];
            foreach ($keys as $key) {
                $ordered[] = ['id' => $stopIds[$key], 'latitude' => self::latOf($stopDefs, $key), 'longitude' => self::lngOf($stopDefs, $key)];
            }
            NetworkAdmin::writeStopsAndGeometry($routeId, $direction, $ordered, 'InsideAccra sample dataset', $checked);
            $routeIds[$code] = $routeId;
        }

        $ama = $userIds['ama@example.com'];
        $mod = $userIds['kofi.mod@example.com'];
        $samples = [
            ['IA-DEMO-FLOOD', 'flooding', 'Demo flooding near Okponglo', 'SAMPLE / DEMO DATA. Standing water is shown on the Madina–Circle sample corridor so the disruption engine can be demonstrated. This is not a live road report.', 5.6297, -0.1763, 'Okponglo – Accra Mall corridor', 'verified', 'high', -2, -1, $mod, 'Verified for the demo dataset. The point sits on the sample route line. Proximity is not treated as a closure.'],
            ['IA-DEMO-POTHOLE', 'road_damage', 'Demo pothole near Kaneshie', 'SAMPLE / DEMO DATA. A pending, unverified pothole near Kaneshie Market. It must not create a route-closure warning.', 5.5734, -0.2366, 'Kaneshie Market', 'pending_review', 'medium', -1, null, null, null],
            ['IA-DEMO-STOP', 'transport_stop', 'Demo damaged stop access at Kaneshie', 'SAMPLE / DEMO DATA. Verified sample report of unsafe access at the Kaneshie demo stop.', 5.5732, -0.2368, 'Kaneshie Market', 'verified', 'medium', -3, -2, $mod, 'Verified for the demo. The report is on the sample stop, so routes serving that stop can show a warning.'],
            ['IA-DEMO-LAPAZ', 'obstruction', 'Demo debris at Lapaz, now resolved', 'SAMPLE / DEMO DATA. This obstruction was marked resolved. It should not keep an active disruption warning.', 5.6054, -0.2476, 'Lapaz', 'resolved', 'low', -8, -6, $mod, 'Resolved in the sample dataset. The debris is recorded as no longer present.'],
            ['IA-DEMO-OSU', 'sanitation', 'Demo waste near Osu, under review', 'SAMPLE / DEMO DATA. Waste affecting the walk to the Osu sample stop. Still under review, so it is not a verified disruption.', 5.5572, -0.1810, 'Osu', 'under_review', 'low', -1, null, $mod, 'Moved to under review for the demo queue.'],
            ['IA-DEMO-MALLAM', 'road_damage', 'Demo road edge at Mallam needs detail', 'SAMPLE / DEMO DATA. The moderator has asked for a clearer description of which side of the junction is damaged.', 5.5742, -0.2888, 'Mallam Junction', 'needs_more_information', 'medium', -4, -3, $mod, 'Please add which approach to Mallam Junction is affected and whether trotros can still load.'],
            ['IA-DEMO-REJECT', 'obstruction', 'Demo rejected notice', 'SAMPLE / DEMO DATA. This submission was rejected as out of scope so the rejected state can be shown to the reporter only.', 5.5715, -0.2105, 'Circle', 'rejected', 'low', -5, -4, $mod, 'Rejected in the sample dataset: the description does not describe a road or transport condition.'],
        ];
        $reportIds = [];
        foreach ($samples as $sample) {
            [$ref, $slug, $title, $description, $lat, $lng, $place, $status, $severity, $observedDays, $reviewDays, $reviewer, $reason] = $sample;
            $created = gmdate('Y-m-d H:i:s', time() + $observedDays * 86400);
            $observed = $created;
            $updated = $reviewDays === null ? $created : gmdate('Y-m-d H:i:s', time() + $reviewDays * 86400);
            $resolvedAt = $status === 'resolved' ? $updated : null;
            $id = q_insert(
                'INSERT INTO reports (public_ref, reporter_id, category_id, title, description, latitude, longitude, location, place_label, observed_at, status, severity, is_demo, resolved_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ' . point_expr() . ', ?, ?, ?, ?, 1, ?, ?, ?)',
                [$ref, $ama, $categoryIds[$slug], $title, $description, $lat, $lng, $lng, $lat, $place, $observed, $status, $severity, $resolvedAt, $created, $updated]
            );
            q_insert(
                'INSERT INTO report_status_history (report_id, actor_id, old_status, new_status, reason, created_at) VALUES (?, ?, NULL, "pending_review", ?, ?)',
                [$id, $ama, 'Report submitted', $created]
            );
            if ($status !== 'pending_review' && $reason !== null && $reviewer !== null) {
                q_insert(
                    'INSERT INTO report_status_history (report_id, actor_id, old_status, new_status, reason, created_at) VALUES (?, ?, "pending_review", ?, ?, ?)',
                    [$id, $reviewer, $status === 'resolved' ? 'verified' : $status, $reason, $updated]
                );
            }
            if ($status === 'resolved') {
                q_insert(
                    'INSERT INTO report_status_history (report_id, actor_id, old_status, new_status, reason, created_at) VALUES (?, ?, "verified", "resolved", ?, ?)',
                    [$id, $reviewer, 'Marked resolved in the sample dataset after the earlier verification.', $updated]
                );
            }
            if ($status === 'needs_more_information') {
                q_insert(
                    'INSERT INTO report_notes (report_id, author_id, note, visibility, created_at) VALUES (?, ?, ?, "reporter", ?)',
                    [$id, $mod, $reason, $updated]
                );
            }
            $reportIds[$ref] = $id;
        }
        $dupId = q_insert(
            'INSERT INTO reports (public_ref, reporter_id, category_id, title, description, latitude, longitude, location, place_label, observed_at, status, severity, duplicate_of_id, is_demo, created_at, updated_at)
             VALUES ("IA-DEMO-DUP", ?, ?, ?, ?, ?, ?, ' . point_expr() . ', ?, ?, "duplicate", "medium", ?, 1, ?, ?)',
            [
                $ama, $categoryIds['flooding'], 'Demo duplicate flood report',
                'SAMPLE / DEMO DATA. A second submission of the same sample flood, linked to IA-DEMO-FLOOD.',
                5.6299, -0.1765, -0.1765, 5.6299, 'Okponglo corridor',
                gmdate('Y-m-d H:i:s', time() - 86400), $reportIds['IA-DEMO-FLOOD'],
                gmdate('Y-m-d H:i:s', time() - 80000), $now,
            ]
        );
        q_insert(
            'INSERT INTO report_status_history (report_id, actor_id, old_status, new_status, reason, created_at) VALUES (?, ?, NULL, "pending_review", "Report submitted", ?)',
            [$dupId, $ama, gmdate('Y-m-d H:i:s', time() - 86400)]
        );
        q_insert(
            'INSERT INTO report_status_history (report_id, actor_id, old_status, new_status, reason, created_at) VALUES (?, ?, "pending_review", "duplicate", ?, ?)',
            [$dupId, $mod, 'Same sample flood as IA-DEMO-FLOOD.', $now]
        );

        q_insert('INSERT INTO saved_routes (user_id, route_id, created_at) VALUES (?, ?, ?)', [$ama, $routeIds['IA-01'], $now]);
        q_insert('INSERT INTO saved_stops (user_id, stop_id, created_at) VALUES (?, ?, ?)', [$ama, $stopIds['circle'], $now]);

        DisruptionEngine::recalculateAll();
        notify(
            $ama,
            'status_changed',
            'Report IA-DEMO-FLOOD: Verified',
            'Sample report IA-DEMO-FLOOD is Verified by a moderator. It can flag nearby sample routes. It is still demo data.',
            $reportIds['IA-DEMO-FLOOD'],
            null
        );
    }

    private static function latOf(array $defs, string $key): float
    {
        foreach ($defs as $def) {
            if ($def[0] === $key) {
                return (float) $def[3];
            }
        }
        throw new \RuntimeException('Missing stop ' . $key);
    }

    private static function lngOf(array $defs, string $key): float
    {
        foreach ($defs as $def) {
            if ($def[0] === $key) {
                return (float) $def[4];
            }
        }
        throw new \RuntimeException('Missing stop ' . $key);
    }
}
