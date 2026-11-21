<?php

namespace App\Models;

use App\Models\Concerns\MonthlyActivityRecord;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MonthlyActivityItem extends MonthlyActivityRecord
{
    public function deleteDraft(): bool
    {
        if ($this->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Draft item deletion requires the lifecycle transaction.');
        }

        return $this->performDraftDeletion();
    }

    protected function casts(): array
    {
        return [...parent::casts(), 'activity_date' => 'immutable_date:Y-m-d', 'source_context' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyActivityReport::class, 'report_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MonthlyActivityAttachment::class, 'item_id');
    }
}
