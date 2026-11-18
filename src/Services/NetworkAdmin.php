<?php
declare(strict_types=1);

namespace App\Services;

final class NetworkAdmin
{
    public static function saveStop(array $input, ?int $id, int $actorId): int
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $locality = trim((string) ($input['locality'] ?? ''));
        $latRaw = trim((string) ($input['latitude'] ?? ''));
        $lngRaw = trim((string) ($input['longitude'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $facilities = trim((string) ($input['facilities'] ?? ''));
        $access = trim((string) ($input['accessibility_notes'] ?? ''));
        $source = trim((string) ($input['source_name'] ?? ''));
        $sourceType = trim((string) ($input['source_type'] ?? ''));
        $confidence = trim((string) ($input['confidence'] ?? 'demo'));
        if (str_len($name) < 2) {
            $errors['name'] = 'Enter the stop name.';
        }
        if (str_len($locality) < 2) {
            $errors['locality'] = 'Enter the area or locality.';
        }
        if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw) || !in_accra((float) $latRaw, (float) $lngRaw)) {
            $errors['latitude'] = 'Enter latitude and longitude inside the covered Accra area.';
        }
        if (str_len($source) < 3) {
            $errors['source_name'] = 'Record where this stop information came from.';
        }
        if (!in_array($sourceType, ['demo', 'community', 'reviewed', 'official'], true)) {
            $errors['source_type'] = 'Choose a source type.';
        }
        if (!in_array($confidence, ['demo', 'low', 'medium', 'high'], true)) {
            $errors['confidence'] = 'Choose a confidence level.';
        }
        if ($errors) {
            fail_validation($errors, $input);
        }
        $now = utc_now();
        $demo = !empty($input['is_demo']) ? 1 : 0;
        if ($sourceType === 'demo') {
            $demo = 1;
        }
        $params = [$name, $locality, $latRaw, $lngRaw, $lngRaw, $latRaw, $description, $facilities, $access, $source, $sourceType, $confidence, $demo, $now];
        if ($id) {
            q_exec(
                'UPDATE stops SET name=?, locality=?, latitude=?, longitude=?, location=' . point_expr() . ', description=?, facilities=?, accessibility_notes=?, source_name=?, source_type=?, confidence=?, is_demo=?, updated_at=? WHERE id=?',
                [...$params, $id]
            );
            audit($actorId, 'stop.update', 'stop', $id, ['name' => $name]);
            return $id;
        }
        $newId = q_insert(
            'INSERT INTO stops (name, locality, latitude, longitude, location, description, facilities, accessibility_notes, source_name, source_type, confidence, is_demo, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ' . point_expr() . ', ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
            [...$params, $now]
        );
        audit($actorId, 'stop.create', 'stop', $newId, ['name' => $name]);
        return $newId;
    }

