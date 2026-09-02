<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'id_branch';

    protected $fillable = [
        'code',
        'name',
        'region',
        'region_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    /**
     * Relasi ke master Region. Dinamai regionRef() (bukan region()) supaya
     * tidak bentrok dengan kolom string denormalisasi `branches.region`
     * yang masih dibaca banyak tempat (dashboard, evidence-approval).
     */
    public function regionRef(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id_region');
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
