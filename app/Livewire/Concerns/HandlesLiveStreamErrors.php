<?php

namespace App\Livewire\Concerns;

use App\Exceptions\LiveStreamOperationException;
use Throwable;

trait HandlesLiveStreamErrors
{
    public function exceptionHandlesLiveStreamErrors(Throwable $e, $stopPropagation): void
    {
        // Livewire supplies this argument by the name "e". A different name
        // makes its container try to construct Throwable and recursively fail.
        if ($e instanceof LiveStreamOperationException && $e->isConflict()) {
            $this->addError('operation', $e->getMessage());
            $stopPropagation();
        }
    }
}
