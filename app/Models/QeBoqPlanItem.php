<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QeBoqPlanItem extends Model
{
    protected $table = 'qe_boq_plan_items';

    protected $primaryKey = 'id_plan_item';

    protected $fillable = [
        'qe_boq_plan_id', 'designator_id', 'designator_code',
        'item_name', 'unit', 'type',
        'qty', 'unit_price', 'total_price',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(QeBoqPlan::class, 'qe_boq_plan_id', 'id_plan');
    }

    public function designator(): BelongsTo
    {
        return $this->belongsTo(Designator::class, 'designator_id', 'id_designator')->withTrashed();
    }
}
