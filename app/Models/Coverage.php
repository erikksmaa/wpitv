<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Coverage extends Model
{
    use HasFactory;

    protected $table = 'tr_perliputan';

    protected $fillable = [
        'submission_code',
        'name',
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

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Menunggu Peninjauan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_IN_PROGRESS => 'Sedang Berjalan',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_REJECTED => 'Ditolak',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_IN_PROGRESS => 'Sedang Berjalan',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_REJECTED => 'Ditolak',
            default => ucfirst(str_replace('_', ' ', $this->status ?? 'pending')),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-warning text-dark',
            self::STATUS_APPROVED => 'bg-primary',
            self::STATUS_IN_PROGRESS => 'bg-info text-dark',
            self::STATUS_COMPLETED => 'bg-success',
            self::STATUS_REJECTED => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public static function generateSubmissionCode(): string
    {
        do {
            $code = 'PLP-'.date('Ymd').'-'.strtoupper(Str::random(4));
        } while (self::where('submission_code', $code)->exists());

        return $code;
    }

    public function agendas()
    {
        return $this->hasMany(WpitvAgenda::class, 'coverage_id');
    }
}
