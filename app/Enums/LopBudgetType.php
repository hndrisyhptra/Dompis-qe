<?php

namespace App\Enums;

enum LopBudgetType: string
{
    case CAPEX = 'CAPEX';
    case OPEX = 'OPEX';

    public function label(): string
    {
        return $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::CAPEX => 'RAB di atas Rp 25 juta',
            self::OPEX => 'RAB di bawah Rp 25 juta',
        };
    }
}
