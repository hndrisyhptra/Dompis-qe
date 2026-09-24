<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QeBoq extends Model
{
    use SoftDeletes;

    protected $table = 'qe_boqs';

    protected $primaryKey = 'id_boq';

    protected $fillable = [
        'qe_lop_id', 'package_id', 'source', 'status', 'item_count',
        'grand_total', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['grand_total' => 'decimal:2'];
    }

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
        return $this->hasMany(QeBoqItem::class, 'qe_boq_id', 'id_boq')->orderBy('designator_code');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(QeBoqHistory::class, 'qe_boq_id', 'id_boq')->latest();
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
