<?php

namespace App\Enums;

enum WbsType: string
{
    case RECOVERY = 'recovery';
    case PREVENTIVE = 'preventive';
    case RELOK_UTILITAS = 'relok_utilitas';

    public function label(): string
    {
        return match ($this) {
            self::RECOVERY => 'QE Recovery',
            self::PREVENTIVE => 'QE Preventive',
            self::RELOK_UTILITAS => 'QE Relok Utilitas',
        };
    }
}
