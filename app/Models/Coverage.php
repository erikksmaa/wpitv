<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coverage extends Model
{
    use HasFactory;

    protected $table = 'tr_perliputan';

    protected $fillable = [
        'submission_code',
        'name',
        'email',
        'phone',
        'event_name',
        'event_date',
        'event_time',
        'location',
        'description',
        'status',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }
}
