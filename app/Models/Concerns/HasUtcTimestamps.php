<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;

trait HasUtcTimestamps
{
    public function freshTimestamp(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }

        // Eloquent formats created_at/updated_at before invoking custom casts.
        // Normalize here before an assigned object's timezone can be discarded.
        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, 'UTC');

        return $date->utc()->format($this->getDateFormat());
    }
}
