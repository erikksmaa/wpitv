<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $table = 'ms_program';

    protected $fillable = [
        'name',
        'slug',
        'image',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'program_id');
    }

    public function tvClubGroups()
    {
        return $this->hasMany(TVClubGroup::class, 'program_id');
    }
}
