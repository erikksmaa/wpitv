<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\TVClubSocialRecord;
use App\Policies\TVClubSocialPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(TVClubSocialPolicy::class)]
class TVClubSocialPost extends TVClubSocialRecord
{
    protected $table = 'tvclub_social_posts';

    protected function casts(): array
    {
        return [...parent::casts(), 'published_at' => UtcDateTime::class, 'archived_at' => UtcDateTime::class, 'lock_version' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TVClubSocialAccount::class, 'account_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TVClubMember::class, 'member_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(TVClubSocialPostObservation::class, 'post_id');
    }
}
