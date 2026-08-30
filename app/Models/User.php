<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'role_id',
        'username',
        'password',
        'name',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function news()
    {
        return $this->hasMany(News::class, 'user_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'user_id');
    }

    public function organizationPositions()
    {
        return $this->hasMany(WpitvOrganizationPosition::class, 'user_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role && $this->role->name === 'Super Admin';
    }

    public function isAdminProduction(): bool
    {
        return $this->role && $this->role->name === 'Admin Production';
    }

    public function isAdminNews(): bool
    {
        return $this->role && $this->role->name === 'Admin News';
    }

    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim($this->name ?: $this->username));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($words[0] ?? 'U', 0, 2));
    }
}
