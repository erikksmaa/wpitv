<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    public const CORE_NAMES = [
        User::ROLE_SUPER_ADMIN,
        User::ROLE_PRODUCTION,
        User::ROLE_NEWS,
        User::ROLE_TVCLUB,
    ];

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'description',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function isCore(): bool
    {
        return in_array($this->name, self::CORE_NAMES, true);
    }
}
