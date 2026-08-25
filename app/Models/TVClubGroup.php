<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TVClubGroup extends Model
{
    use HasFactory;

    protected $table = 'tvclub_groups';

    protected $fillable = [
        'generation_id',
        'program_id',
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function generation()
    {
        return $this->belongsTo(TVClubGeneration::class, 'generation_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function members()
    {
        return $this->belongsToMany(TVClubMember::class, 'tvclub_group_members', 'group_id', 'member_id')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }
}
