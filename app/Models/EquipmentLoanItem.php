<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentLoanItem extends Model
{
    use HasUtcTimestamps;

    protected $table = 'equipment_loan_items';

    // Writes must be explicitly assigned by the future authorized transaction service.
    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'unit_snapshot' => 'array',
            'returned_at' => UtcDateTime::class,
            'due_at_on_return' => UtcDateTime::class,
            'lost_at' => UtcDateTime::class,
            'created_at' => UtcDateTime::class,
            'updated_at' => UtcDateTime::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EquipmentLoan::class, 'loan_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(EquipmentAuditEvent::class, 'loan_item_id');
    }
}
