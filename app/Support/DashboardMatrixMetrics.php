<?php

namespace App\Support;

use App\Enums\LopStatus;

class DashboardMatrixMetrics
{
    public static function fromPipeline(array $counts): array
    {
        $pipeline = collect(LopStatus::cases())->mapWithKeys(fn (LopStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])->all();
        $total = array_sum($pipeline);
        $complete = $pipeline[LopStatus::COMPLETED->value];

        return [
            'total' => $total,
            'assigned' => $pipeline[LopStatus::ASSIGNED->value],
            'unassigned' => $pipeline[LopStatus::DRAFT->value],
            'in_review' => $pipeline[LopStatus::WAITING_APPROVAL->value],
            'complete' => $complete,
            'percentage' => $total > 0 ? (int) round(($complete / $total) * 100) : 0,
            'pipeline' => $pipeline,
        ];
    }

    public static function merge(array $rows): array
    {
        $pipeline = collect(LopStatus::cases())->mapWithKeys(
            fn (LopStatus $status) => [$status->value => array_sum(array_column(array_column($rows, 'pipeline'), $status->value))]
        )->all();

        return self::fromPipeline($pipeline);
    }
}
