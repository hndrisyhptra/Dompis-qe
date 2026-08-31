<?php

namespace App\Enums;

enum EvidenceCategory: string
{
    case PRE = 'pre';
    case MATERIAL_ARRIVAL = 'material_arrival';
    case BEFORE = 'before';
    case PROGRESS = 'progress';
    case AFTER = 'after';

    public function label(): string
    {
        return match ($this) {
            self::PRE => 'Evidence Pra',
            self::MATERIAL_ARRIVAL => 'Material Tiba',
            self::BEFORE => 'Before',
            self::PROGRESS => 'Progress',
            self::AFTER => 'After',
        };
    }
}
