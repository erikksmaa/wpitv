<?php

namespace App\Models;

use App\Models\Concerns\MonthlyActivityRecord;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MonthlyActivityReport extends MonthlyActivityRecord
{
    private bool $coordinatedWrite = false;

    protected function casts(): array
    {
        return [...parent::casts(), 'period_start' => 'immutable_date:Y-m-d', 'version' => 'integer'];
    }

    public function save(array $options = [])
    {
        if (! $this->coordinatedWrite || ($this->exists && $this->isDirty(['id', 'division', 'period_start', 'created_by', 'created_at']))) {
            throw new LogicException('Monthly report writes require the lifecycle service and fixed identity.');
        }

        return parent::save($options);
    }

    public function saveCoordinated(): bool
    {
        if ($this->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Monthly report writes require a transaction.');
        }

        $this->coordinatedWrite = true;
        try {
            return $this->save();
        } finally {
            $this->coordinatedWrite = false;
        }
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        throw new LogicException('Monthly report writes require the lifecycle service.');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MonthlyActivityItem::class, 'report_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MonthlyActivityAttachment::class, 'report_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MonthlyActivityVersion::class, 'report_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MonthlyActivityEvent::class, 'report_id');
    }
}
