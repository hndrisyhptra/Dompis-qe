<?php

namespace App\Enums;

enum ExcludeReason: string
{
    case NON_MATERIAL = 'non_material';
    case TEMPORER = 'temporer';
    case DUPLIKAT_OPEN = 'duplikat_open';
    case MAGU_GANTI_PASIF_ODP = 'magu_ganti_pasif_odp';

    public function label(): string
    {
        return match ($this) {
            self::NON_MATERIAL => 'Non Material',
            self::TEMPORER => 'Temporer',
            self::DUPLIKAT_OPEN => 'Duplikat Tiket Open',
            self::MAGU_GANTI_PASIF_ODP => 'MAGU Ganti Pasif ODP',
        };
    }
}
