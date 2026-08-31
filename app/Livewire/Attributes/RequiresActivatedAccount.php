<?php

namespace App\Livewire\Attributes;

use Attribute as PhpAttribute;
use Closure;
use Livewire\Features\SupportAttributes\Attribute as LivewireAttribute;

#[PhpAttribute(PhpAttribute::TARGET_METHOD)]
/**
 * Guards a Livewire method behind an activated account.
 */
class RequiresActivatedAccount extends LivewireAttribute
{
    public function __construct(public bool $silent = false) {}

    public function call(array $params, Closure $returnEarly): void
    {
        $user = auth()->user();

        if ($user !== null && ! $user->isPendingActivation()) {
            return;
        }

        if ($this->silent) {
            $returnEarly();

            return;
        }

        if ($user === null) {
            $this->component->redirectRoute('login', ['redirect_to' => url()->previous()]);
        } else {
            session()->flash('status', 'verification-required');

            $this->component->redirectRoute('verification.notice', navigate: false);
        }

        $returnEarly();
    }
}
