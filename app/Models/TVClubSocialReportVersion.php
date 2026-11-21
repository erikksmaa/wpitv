<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\TVClubSocialHistory;
use App\Policies\TVClubSocialPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(TVClubSocialPolicy::class)]
class TVClubSocialReportVersion extends TVClubSocialHistory
{
    protected $table = 'tvclub_social_report_versions';

    protected function casts(): array
    {
        return [...parent::casts(), 'period_start' => 'immutable_date:Y-m-d', 'published_at' => UtcDateTime::class, 'snapshot' => 'array', 'revision_number' => 'integer'];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
