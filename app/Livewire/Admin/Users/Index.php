<?php

namespace App\Livewire\Admin\Users;

use App\Actions\Users\ResetUserPassword;
use App\Actions\Users\SaveUser;
use App\Actions\Users\SetUserActiveState;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Title('Users')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['name', 'email', 'last_login_at', 'created_at'];

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $role = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $mobile = '';

    /** @var list<string> */
    public array $roles = [];

    public bool $showStatusModal = false;

    public ?int $statusUserId = null;

    public string $statusReason = '';

    public bool $showPasswordModal = false;

    public ?string $revealedPassword = null;

    public ?string $revealedFor = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function create(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('update', $user);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->mobile = (string) $user->mobile;
        $this->roles = $user->getRoleNames()->all();
        $this->showForm = true;
    }

    public function save(SaveUser $saveUser): void
    {
        $user = $this->editingId ? User::query()->findOrFail($this->editingId) : null;
        $user ? $this->authorize('update', $user) : $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'mobile' => ['nullable', 'digits:10', Rule::unique('users', 'mobile')->ignore($this->editingId)],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $canAssignRoles = Auth::user()->can('assignRoles', [User::class, $user]);

        $result = $this->attempt(fn () => $saveUser->handle(
            Auth::user(),
            ['name' => $validated['name'], 'email' => $validated['email'], 'mobile' => $validated['mobile'] ?: null],
            $canAssignRoles ? $validated['roles'] : null,
            $user,
        ));

        if ($result === null) {
            return;
        }

        $this->showForm = false;

        if ($result['temporary_password']) {
            $this->revealPassword($result['user'], $result['temporary_password']);
        }

        $this->toast($user ? __('User updated.') : __('User created.'));
    }

    public function confirmStatusChange(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('deactivate', $user);

        $this->statusUserId = $user->id;
        $this->statusReason = '';
        $this->resetValidation();
        $this->showStatusModal = true;
    }

    public function changeStatus(SetUserActiveState $setState): void
    {
        $user = User::query()->findOrFail($this->statusUserId);
        $this->authorize('deactivate', $user);

        $this->validate(['statusReason' => ['required', 'string', 'min:5', 'max:500']], attributes: ['statusReason' => __('reason')]);

        $activate = ! $user->is_active;

        $changed = $this->attempt(function () use ($setState, $user, $activate): bool {
            $setState->handle(Auth::user(), $user, $activate, $this->statusReason);

            return true;
        });

        if ($changed === null) {
            return;
        }

        $this->showStatusModal = false;
        $this->toast($activate ? __(':name has been activated.', ['name' => $user->name]) : __(':name has been deactivated and signed out.', ['name' => $user->name]));
    }

    public function resetPassword(int $userId, ResetUserPassword $reset): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('resetPassword', $user);

        $this->revealPassword($user, $reset->handle($user));
    }

    public function unlock(int $userId, ResetUserPassword $reset): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('resetPassword', $user);

        $reset->unlock($user);
        $this->toast(__(':name has been unlocked.', ['name' => $user->name]));
    }

    public function closePasswordModal(): void
    {
        $this->showPasswordModal = false;
        $this->revealedPassword = null;
        $this->revealedFor = null;
    }

    public function render(): mixed
    {
        $query = User::query()
            ->with(['roles:id,name', 'employee:id,user_id,employee_code'])
            ->when($this->searchTerm(), fn ($query, string $term) => $query->where(fn ($query) => $query
                ->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('mobile', 'like', $term)))
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->status === 'locked', fn ($query) => $query->where('locked_until', '>', now()))
            ->when($this->role !== '', fn ($query) => $query->role($this->role));

        return view('livewire.admin.users.index', [
            'users' => $this->applySorting($query, 'name', 'asc')->paginate($this->perPage),
            'roleOptions' => Role::query()->orderBy('name')->pluck('name')->all(),
            'statusUser' => $this->statusUserId ? User::query()->find($this->statusUserId) : null,
            'canAssignRoles' => Auth::user()->can('assignRoles', [User::class, $this->editingId ? User::query()->find($this->editingId) : null]),
        ]);
    }

    private function revealPassword(User $user, string $password): void
    {
        $this->revealedPassword = $password;
        $this->revealedFor = $user->name;
        $this->showPasswordModal = true;
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'mobile', 'roles');
        $this->resetValidation();
    }
}
