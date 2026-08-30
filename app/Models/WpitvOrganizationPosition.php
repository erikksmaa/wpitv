<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WpitvOrganizationPosition extends Model
{
    use HasFactory;

    protected $table = 'wpitv_organization_positions';

    protected $fillable = [
        'parent_id',
        'user_id',
        'name',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
