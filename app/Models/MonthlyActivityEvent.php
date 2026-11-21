<?php

namespace App\Models;

use App\Models\Concerns\MonthlyActivityHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyActivityEvent extends MonthlyActivityHistory
{
    protected function casts(): array
    {
        return [...parent::casts(), 'changes' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyActivityReport::class, 'report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(MonthlyActivityVersion::class, 'report_version_id');
    }
}
