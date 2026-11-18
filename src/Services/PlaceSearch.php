<?php
declare(strict_types=1);

namespace App\Services;

final class PlaceSearch
{
    public static function resolve(string $query): array
    {
        $query = trim((string) preg_replace('/\s+/', ' ', $query));
        if ($query === '') {
            return ['status' => 'empty', 'query' => ''];
        }
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $query, $matches)) {
            $lat = (float) $matches[1];
            $lng = (float) $matches[2];
            if (!in_accra($lat, $lng)) {
                return ['status' => 'outside', 'query' => $query];
            }
            return [
                'status' => 'ok',
                'place' => [
                    'label' => 'Pinned location',
                    'lat' => $lat,
                    'lng' => $lng,
                    'kind' => 'coordinates',
                ],
            ];
        }
        if (strlen($query) < 2) {
            return ['status' => 'none', 'query' => $query, 'options' => []];
        }

        $exact = q_all(
            'SELECT id, name, locality, latitude, longitude FROM stops
             WHERE active = 1 AND (LOWER(name) = LOWER(?) OR LOWER(locality) = LOWER(?))
             ORDER BY name',
            [$query, $query]
        );
        if (count($exact) === 1) {
            return ['status' => 'ok', 'place' => self::fromStop($exact[0])];
        }
        if (count($exact) > 1) {
            return ['status' => 'ambiguous', 'query' => $query, 'options' => array_map([self::class, 'fromStop'], $exact)];
        }

        $like = '%' . self::escapeLike($query) . '%';
        $stops = q_all(
            'SELECT id, name, locality, latitude, longitude FROM stops
             WHERE active = 1 AND (name LIKE ? ESCAPE \'\\\\\' OR locality LIKE ? ESCAPE \'\\\\\')
             ORDER BY name LIMIT 8',
            [$like, $like]
        );
        $places = q_all(
            'SELECT id, name, latitude, longitude, kind FROM places
             WHERE name LIKE ? ESCAPE \'\\\\\' ORDER BY name LIMIT 8',
            [$like]
        );
        $options = [];
        foreach ($stops as $stop) {
            $options[] = self::fromStop($stop);
        }
        foreach ($places as $place) {
            $options[] = [
                'label' => $place['name'],
                'lat' => (float) $place['latitude'],
                'lng' => (float) $place['longitude'],
                'kind' => 'place',
                'id' => (int) $place['id'],
            ];
        }
        $options = self::dedupe($options);
        if (count($options) === 1) {
            return ['status' => 'ok', 'place' => $options[0]];
        }
        if (count($options) > 1) {
            return ['status' => 'ambiguous', 'query' => $query, 'options' => $options];
        }
        return ['status' => 'none', 'query' => $query, 'options' => []];
    }

    public static function suggest(string $query): array
    {
        $resolved = self::resolve($query);
        if (($resolved['status'] ?? '') === 'ok') {
            return [$resolved['place']];
        }
        return $resolved['options'] ?? [];
    }

    private static function fromStop(array $stop): array
    {
        return [
            'label' => $stop['name'] . ' · ' . $stop['locality'],
            'name' => $stop['name'],
            'lat' => (float) $stop['latitude'],
            'lng' => (float) $stop['longitude'],
            'kind' => 'stop',
            'id' => (int) $stop['id'],
        ];
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function dedupe(array $options): array
    {
        $seen = [];
        $out = [];
        foreach ($options as $option) {
            $key = round($option['lat'], 4) . ',' . round($option['lng'], 4);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $option;
        }
        return $out;
    }
}
