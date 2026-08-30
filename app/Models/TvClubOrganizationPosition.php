<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TvClubOrganizationPosition extends Model
{
    use HasFactory;

    protected $table = 'tvclub_organization_positions';

    protected $fillable = [
        'generation_id',
        'parent_id',
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

    public function generation()
    {
        return $this->belongsTo(TVClubGeneration::class, 'generation_id');
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

    public function assignments()
    {
        return $this->hasMany(TvClubOrganizationMember::class, 'position_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function members()
    {
        return $this->belongsToMany(TVClubMember::class, 'tvclub_organization_members', 'position_id', 'member_id')
            ->using(TvClubOrganizationMember::class)
            ->withPivot('id', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('tvclub_members.id');
    }
}
