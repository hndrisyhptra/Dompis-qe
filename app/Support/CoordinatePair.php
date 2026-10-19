<?php

namespace App\Support;

class CoordinatePair
{
    public static function parse(string $value): ?array
    {
        $text = trim($value);
        if (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $text = trim(substr($text, 1, -1));
        }
        $parts = preg_split('/\s*[,;]\s*|\s+/', $text);
        if (count($parts) !== 2 || ! collect($parts)->every(fn ($v) => preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/D', $v))) {
            return null;
        }
        [$lat, $lng] = array_map('floatval', $parts);
        if (abs($lat) > 90 && abs($lng) <= 90) {
            [$lat, $lng] = [$lng, $lat];
        }
        if (! is_finite($lat) || ! is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        return ['latitude' => round($lat, 7), 'longitude' => round($lng, 7)];
    }
}
