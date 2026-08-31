<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function isSuperAdmin(): bool
    {
        return in_array($this->role ?? 'superadmin', ['superadmin', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    public function isOperator(): bool
    {
        return ($this->role ?? '') === 'operator';
    }

    public static function superAdminCount(): int
    {
        return static::query()->whereIn('role', ['superadmin', 'admin'])->count();
    }
}
