<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Farmers\SaveFarmer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreFarmerRequest;
use App\Http\Resources\FarmerResource;
use App\Models\Farmer;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('farmers.view'), 403);
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $farmers = Farmer::query()
            ->with('village.tehsil.district')
            ->withCount('enquiries')
            ->when(! $user->hasAllBranchAccess(), fn (Builder $query) => $query->whereIn('branch_id', $user->accessibleBranches()->pluck('id')))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "{$search}%")
                ->orWhere('alternate_mobile', 'like', "{$search}%")->orWhere('farmer_no', $search)))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return ApiResponse::success(FarmerResource::collection($farmers));
    }

    public function show(Request $request, Farmer $farmer): JsonResponse
    {
        abort_unless($request->user()->can('farmers.view'), 403);

        return ApiResponse::success(FarmerResource::make($farmer->load('village.tehsil.district')->loadCount('enquiries')));
    }

    public function store(StoreFarmerRequest $request, SaveFarmer $saveFarmer): JsonResponse
    {
        $attributes = collect($request->safe()->except('confirm_not_duplicate'))->map(fn ($value) => $value === '' ? null : $value)->all();

        $farmer = $saveFarmer->handle($attributes, $request->user()->workingBranch(), confirmedNotDuplicate: $request->boolean('confirm_not_duplicate'));

        return ApiResponse::success(FarmerResource::make($farmer->load('village.tehsil.district')), 'Farmer created.', status: 201);
    }
}
