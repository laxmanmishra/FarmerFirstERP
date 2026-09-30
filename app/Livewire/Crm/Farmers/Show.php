<?php

namespace App\Livewire\Crm\Farmers;

use App\Models\Enquiry;
use App\Models\Farmer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $farmerId;

    public function mount(Farmer $farmer): void
    {
        $this->authorize('farmers.view');

        $user = Auth::user();
        abort_unless($user->hasAllBranchAccess() || $user->canAccessBranch($farmer->branch_id), 404);

        $this->farmerId = $farmer->id;
    }

    public function render(): mixed
    {
        $farmer = Farmer::query()->with(['village.tehsil.district', 'branch', 'creator:id,name'])->findOrFail($this->farmerId);

        $enquiries = Enquiry::query()
            ->visibleTo(Auth::user())
            ->where('farmer_id', $farmer->id)
            ->with(['validationStage', 'pipelineStage', 'assignee:id,name', 'requirements.product', 'requirements.brand'])
            ->latest('id')
            ->get();

        return view('livewire.crm.farmers.show', [
            'farmer' => $farmer,
            'enquiries' => $enquiries,
            'hiddenCount' => Enquiry::query()->where('farmer_id', $farmer->id)->count() - $enquiries->count(),
        ])->title($farmer->name);
    }
}
