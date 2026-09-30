<?php

namespace App\Livewire\Crm\ReopenRequests;

use App\Actions\Pipeline\DecideReopenRequest;
use App\Enums\ApprovalStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Enquiry;
use App\Models\ReopenRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Reopen Requests')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    public mixed $modal = null;

    public ?int $requestId = null;

    public string $remarks = '';

    public function mount(): void
    {
        $this->authorize('enquiries.reopen');
    }

    public function decide(int $requestId, string $decision): void
    {
        $this->authorize('enquiries.reopen');
        $this->requestId = $this->query()->findOrFail($requestId)->id;
        $this->remarks = '';
        $this->resetValidation();
        $this->modal = $decision === 'approve' ? 'approve' : 'reject';
    }

    public function submit(DecideReopenRequest $decide): void
    {
        $this->authorize('enquiries.reopen');
        $request = $this->query()->with('enquiry')->findOrFail($this->requestId);
        $approve = $this->modal === 'approve';

        $this->validate(['remarks' => [$approve ? 'nullable' : 'required', 'string', 'max:1000']], attributes: ['remarks' => __('remarks')]);

        if ($this->attempt(fn () => $decide->handle(Auth::user(), $request, $approve, $this->remarks ?: null))) {
            $this->modal = null;
            $this->toast($approve ? __(':no reopened.', ['no' => $request->enquiry->enquiry_no]) : __('Request rejected.'));
        }
    }

    public function render(): mixed
    {
        return view('livewire.crm.reopen-requests.index', [
            'requests' => $this->query()
                ->with(['enquiry.farmer', 'enquiry.pipelineStage', 'enquiry.validationStage', 'requester:id,name', 'decider:id,name'])
                ->when(ApprovalStatus::tryFrom($this->status), fn (Builder $query, ApprovalStatus $status) => $query->where('status', $status))
                ->latest('id')
                ->paginate($this->perPage),
        ]);
    }

    /**
     * @return Builder<ReopenRequest>
     */
    private function query(): Builder
    {
        return ReopenRequest::query()->whereHas('enquiry', fn (Builder $query) => $query->whereIn('id', Enquiry::query()->visibleTo(Auth::user())->select('id')));
    }
}
