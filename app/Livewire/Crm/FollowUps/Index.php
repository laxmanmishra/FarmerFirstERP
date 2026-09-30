<?php

namespace App\Livewire\Crm\FollowUps;

use App\Actions\FollowUps\CompleteFollowUp;
use App\Enums\FollowUpStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\LookupValue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Follow-ups')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    #[Url(except: 'today')]
    public string $tab = 'today';

    #[Url(except: '')]
    public string $scope = '';

    public ?int $actingId = null;

    public mixed $modal = null;

    public string $outcome = '';

    public bool $scheduleNext = false;

    public string $nextType = 'CALL';

    public string $nextDueAt = '';

    public string $nextPurpose = '';

    public function mount(): void
    {
        $this->authorize('follow_ups.view');
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, ['today', 'overdue', 'upcoming', 'completed'], true) ? $this->tab : 'today';
        $this->resetPage();
    }

    public function open(string $modal, int $followUpId): void
    {
        $this->authorize('follow_ups.manage');
        $this->resetValidation();
        $this->reset('outcome', 'scheduleNext', 'nextPurpose');
        $this->nextType = 'CALL';
        $this->nextDueAt = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
        $this->actingId = FollowUp::query()->visibleTo(Auth::user())->findOrFail($followUpId)->id;
        $this->modal = in_array($modal, ['complete', 'cancel'], true) ? $modal : null;
    }

    public function submit(CompleteFollowUp $complete): void
    {
        $this->authorize('follow_ups.manage');
        $followUp = FollowUp::query()->visibleTo(Auth::user())->with(['followable', 'assignee'])->findOrFail($this->actingId);

        $this->validate([
            'outcome' => ['required', 'string', 'min:3', 'max:1000'],
            ...($this->modal === 'complete' && $this->scheduleNext ? [
                'nextType' => ['required', Rule::in(LookupValue::options(LookupValue::FOLLOW_UP_TYPE)->keys())],
                'nextDueAt' => ['required', 'date', 'after:now'],
                'nextPurpose' => ['required', 'string', 'min:3', 'max:500'],
            ] : []),
        ], attributes: ['outcome' => $this->modal === 'cancel' ? __('reason') : __('outcome'), 'nextDueAt' => __('due time'), 'nextPurpose' => __('purpose')]);

        $result = $this->attempt(fn () => $this->modal === 'cancel'
            ? $complete->cancel(Auth::user(), $followUp, $this->outcome)
            : $complete->handle(Auth::user(), $followUp, $this->outcome, $this->scheduleNext
                ? ['type_code' => $this->nextType, 'due_at' => CarbonImmutable::parse($this->nextDueAt), 'purpose' => $this->nextPurpose]
                : null));

        if ($result) {
            $this->modal = null;
            $this->toast($result->status === FollowUpStatus::Cancelled ? __('Follow-up cancelled.') : __('Follow-up completed.'));
        }
    }

    public function render(): mixed
    {
        $user = Auth::user();
        $base = fn () => FollowUp::query()->visibleTo($user)
            ->when($this->scope === 'mine' || ! $user->canAny(['enquiries.view_team', 'enquiries.view_all']), fn (Builder $query) => $query->where('assigned_employee_id', $user->employee?->id));

        $query = $base()
            ->with(['assignee:id,name', 'followable' => fn ($morph) => $morph->morphWith([Enquiry::class => ['farmer.village']])])
            ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('purpose', 'like', $term)
                ->orWhereHasMorph('followable', [Enquiry::class], fn (Builder $query) => $query->where('enquiry_no', 'like', $term)
                    ->orWhereHas('farmer', fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('mobile', 'like', $term)))));

        match ($this->tab) {
            'overdue' => $query->overdue()->orderBy('due_at'),
            'upcoming' => $query->upcoming()->orderBy('due_at'),
            'completed' => $query->whereIn('status', [FollowUpStatus::Completed, FollowUpStatus::Cancelled])->orderByDesc('completed_at'),
            default => $query->pending()->where('due_at', '<=', now()->endOfDay())->orderBy('due_at'),
        };

        return view('livewire.crm.follow-ups.index', [
            'followUps' => $query->paginate($this->perPage),
            'counts' => [
                'today' => $base()->dueToday()->count(),
                'overdue' => $base()->overdue()->count(),
                'upcoming' => $base()->upcoming()->count(),
                'completedToday' => $base()->where('status', FollowUpStatus::Completed)->where('completed_at', '>=', today())->count(),
            ],
            'types' => LookupValue::options(LookupValue::FOLLOW_UP_TYPE),
            'canSeeTeam' => $user->canAny(['enquiries.view_team', 'enquiries.view_all']),
        ]);
    }
}
