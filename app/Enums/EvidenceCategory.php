<?php

namespace App\Enums;

enum EvidenceCategory: string
{
    case PRE = 'pre';
    case INSERA = 'insera';
    case MATERIAL_ARRIVAL = 'material_arrival';
    case BEFORE = 'before';
    case PROGRESS = 'progress';
    case AFTER = 'after';
    case SLOT_PORT = 'slot_port';

    public function label(): string
    {
        return match ($this) {
            self::PRE => 'Evidence Pra',
            self::INSERA => 'Capture Ticket Insera',
            self::MATERIAL_ARRIVAL => 'Material Tiba',
            self::BEFORE => 'Before',
            self::PROGRESS => 'Progress',
            self::AFTER => 'After',
            self::SLOT_PORT => 'Slot Port',
        };
    }
}
