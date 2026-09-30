<?php

namespace App\Livewire\Auth;

use App\Services\AuthenticationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Sign in')]
class Login extends Component
{
    #[Validate('required|string|max:255')]
    public string $email = '';

    #[Validate('required|string|max:255')]
    public string $password = '';

    public bool $remember = false;

    public function login(AuthenticationService $authentication): mixed
    {
        $this->validate();

        $throttleKey = 'login:'.mb_strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey), 'minutes' => 1]),
            ]);
        }

        try {
            $user = $authentication->attempt($this->email, $this->password, request());
        } catch (ValidationException $exception) {
            RateLimiter::hit($throttleKey);
            $this->reset('password');

            throw $exception;
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $this->remember);
        session()->regenerate();

        if ($user->current_branch_id === null && ($branch = $user->accessibleBranches()->first())) {
            $user->forceFill(['current_branch_id' => $branch->id])->saveQuietly();
        }

        return $this->redirectIntended(route($user->must_change_password ? 'password.change' : 'dashboard'), navigate: false);
    }

    public function render(): mixed
    {
        return view('livewire.auth.login');
    }
}
