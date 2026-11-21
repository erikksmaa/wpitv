<?php

namespace App\Models;

use App\Models\Concerns\TVClubSocialHistory;
use App\Policies\TVClubSocialPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(TVClubSocialPolicy::class)]
class TVClubSocialEvent extends TVClubSocialHistory
{
    protected $table = 'tvclub_social_events';

    protected function casts(): array
    {
        return [...parent::casts(), 'changes' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
