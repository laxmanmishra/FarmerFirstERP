<?php

namespace App\Livewire\Sales\Customers;

use App\Enums\QuotationStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\WorkflowStatusHistory;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Customer 360° (SRS §12): profile, enquiries, quotations, deals and timeline.
 * Documents, payments and fulfilment tabs join as those modules go live.
 */
class Show extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $customerId;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    public bool $showEdit = false;

    /** @var array<string, string> */
    public array $profile = [];

    public function mount(Customer $customer): void
    {
        $this->authorize('customers.view');
        abort_unless(Customer::query()->visibleTo(Auth::user())->whereKey($customer->id)->exists(), 404);
        $this->customerId = $customer->id;
        $this->tab = in_array($this->tab, ['overview', 'enquiries', 'quotations', 'deals', 'timeline'], true) ? $this->tab : 'overview';
    }

    public function editProfile(): void
    {
        $this->authorize('customers.update');
        $customer = Customer::query()->findOrFail($this->customerId);
        $this->profile = array_map(fn ($value) => (string) $value, $customer->only(['name', 'father_name', 'mobile', 'alternate_mobile', 'whatsapp_number', 'email', 'address', 'pin_code', 'pan']));
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function saveProfile(): void
    {
        $this->authorize('customers.update');

        $validated = $this->validate([
            'profile.name' => ['required', 'string', 'max:255'],
            'profile.father_name' => ['nullable', 'string', 'max:255'],
            'profile.mobile' => ['required', 'digits:10'],
            'profile.alternate_mobile' => ['nullable', 'digits:10', 'different:profile.mobile'],
            'profile.whatsapp_number' => ['nullable', 'digits:10'],
            'profile.email' => ['nullable', 'email:rfc', 'max:255'],
            'profile.address' => ['nullable', 'string', 'max:500'],
            'profile.pin_code' => ['nullable', 'digits:6'],
            'profile.pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/', Rule::unique('customers', 'pan')->ignore($this->customerId)],
        ], ['profile.pan.regex' => __('Enter a valid PAN (e.g. ABCDE1234F).')], ['profile.pan' => 'PAN', 'profile.mobile' => __('mobile')]);

        Customer::query()->findOrFail($this->customerId)->update(array_map(fn ($value) => $value === '' ? null : $value, $validated['profile']));
        $this->showEdit = false;
        $this->toast(__('Customer profile updated.'));
    }

    public function render(): mixed
    {
        $customer = Customer::query()->with(['village.tehsil.district', 'farmer', 'branch', 'possibleDuplicateOf', 'creator:id,name'])->findOrFail($this->customerId);
        $user = Auth::user();

        $enquiries = Enquiry::query()->visibleTo($user)->where('farmer_id', $customer->farmer_id)
            ->with(['validationStage', 'pipelineStage', 'assignee:id,name', 'requirements'])->latest('id')->get();
        $quotations = Quotation::query()->visibleTo($user)->where('farmer_id', $customer->farmer_id)->latest('id')->get();
        $deals = Deal::query()->visibleTo($user)->where('customer_id', $customer->id)->with(['stage', 'primarySalesman:id,name'])->latest('id')->get();

        return view('livewire.sales.customers.show', [
            'customer' => $customer,
            'enquiries' => $enquiries,
            'quotations' => $quotations,
            'deals' => $deals,
            'kpis' => [
                'enquiries' => $enquiries->count(),
                'openQuotations' => $quotations->whereIn('status', [QuotationStatus::Draft, QuotationStatus::PendingApproval, QuotationStatus::Issued])->count(),
                'activeDeals' => $deals->filter(fn (Deal $deal) => ! $deal->stage->is_final)->count(),
                'approvedValue' => Money::add(...$deals->filter(fn (Deal $deal) => $deal->stage->is_completion)->pluck('deal_value')->all()),
            ],
            'timeline' => $this->tab === 'timeline' ? $this->timeline($customer, $enquiries, $deals) : collect(),
        ])->title($customer->name);
    }

    /**
     * @param  Collection<int, Enquiry>  $enquiries
     * @param  Collection<int, Deal>  $deals
     * @return Collection<int, array<string, mixed>>
     */
    private function timeline(Customer $customer, Collection $enquiries, Collection $deals): Collection
    {
        $subjects = $enquiries->map(fn ($enquiry) => [$enquiry->getMorphClass(), $enquiry->id])
            ->merge($deals->map(fn ($deal) => [$deal->getMorphClass(), $deal->id]));

        $histories = WorkflowStatusHistory::query()->with(['toStage', 'fromStage', 'user:id,name', 'subject'])
            ->where(function ($query) use ($subjects): void {
                foreach ($subjects as [$type, $id]) {
                    $query->orWhere(fn ($query) => $query->where('subject_type', $type)->where('subject_id', $id));
                }
            })
            ->when($subjects->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('id')->limit(100)->get();

        $items = $histories->map(fn (WorkflowStatusHistory $history) => [
            'at' => $history->created_at,
            'title' => ($history->subject instanceof Deal ? $history->subject->deal_no : $history->subject?->enquiry_no).' · '
                .($history->fromStage ? $history->fromStage->name.' → ' : '').$history->toStage->name,
            'body' => $history->remarks,
            'actor' => $history->user?->name,
            'tone' => $history->toStage->color,
            'icon' => $history->subject instanceof Deal ? 'handshake' : 'inbox',
        ]);

        $items->push([
            'at' => $customer->created_at,
            'title' => __('Became customer :no', ['no' => $customer->customer_no]),
            'body' => null,
            'actor' => $customer->creator?->name,
            'tone' => 'brand',
            'icon' => 'identification',
        ]);

        foreach (AuditLog::query()->forSubject($customer)->where('event', 'updated')->with('user:id,name')->latest('id')->limit(20)->get() as $entry) {
            $items->push(['at' => $entry->created_at, 'title' => __('Profile updated: :fields', ['fields' => implode(', ', array_keys($entry->new_values ?? []))]), 'body' => null, 'actor' => $entry->user?->name, 'tone' => 'slate', 'icon' => 'pencil']);
        }

        return $items->sortByDesc('at')->values();
    }
}
