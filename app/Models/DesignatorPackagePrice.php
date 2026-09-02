<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "KHS" - harga satuan sebuah Designator di dalam satu Package (Paket KHS).
 */
class DesignatorPackagePrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'id_designator_package_price';

    protected $fillable = [
        'designator_id',
        'package_id',
        'price',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function designator(): BelongsTo
    {
        return $this->belongsTo(Designator::class, 'designator_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
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
