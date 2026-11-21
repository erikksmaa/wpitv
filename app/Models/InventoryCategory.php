<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class InventoryCategory extends Model
{
    use HasUtcTimestamps;

    protected $table = 'inventory_categories';

    // Writes must be explicitly assigned by the future authorized transaction service.
    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => UtcDateTime::class,
            'updated_at' => UtcDateTime::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }

    public function itemUnits(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryUnit::class, InventoryItem::class, 'category_id', 'item_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class, 'category_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(EquipmentAuditEvent::class, 'category_id');
    }
}
