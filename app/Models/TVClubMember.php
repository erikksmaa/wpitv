<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TVClubMember extends Model
{
    use HasFactory;

    protected $table = 'tvclub_members';

    protected $fillable = [
        'generation_id',
        'student_number',
        'name',
        'photo',
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

    public function groups()
    {
        return $this->belongsToMany(TVClubGroup::class, 'tvclub_group_members', 'member_id', 'group_id')
                    ->withPivot('id', 'role')
                    ->withTimestamps();
    }
}
