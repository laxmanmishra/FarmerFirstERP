<?php

namespace App\Livewire\Admin\AuditLogs;

use App\Livewire\Concerns\WithDataTable;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Audit Logs')]
class Index extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $module = '';

    #[Url(except: '')]
    public string $event = '';

    #[Url(except: '')]
    public string $userId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public ?int $viewingId = null;

    public bool $showDetail = false;

    public function mount(): void
    {
        $this->authorize('audit.view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['module', 'event', 'userId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function view(int $id): void
    {
        $this->viewingId = $id;
        $this->showDetail = true;
    }

    public function render(): mixed
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($this->module !== '', fn ($query) => $query->where('module', $this->module))
            ->when($this->event !== '', fn ($query) => $query->where('event', $this->event))
            ->when($this->userId !== '', fn ($query) => $query->where('user_id', $this->userId))
            ->when($this->parseDate($this->from), fn ($query, Carbon $from) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($this->parseDate($this->to), fn ($query, Carbon $to) => $query->where('created_at', '<=', $to->endOfDay()))
            ->when($this->searchTerm(), fn ($query, string $term) => $query->where(fn ($query) => $query
                ->where('reason', 'like', $term)->orWhere('request_id', 'like', $term)->orWhere('ip_address', 'like', $term)))
            ->latest('id')
            ->paginate($this->perPage);

        return view('livewire.admin.audit-logs.index', [
            'logs' => $logs,
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'detail' => $this->viewingId ? AuditLog::query()->with('user:id,name')->find($this->viewingId) : null,
        ]);
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
