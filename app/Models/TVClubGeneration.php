<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TVClubGeneration extends Model
{
    use HasFactory;

    protected $table = 'tvclub_generations';

    protected $fillable = [
        'name',
        'year',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'year' => 'integer',
        ];
    }

    public function members()
    {
        return $this->hasMany(TVClubMember::class, 'generation_id');
    }

    public function groups()
    {
        return $this->hasMany(TVClubGroup::class, 'generation_id');
    }
}
