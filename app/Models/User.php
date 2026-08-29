<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $primaryKey = 'id_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'nik',
        'name',
        'username',
        'password',
        'email',
        'phone',
        'branch_id',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function createdLops(): HasMany
    {
        return $this->hasMany(QeLop::class, 'created_by');
    }

    public function lopAssignments(): HasMany
    {
        return $this->hasMany(QeLopAssignment::class, 'technician_id');
    }

    public function activeLopAssignments(): HasMany
    {
        return $this->lopAssignments()->where('status', 'active');
    }

    /**
     * Audit trail perubahan data user ini (dibuat, ganti role, nonaktif,
     * dihapus, dst) - lihat UserService::recordHistory().
     */
    public function historyEntries(): HasMany
    {
        return $this->hasMany(UserHistory::class, 'target_user_id')
            ->orderBy('created_at');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Cek role user terhadap satu atau lebih UserRole enum. Dipakai Policy
     * & Middleware - satu-satunya cara resmi cek role (jangan bandingkan
     * string role mentah di controller/view).
     */
    public function hasRole(UserRole ...$roles): bool
    {
        if (! $this->role) {
            return false;
        }

        $roleCodes = array_map(fn (UserRole $r) => $r->value, $roles);

        return in_array($this->role->code, $roleCodes, true);
    }

    /**
     * Cek granular permission (kode) milik role user, mis. 'create_lop'.
     * Pelengkap hasRole()/Policy, bukan pengganti - modul LOP tetap pakai
     * QeLopPolicy untuk otorisasi kontekstual per-record.
     */
    public function hasPermission(string $code): bool
    {
        if (! $this->role) {
            return false;
        }

        return $this->role->permissions->contains('code', $code);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Route tujuan setelah login, sesuai role. Fallback ke lop.index kalau
     * user belum punya role (role_id null) - lihat UserRole::dashboardRouteName().
     */
    public function postLoginRouteName(): string
    {
        if (! $this->role) {
            return 'lop.index';
        }

        return UserRole::from($this->role->code)->dashboardRouteName();
    }
}
