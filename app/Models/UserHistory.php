<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHistory extends Model
{
    protected $table = 'user_histories';

    protected $primaryKey = 'id_user_history';

    protected $fillable = [
        'target_user_id',
        'actor_id',
        'event_type',
        'changes',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
