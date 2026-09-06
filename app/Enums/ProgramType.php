<?php

namespace App\Enums;

enum ProgramType: string
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

    public function code(): string
    {
        return match ($this) {
            self::RECOVERY => 'QEREC',
            self::PREVENTIVE => 'QEPREV',
            self::RELOK_UTILITAS => 'QEREL',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::RECOVERY => 1,
            self::PREVENTIVE => 2,
            self::RELOK_UTILITAS => 3,
        };
    }
}
