<?php

namespace App\Models;

use App\Enums\DesignatorType;
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
        'type',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DesignatorType::class,
        ];
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
