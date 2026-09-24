<?php

namespace App\Models;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\ProjectStatus;
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
        'status_project',
        'sto',
        'branch',
        'branch_id',
        'service_area_id',
        'area',
        'segment',
        'budget_type',
        'job_description',
        'ticket_summary',
        'datek',
        'ihld_id',
        'package_id',
        'boq_snapshot',
        'status_lop',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $lop) {
            $program = $lop->program_type instanceof ProgramType
                ? $lop->program_type
                : ProgramType::tryFrom((string) $lop->program_type);
            if ($program === ProgramType::RECOVERY) {
                $lop->status_project = null;
            } elseif ($program?->usesProjectStatus() && blank($lop->status_project)) {
                $lop->status_project = ProjectStatus::USULAN;
            }

            $raw = $lop->attributes['segment'] ?? null;

            // Normalisasi string tunggal "odp" -> '["odp"]' agar konsisten dengan cast array + JSON kolom.
            // Pakai set raw JSON string (bypass cast double-encoding) karena saving() berjalan setelah mutasi awal.
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (! is_array($decoded)) {
                    $val = strtolower(trim($raw));
                    if (str_contains($val, ',')) {
                        $parts = array_values(array_filter(array_map(fn ($v) => strtolower(trim((string) $v)), explode(',', $val))));
                        $lop->attributes['segment'] = json_encode($parts);
                    } else {
                        $lop->attributes['segment'] = $val !== '' ? json_encode([$val]) : json_encode([]);
                    }
                    // Tandai agar cast tidak meng-encode ulang (sudah JSON string)
                    // Dengan menulis langsung ke attributes, cast akan menganggap sudah final string.
                }
            }

            if ($raw === '') {
                $lop->attributes['segment'] = json_encode([]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'program_type' => ProgramType::class,
            'status_project' => ProjectStatus::class,
            'segment' => 'array',
            'budget_type' => LopBudgetType::class,
            'status_lop' => LopStatus::class,
            'datek' => 'array',
            'boq_snapshot' => 'array',
        ];
    }

    /**
     * Normalisasi segment agar selalu array string lowercase.
     * Support data lama: string "odp", CSV "odp,tiang", atau JSON array.
     *
     * @return list<string>
     */
    public function segments(): array
    {
        $raw = $this->attributes['segment'] ?? null;

        if ($raw === null || $raw === '') {
            return [];
        }

        // Jika sudah array (dari cast), pakai langsung
        if (is_array($raw)) {
            return array_values(array_filter(array_map(fn ($v) => strtolower(trim((string) $v)), $raw)));
        }

        // Jika string JSON array
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(fn ($v) => strtolower(trim((string) $v)), $decoded)));
        }

        // CSV atau single string
        if (str_contains((string) $raw, ',')) {
            return array_values(array_filter(array_map(fn ($v) => strtolower(trim((string) $v)), explode(',', (string) $raw))));
        }

        return [strtolower(trim((string) $raw))];
    }

    /**
     * Segment pertama (compat helper untuk program non-relok atau display singkat).
     */
    public function primarySegment(): ?LopSegment
    {
        $list = $this->segments();

        $first = $list[0] ?? null;

        return $first ? LopSegment::tryFrom($first) : null;
    }

    /**
     * Label gabungan untuk display, mis. "ODP, Tiang".
     */
    public function segmentLabel(): string
    {
        $list = $this->segments();
        if ($list === []) {
            return '—';
        }

        return implode(', ', array_map(fn ($v) => LopSegment::tryFrom($v)?->label() ?? strtoupper($v), $list));
    }

    /**
     * Cek apakah LOP ini multi-segmen.
     */
    public function isMultiSegment(): bool
    {
        return count($this->segments()) > 1;
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

    public function branchRef(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id_branch');
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class, 'service_area_id', 'id_service_area');
    }

    public function locationBranchName(): string
    {
        return $this->branchRef?->name ?? $this->branch ?? '—';
    }

    public function locationServiceAreaName(): string
    {
        return $this->serviceArea?->workzone ?? $this->sto ?? '—';
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

    public function boq(): HasOne
    {
        return $this->hasOne(QeBoq::class, 'qe_lop_id', 'id_qe_lops');
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

    /**
     * Nomor tiket dibuat manual (prefix INP dari ManualIncidentService) vs.
     * nomor tiket asli hasil lookup DB tiket eksternal (prefix INC).
     * Hanya nomor manual yang boleh diubah lewat Edit LOP.
     */
    public function usesManualIncident(): bool
    {
        return str_starts_with((string) $this->incident, 'INP');
    }
}
