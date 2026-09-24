<?php

namespace App\Enums;

enum AdminScopeType: string
{
    case AREA = 'area';
    case REGION = 'region';
    case BRANCH = 'branch';
    case SERVICE_AREA = 'service_area';

    public function label(): string
    {
        return match ($this) {
            self::AREA => 'Admin Area',
            self::REGION => 'Admin Region',
            self::BRANCH => 'Admin Branch',
            self::SERVICE_AREA => 'Admin Service Area',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AREA => 'Melihat seluruh LOP pada semua region, branch, dan service area di Area terpilih.',
            self::REGION => 'Melihat seluruh LOP pada semua branch dan service area di Region terpilih.',
            self::BRANCH => 'Melihat seluruh LOP pada semua service area di Branch terpilih.',
            self::SERVICE_AREA => 'Melihat LOP dari satu atau beberapa Service Area yang dipilih.',
        };
    }
}
