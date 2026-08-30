<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TvClubOrganizationMember extends Pivot
{
    use HasFactory;

    protected $table = 'tvclub_organization_members';

    public $incrementing = true;

    protected $fillable = [
        'position_id',
        'member_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function position()
    {
        return $this->belongsTo(TvClubOrganizationPosition::class, 'position_id');
    }

    public function member()
    {
        return $this->belongsTo(TVClubMember::class, 'member_id');
    }
}
