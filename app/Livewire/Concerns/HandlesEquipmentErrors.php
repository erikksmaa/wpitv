<?php

namespace App\Livewire\Concerns;

use App\Exceptions\EquipmentOperationException;
use Throwable;

trait HandlesEquipmentErrors
{
    public function exceptionHandlesEquipmentErrors(Throwable $e, $stopPropagation): void
    {
        // Expected mutation conflicts use the existing form error display.
        // Do not throw from this hook: Livewire wraps the hook itself and would
        // dispatch it recursively. Other failures reach the scoped Laravel mapper.
        if ($e instanceof EquipmentOperationException && $e->field !== null) {
            $this->addError($e->field, $e->getMessage());
            $stopPropagation();
        }
    }
}
