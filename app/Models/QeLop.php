<?php

namespace App\Models;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\LopStatus;
use App\Enums\ProgramType;
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
        'incident',
        'nama_lop',
        'program_type',
        'sto',
        'branch',
        'area',
        'segment',
        'budget_type',
        'job_description',
        'ticket_summary',
        'datek',
        'ihld_id',
        'package_id',
        'status_lop',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'program_type' => ProgramType::class,
            'segment' => LopSegment::class,
            'budget_type' => LopBudgetType::class,
            'status_lop' => LopStatus::class,
            'datek' => 'array',
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

    public function materialReservation(): HasOne
    {
        return $this->hasOne(QeMaterialReservation::class, 'qe_lop_id', 'id_qe_lops');
    }

    public function survey(): HasOne
    {
        return $this->hasOne(QeSurvey::class, 'qe_lop_id', 'id_qe_lops');
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
