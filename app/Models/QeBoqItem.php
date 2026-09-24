<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QeBoqItem extends Model
{
    use SoftDeletes;

    protected $table = 'qe_boq_items';

    protected $primaryKey = 'id_boq_item';

    protected $fillable = [
        'qe_boq_id', 'designator_id', 'designator_code', 'item_name',
        'unit', 'type', 'qty', 'unit_price', 'total_price',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(QeBoq::class, 'qe_boq_id', 'id_boq');
    }

    public function designator(): BelongsTo
    {
        return $this->belongsTo(Designator::class, 'designator_id', 'id_designator')->withTrashed();
    }
}
