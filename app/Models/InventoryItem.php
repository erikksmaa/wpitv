<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'created_at' => UtcDateTime::class, 'updated_at' => UtcDateTime::class];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class, 'item_id');
    }
}
