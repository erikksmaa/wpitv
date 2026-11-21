<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryUnit extends Model
{
    use HasUtcTimestamps;

    protected $table = 'inventory_units';

    // Writes must be explicitly assigned by the future authorized transaction service.
    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
            'created_at' => UtcDateTime::class,
            'updated_at' => UtcDateTime::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(EquipmentLoanItem::class, 'inventory_unit_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(EquipmentAuditEvent::class, 'unit_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
