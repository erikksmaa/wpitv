<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentAuditEvent extends Model
{
    use HasUtcTimestamps;

    public const UPDATED_AT = null;

    protected $table = 'equipment_audit_events';

    // Writes must be explicitly assigned by the future authorized transaction service.
    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'created_at' => UtcDateTime::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'unit_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EquipmentLoan::class, 'loan_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EquipmentLoanItem::class, 'loan_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
