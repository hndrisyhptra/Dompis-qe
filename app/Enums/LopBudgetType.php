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
}
