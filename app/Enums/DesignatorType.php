<?php

namespace App\Enums;

enum DesignatorType: string
{
    case MATERIAL = 'material';
    case JASA = 'jasa';

    public function label(): string
    {
        return match ($this) {
            self::MATERIAL => 'Material',
            self::JASA => 'Jasa',
        };
    }
}
