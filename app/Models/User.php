<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('rt_code')) {
                $user->assigned_rt = $user->rt_code;
            }
        });
    }

    protected $fillable = ['name', 'email', 'password', 'role', 'rt_code', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isRtAdmin(): bool
    {
        return $this->role === 'admin_rt';
    }

    public function createdHouses(): HasMany
    {
        return $this->hasMany(House::class, 'created_by');
    }
}
