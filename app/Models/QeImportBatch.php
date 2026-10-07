<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QeImportBatch extends Model
{
    use SoftDeletes;

    protected $table = 'qe_import_batches';

    protected $primaryKey = 'id_import_batch';

    protected $fillable = [
        'uuid', 'type', 'status', 'disk', 'file_path', 'original_name',
        'total_rows', 'success_rows', 'failed_rows', 'metadata',
        'error_message', 'uploaded_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(QeImportRow::class, 'import_batch_id', 'id_import_batch')->orderBy('row_number');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id_user');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'partial', 'failed'], true);
    }

    public function processedRows(): int
    {
        if ($this->isFinished()) {
            return (int) $this->total_rows;
        }

        return min(
            (int) $this->total_rows,
            max(
                (int) data_get($this->metadata, 'processed_rows', 0),
                (int) $this->success_rows + (int) $this->failed_rows,
            ),
        );
    }

    public function progressPercentage(): int
    {
        if ($this->isFinished()) {
            return 100;
        }

        if ((int) $this->total_rows === 0) {
            return 0;
        }

        return min(99, (int) round(($this->processedRows() / (int) $this->total_rows) * 100));
    }
}
