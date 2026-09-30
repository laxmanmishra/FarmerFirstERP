<?php

namespace App\Livewire\Auth;

use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Change password')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(AuditService $audit): mixed
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = Auth::user();
        $user->forceFill([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // Sign out other devices and API clients that used the old password.
        Auth::logoutOtherDevices($this->password);
        $user->tokens()->delete();

        $audit->record('password_changed', 'auth', $user);

        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('toast', ['type' => 'success', 'message' => __('Your password has been changed.')]);

        return $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.auth.change-password', ['forced' => Auth::user()->must_change_password]);
    }
}
