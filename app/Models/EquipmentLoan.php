<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentLoan extends Model
{
    use HasUtcTimestamps;

    protected $table = 'equipment_loans';

    // Writes must be explicitly assigned by the future authorized transaction service.
    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'borrowed_at' => UtcDateTime::class,
            'due_at' => UtcDateTime::class,
            'initial_due_at' => UtcDateTime::class,
            'version' => 'integer',
            'created_at' => UtcDateTime::class,
            'updated_at' => UtcDateTime::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EquipmentLoanItem::class, 'loan_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(EquipmentAuditEvent::class, 'loan_id');
    }
}
