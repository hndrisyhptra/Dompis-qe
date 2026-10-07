<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QeBoqPlan extends Model
{
    use SoftDeletes;

    protected $table = 'qe_boq_plans';

    protected $primaryKey = 'id_plan';

    protected $fillable = [
        'qe_lop_id', 'package_id', 'note',
        'created_by', 'updated_by',
    ];

    public function lop(): BelongsTo
    {
        return $this->belongsTo(QeLop::class, 'qe_lop_id', 'id_qe_lops');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id', 'id_package')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(QeBoqPlanItem::class, 'qe_boq_plan_id', 'id_plan')
            ->orderBy('designator_code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id_user');
    }
}
