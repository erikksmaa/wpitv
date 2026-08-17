<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TVClubGroupMember extends Pivot
{
    use HasFactory;

    protected $table = 'tvclub_group_members';

    public $incrementing = true;

    protected $fillable = [
        'group_id',
        'member_id',
        'role',
    ];
}
