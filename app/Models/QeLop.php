<?php

namespace App\Models;

use App\Enums\LopStatus;
use App\Enums\WbsType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class QeLop extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'qe_lops';

    protected $primaryKey = 'id_qe_lops';

    /**
     * technician_id SENGAJA tidak ada di daftar ini (dan tidak ada kolomnya
     * sama sekali di tabel qe_lops) - penugasan teknisi wajib lewat
     * QeLopAssignment, sesuai aturan CLAUDE.md.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kode_lop',
        'nama_lop',
        'wbs_type',
        'sto',
        'branch',
        'package_id',
        'status_lop',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'wbs_type' => WbsType::class,
            'status_lop' => LopStatus::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(QeLopAssignment::class, 'qe_lop_id', 'id_qe_lops');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(QeLopAssignment::class, 'qe_lop_id', 'id_qe_lops')
            ->where('status', 'active');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(QeLopHistory::class, 'qe_lop_id', 'id_qe_lops')
            ->orderBy('created_at');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(QeEvidence::class, 'qe_lop_id', 'id_qe_lops')
            ->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function currentTechnician(): ?User
    {
        return $this->activeAssignment?->technician;
    }
}
