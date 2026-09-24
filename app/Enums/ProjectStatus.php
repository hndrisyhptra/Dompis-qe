<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case USULAN = 'usulan';
    case ON_GOING = 'on_going';

    public function label(): string
    {
        return match ($this) {
            self::USULAN => 'Usulan',
            self::ON_GOING => 'On Going',
        };
    }
}
