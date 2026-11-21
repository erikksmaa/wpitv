<?php

namespace App\Models;

use App\Models\Concerns\MonthlyActivityRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyActivityAttachment extends MonthlyActivityRecord
{
    public function scopeAttached(Builder $query): Builder
    {
        return $query->whereNotExists(
            MonthlyActivityEvent::query()->selectRaw('1')
                ->whereColumn('monthly_activity_events.report_id', 'monthly_activity_attachments.report_id')
                ->where('action', 'updated')->where('changes->operation', 'attachment_detached')
                ->whereRaw("JSON_EXTRACT(monthly_activity_events.changes, '$.attachment_id') = monthly_activity_attachments.id")
        );
    }

    protected function casts(): array
    {
        return [...parent::casts(), 'size_bytes' => 'integer'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyActivityReport::class, 'report_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MonthlyActivityItem::class, 'item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
