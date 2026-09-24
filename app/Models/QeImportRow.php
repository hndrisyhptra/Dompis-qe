<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QeImportRow extends Model
{
    use SoftDeletes;

    protected $table = 'qe_import_rows';

    protected $primaryKey = 'id_import_row';

    protected $fillable = [
        'import_batch_id', 'row_number', 'status', 'reference',
        'message', 'payload', 'result',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QeImportBatch::class, 'import_batch_id', 'id_import_batch');
    }
}
