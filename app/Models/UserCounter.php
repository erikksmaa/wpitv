<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCounter extends Model
{
    use HasFactory;

    protected $table = 'tr_usercounter';

    public $timestamps = false;

    protected $fillable = [
        'ip',
        'tanggal',
        'hits',
        'online',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'hits' => 'integer',
            'online' => 'integer',
        ];
    }
}
