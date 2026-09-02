<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designator extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'id_designator';

    protected $fillable = [
        'code',
        'item_name',
        'unit',
        'designator_type_id',
        'designator_category_id',
        'created_by',
        'updated_by',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DesignatorType::class, 'designator_type_id', 'id_designator_type');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DesignatorCategory::class, 'designator_category_id', 'id_designator_category');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(DesignatorPackagePrice::class, 'designator_id');
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(QeMaterialReservationItem::class, 'designator_id', 'id_designator');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
