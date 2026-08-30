<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WpitvAgenda extends Model
{
    use HasFactory;

    protected $table = 'wpitv_agendas';

    protected $fillable = [
        'title', 'type', 'program_id', 'coverage_id', 'group_id', 'agenda_date',
        'start_time', 'end_time', 'location', 'description', 'status', 'visibility', 'created_by',
    ];

    public const TYPES = [
        'program_production' => 'Produksi Program',
        'coverage' => 'Peliputan',
        'live' => 'Live / Siaran Langsung',
        'meeting' => 'Rapat',
        'training' => 'Pelatihan',
        'campus_event' => 'Kegiatan Kampus',
        'internal' => 'Internal WPiTV',
        'other' => 'Lainnya',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Terjadwal',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public const VISIBILITIES = ['public' => 'Publik', 'internal' => 'Internal'];

    protected function casts(): array
    {
        return ['agenda_date' => 'date'];
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class, 'coverage_id');
    }

    public function group()
    {
        return $this->belongsTo(TVClubGroup::class, 'group_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where('visibility', 'public')->where('status', 'scheduled');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('agenda_date', '>=', today());
    }

    public function scopeBetweenDates(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('agenda_date', [$start, $end]);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? 'Lainnya';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getVisibilityLabelAttribute(): string
    {
        return self::VISIBILITIES[$this->visibility] ?? ucfirst($this->visibility);
    }

    public function getTimeLabelAttribute(): string
    {
        if (! $this->start_time) {
            return 'Waktu menyusul';
        }

        $start = str_replace(':', '.', substr($this->start_time, 0, 5));
        $end = $this->end_time ? '–'.str_replace(':', '.', substr($this->end_time, 0, 5)) : '';

        return $start.$end.' WIB';
    }
}
