<?php

namespace App\Models;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceStep;
use App\Enums\EvidenceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class QeEvidence extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'qe_evidences';

    protected $primaryKey = 'id_evidence';

    protected $fillable = [
        'qe_lop_id',
        'designator_id',
        'uploaded_by',
        'step',
        'type',
        'category',
        'file_path',
        'thumb_path',
        'metadata',
        'note',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'step' => EvidenceStep::class,
            'type' => EvidenceType::class,
            'category' => EvidenceCategory::class,
            'status' => EvidenceStatus::class,
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * URL file evidence asli (full-res) di disk yang dikonfigurasi.
     */
    public function url(): string
    {
        return Storage::disk(config('evidence.disk'))->url($this->file_path);
    }

    /**
     * URL thumbnail; jatuh balik ke file asli bila thumbnail belum ada.
     */
    public function thumbUrl(): string
    {
        return $this->thumb_path
            ? Storage::disk(config('evidence.disk'))->url($this->thumb_path)
            : $this->url();
    }

    public function lop(): BelongsTo
    {
        return $this->belongsTo(QeLop::class, 'qe_lop_id', 'id_qe_lops');
    }

    public function designator(): BelongsTo
    {
        return $this->belongsTo(Designator::class, 'designator_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
