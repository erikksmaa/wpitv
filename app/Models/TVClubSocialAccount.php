<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\TVClubSocialRecord;
use App\Policies\TVClubSocialPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(TVClubSocialPolicy::class)]
class TVClubSocialAccount extends TVClubSocialRecord
{
    protected $table = 'tvclub_social_accounts';

    protected function casts(): array
    {
        return [...parent::casts(), 'archived_at' => UtcDateTime::class, 'lock_version' => 'integer'];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(TVClubSocialPost::class, 'account_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(TVClubSocialAccountObservation::class, 'account_id');
    }
}
