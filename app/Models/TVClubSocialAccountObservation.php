<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\TVClubSocialHistory;
use App\Policies\TVClubSocialPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(TVClubSocialPolicy::class)]
class TVClubSocialAccountObservation extends TVClubSocialHistory
{
    protected $table = 'tvclub_social_account_observations';

    protected function casts(): array
    {
        return [...parent::casts(), 'observed_at' => UtcDateTime::class];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TVClubSocialAccount::class, 'account_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_id');
    }
}
