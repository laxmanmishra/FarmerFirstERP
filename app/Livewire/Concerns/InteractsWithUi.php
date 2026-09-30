<?php

namespace App\Livewire\Concerns;

use App\Exceptions\BusinessRuleException;
use Closure;

/**
 * Toast helper and uniform handling of business-rule failures in Livewire actions.
 */
trait InteractsWithUi
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    /**
     * Runs a write operation; a BusinessRuleException becomes an error toast
     * instead of a server error. Returns null when the rule blocked the action.
     */
    protected function attempt(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (BusinessRuleException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return null;
        }
    }
}
