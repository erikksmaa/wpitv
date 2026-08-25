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

    /**
     * Get total cumulative hits across all time.
     */
    public static function getTotalHits(): int
    {
        return (int) self::sum('hits');
    }

    /**
     * Get total visitor logs count across all time.
     */
    public static function getTotalVisitors(): int
    {
        return (int) self::count();
    }

    /**
     * Get total hits for today.
     */
    public static function getTodayHits(): int
    {
        return (int) self::whereDate('tanggal', today())->sum('hits');
    }

    /**
     * Get total visitor records for today.
     */
    public static function getTodayVisitors(): int
    {
        return (int) self::whereDate('tanggal', today())->count();
    }

    /**
     * Get active online visitors within the last N minutes (default: 5 mins / 300s).
     */
    public static function getOnlineVisitors(int $minutes = 5): int
    {
        $threshold = time() - ($minutes * 60);

        return (int) self::where('online', '>', $threshold)->count();
    }
}
