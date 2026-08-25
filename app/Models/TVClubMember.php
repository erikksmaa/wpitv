<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Get the full URL for the member's photo with fallback.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->photo)) {
                    return null;
                }

                if (Storage::disk('public')->exists('tvclub/members/'.$this->photo)) {
                    return asset('storage/tvclub/members/'.$this->photo);
                }

                return null;
            }
        );
    }

    /**
     * Get the initials of the member's name for avatar fallback.
     */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: function () {
                $words = explode(' ', trim($this->name));
                if (count($words) >= 2) {
                    return strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
                }

                return strtoupper(mb_substr($this->name, 0, 2));
            }
        );
    }
}