    public static function saveRoute(array $input, ?int $id, int $actorId): int
    {
        $errors = [];
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $origin = trim((string) ($input['origin_label'] ?? ''));
        $destination = trim((string) ($input['destination_label'] ?? ''));
        $direction = trim((string) ($input['direction'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $source = trim((string) ($input['source_name'] ?? ''));
        $sourceType = trim((string) ($input['source_type'] ?? 'demo'));
        $confidence = trim((string) ($input['confidence'] ?? 'demo'));
        $limits = trim((string) ($input['limitation_notes'] ?? ''));
        if (!preg_match('/^[A-Z0-9-]{2,32}$/', $code)) {
            $errors['code'] = 'Use 2–32 letters, numbers, or hyphens for the route code.';
        }
        if (str_len($name) < 3) {
            $errors['name'] = 'Enter a route name.';
        }
        if ($origin === '' || $destination === '' || $direction === '') {
            $errors['direction'] = 'Enter origin area, destination area, and direction.';
        }
        if (str_len($source) < 3) {
            $errors['source_name'] = 'Record the data source.';
        }
        if (str_len($limits) < 10) {
            $errors['limitation_notes'] = 'Describe what this route record does not claim, in at least 10 characters.';
        }
        if (!in_array($sourceType, ['demo', 'community', 'reviewed', 'official'], true)) {
            $errors['source_type'] = 'Choose a source type.';
        }
        $pairs = [];
        $sequences = $input['sequence'] ?? [];
        if (!is_array($sequences)) {
            $errors['sequence'] = 'Choose at least two stops and give each a sequence number.';
            $sequences = [];
        }
        $seenSeq = [];
        foreach ($sequences as $stopId => $sequence) {
            if ($sequence === '' || $sequence === null) {
                continue;
            }
            $stopId = (int) $stopId;
            $sequence = (int) $sequence;
            if ($sequence < 1) {
                $errors['sequence'] = 'Sequence numbers start at 1.';
                continue;
            }
            if (isset($seenSeq[$sequence])) {
                $errors['sequence'] = 'Each sequence number can be used once.';
            }
            $seenSeq[$sequence] = $stopId;
            $pairs[] = [$stopId, $sequence];
        }
        if (count($pairs) < 2) {
            $errors['sequence'] = 'A route needs at least two stops in order.';
        }
        usort($pairs, static fn (array $a, array $b): int => $a[1] <=> $b[1]);
        $stopIds = array_map(static fn (array $pair): int => $pair[0], $pairs);
        if (count($stopIds) !== count(array_unique($stopIds))) {
            $errors['sequence'] = 'A stop can appear only once on a route.';
        }
        if ($stopIds) {
            $placeholders = implode(',', array_fill(0, count($stopIds), '?'));
            $found = q_all("SELECT id, name, latitude, longitude, active FROM stops WHERE id IN ($placeholders)", $stopIds);
            if (count($found) !== count($stopIds)) {
                $errors['sequence'] = 'One of the selected stops does not exist.';
            }
            foreach ($found as $stop) {
                if (!(int) $stop['active']) {
                    $errors['sequence'] = 'Inactive stops cannot be added to a route.';
                }
            }
        }
        $duplicate = q_one('SELECT id FROM routes WHERE code = ? AND direction = ? AND id <> ?', [$code, $direction, $id ?? 0]);
        if ($duplicate) {
            $errors['code'] = 'That code and direction already exist.';
        }
        if ($errors) {
            fail_validation($errors, $input);
        }

        $byId = [];
        foreach ($found as $stop) {
            $byId[(int) $stop['id']] = $stop;
        }
        $ordered = [];
        foreach ($stopIds as $stopId) {
            $ordered[] = $byId[$stopId];
        }
        $now = utc_now();
        $demo = !empty($input['is_demo']) || $sourceType === 'demo' ? 1 : 0;
        $status = ($input['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $routeId = db_tx(function () use ($id, $code, $name, $origin, $destination, $direction, $description, $status, $source, $sourceType, $confidence, $limits, $demo, $now, $ordered, $actorId): int {
            if ($id) {
                q_exec(
                    'UPDATE routes SET code=?, name=?, origin_label=?, destination_label=?, direction=?, description=?, status=?, source_name=?, source_type=?, confidence=?, limitation_notes=?, is_demo=?, updated_at=? WHERE id=?',
                    [$code, $name, $origin, $destination, $direction, $description, $status, $source, $sourceType, $confidence, $limits, $demo, $now, $id]
                );
                $routeId = $id;
                q_exec('DELETE FROM route_stops WHERE route_id = ?', [$routeId]);
            } else {
                $routeId = q_insert(
                    'INSERT INTO routes (code, name, origin_label, destination_label, direction, description, status, source_name, source_type, confidence, limitation_notes, is_demo, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$code, $name, $origin, $destination, $direction, $description, $status, $source, $sourceType, $confidence, $limits, $demo, $now, $now]
                );
            }
            self::writeStopsAndGeometry($routeId, $direction, $ordered, $source, $now);
            audit($actorId, $id ? 'route.update' : 'route.create', 'route', $routeId, ['code' => $code]);
            return $routeId;
        });
        DisruptionEngine::recalculateAll();
        return $routeId;
    }

    public static function writeStopsAndGeometry(int $routeId, string $direction, array $orderedStops, string $source, string $now): void
    {
        $previous = null;
        $coords = [];
        $sequence = 1;
        foreach ($orderedStops as $stop) {
            $spacing = null;
            if ($previous) {
                $spacing = (int) round(Geo::meters((float) $previous['latitude'], (float) $previous['longitude'], (float) $stop['latitude'], (float) $stop['longitude']));
            }
            q_insert(
                'INSERT INTO route_stops (route_id, stop_id, sequence_no, direction, distance_from_prev_m) VALUES (?, ?, ?, ?, ?)',
                [$routeId, (int) $stop['id'], $sequence, $direction, $spacing]
            );
            $coords[] = [round((float) $stop['longitude'], 6), round((float) $stop['latitude'], 6)];
            $previous = $stop;
            $sequence++;
        }
        $json = json_encode(['type' => 'LineString', 'coordinates' => $coords]);
        $existing = q_one('SELECT id FROM route_geometries WHERE route_id = ?', [$routeId]);
        if ($existing) {
            q_exec(
                'UPDATE route_geometries SET geometry_json = ?, source_name = ?, verified_at = ? WHERE route_id = ?',
                [$json, $source, $now, $routeId]
            );
        } else {
            q_insert(
                'INSERT INTO route_geometries (route_id, format, geometry_json, source_name, verified_at) VALUES (?, "geojson", ?, ?, ?)',
                [$routeId, $json, $source, $now]
            );
        }
    }

    public static function setRouteActive(int $id, bool $active, int $actorId): void
    {
        q_exec('UPDATE routes SET status = ?, updated_at = ? WHERE id = ?', [$active ? 'active' : 'inactive', utc_now(), $id]);
        audit($actorId, $active ? 'route.activate' : 'route.deactivate', 'route', $id, []);
        DisruptionEngine::recalculateAll();
    }

    public static function markRouteChecked(int $id, int $actorId): void
    {
        $now = utc_now();
        q_exec('UPDATE routes SET verified_at = ?, updated_at = ? WHERE id = ?', [$now, $now, $id]);
        q_exec('UPDATE route_geometries SET verified_at = ? WHERE route_id = ?', [$now, $id]);
        audit($actorId, 'route.check', 'route', $id, []);
    }

    public static function setStopActive(int $id, bool $active, int $actorId): void
    {
        q_exec('UPDATE stops SET active = ?, updated_at = ? WHERE id = ?', [$active ? 1 : 0, utc_now(), $id]);
        audit($actorId, $active ? 'stop.activate' : 'stop.deactivate', 'stop', $id, []);
        DisruptionEngine::recalculateAll();
    }

    public static function markStopChecked(int $id, int $actorId): void
    {
        $now = utc_now();
        q_exec('UPDATE stops SET verified_at = ?, updated_at = ? WHERE id = ?', [$now, $now, $id]);
        audit($actorId, 'stop.check', 'stop', $id, []);
    }
}
