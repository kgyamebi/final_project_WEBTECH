<?php
declare(strict_types=1);

namespace App\Services;

final class Geo
{
    public static function meters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }

    public static function pointToSegmentMeters(float $lat, float $lng, float $latA, float $lngA, float $latB, float $lngB): float
    {
        $lat0 = deg2rad($lat);
        $kx = 111320.0 * cos($lat0);
        $ky = 110540.0;
        $px = $lng * $kx;
        $py = $lat * $ky;
        $ax = $lngA * $kx;
        $ay = $latA * $ky;
        $bx = $lngB * $kx;
        $by = $latB * $ky;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len2 = $dx * $dx + $dy * $dy;
        if ($len2 < 0.01) {
            return hypot($px - $ax, $py - $ay);
        }
        $t = max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / $len2));
        return hypot($px - ($ax + $t * $dx), $py - ($ay + $t * $dy));
    }

    /** @param list<array{0:float,1:float}> $line lat,lng pairs */
    public static function pointToLineMeters(float $lat, float $lng, array $line): ?float
    {
        if (count($line) === 0) {
            return null;
        }
        if (count($line) === 1) {
            return self::meters($lat, $lng, $line[0][0], $line[0][1]);
        }
        $best = null;
        for ($i = 1, $n = count($line); $i < $n; $i++) {
            $d = self::pointToSegmentMeters($lat, $lng, $line[$i - 1][0], $line[$i - 1][1], $line[$i][0], $line[$i][1]);
            if ($best === null || $d < $best) {
                $best = $d;
            }
        }
        return $best;
    }
}
