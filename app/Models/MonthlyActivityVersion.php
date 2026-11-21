<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\MonthlyActivityHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyActivityVersion extends MonthlyActivityHistory
{
    protected function casts(): array
    {
        return [...parent::casts(), 'snapshot' => 'array', 'submitted_at' => UtcDateTime::class];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyActivityReport::class, 'report_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MonthlyActivityEvent::class, 'report_version_id');
    }
}
