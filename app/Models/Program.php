<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Get the full URL for the program cover image with fallback.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->image)) {
                    return asset('images/placeholders/program-placeholder.svg');
                }

                if (Storage::disk('public')->exists('programs/'.$this->image)) {
                    return asset('storage/programs/'.$this->image);
                }

                return asset('images/placeholders/program-placeholder.svg');
            }
        );
    }

    /**
     * Scope query to only include active programs.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'program_id');
    }

    public function tvClubGroups()
    {
        return $this->hasMany(TVClubGroup::class, 'program_id');
    }

    public function agendas()
    {
        return $this->hasMany(WpitvAgenda::class, 'program_id');
    }

    public function liveStreams(): HasManyThrough
    {
        return $this->hasManyThrough(LiveStream::class, WpitvAgenda::class, 'program_id', 'agenda_id');
    }
}
