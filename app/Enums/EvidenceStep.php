<?php

namespace App\Enums;

enum EvidenceStep: string
{
    case SURVEY = 'SURVEY';
    case BEFORE = 'BEFORE';
    case PROGRESS = 'PROGRESS';
    case AFTER = 'AFTER';
    case DISMANTLE = 'DISMANTLE';

    public function label(): string
    {
        return match ($this) {
            self::SURVEY => 'Survey',
            self::BEFORE => 'Before',
            self::PROGRESS => 'Progress',
            self::AFTER => 'After',
            self::DISMANTLE => 'Dismantle',
        };
    }

    /**
     * Step yang evidence-nya terikat ke item designator tertentu.
     * SURVEY & DISMANTLE bersifat umum per-LOP, bukan per-item.
     */
    public function requiresDesignator(): bool
    {
        return in_array($this, [self::BEFORE, self::PROGRESS, self::AFTER], true);
    }
}
