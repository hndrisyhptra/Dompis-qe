<?php

namespace App\Enums;

enum LopSegment: string
{
    public const MAX_SELECTIONS = 5;

    case FEEDER = 'feeder';
    case DISTRIBUSI = 'distribusi';
    case ODC = 'odc';
    case ODP = 'odp';
    case TIANG = 'tiang';
    case JC = 'jc';
    case OTB = 'otb';
    case GPON = 'gpon';
    case MINI_OLT = 'mini_olt';
    case REGEL = 'regel';

    /** Normalisasi segmen tanpa memotong pilihan agar batas tetap divalidasi. */
    public static function normalizeInput(mixed $input): array
    {
        $values = is_array($input) ? $input : (is_string($input) ? preg_split('/[|,;]+/', $input) : []);
        $aliases = [];
        foreach (self::cases() as $segment) {
            $aliases[mb_strtolower($segment->label())] = $segment->value;
        }

        $normalized = [];
        foreach ($values ?: [] as $value) {
            if (! is_string($value)) {
                $normalized[] = $value;

                continue;
            }
            $value = mb_strtolower(trim($value));
            if ($value !== '') {
                $normalized[] = $aliases[$value] ?? $value;
            }
        }

        return array_values(array_unique($normalized, SORT_REGULAR));
    }

    public function label(): string
    {
        return match ($this) {
            self::FEEDER => 'Feeder',
            self::DISTRIBUSI => 'Distribusi',
            self::ODC => 'ODC',
            self::ODP => 'ODP',
            self::TIANG => 'Tiang',
            self::JC => 'JC',
            self::OTB => 'OTB',
            self::GPON => 'GPON',
            self::MINI_OLT => 'Mini OLT',
            self::REGEL => 'Regel',
        };
    }
}
