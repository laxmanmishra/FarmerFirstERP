<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Enquiries\CreateEnquiry;
use App\Actions\Enquiries\EnquiryData;
use App\Enums\DealType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEnquiryRequest;
use App\Http\Resources\EnquiryResource;
use App\Models\Enquiry;
use App\Models\Farmer;
use App\Services\CrmDuplicateService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    private const RELATIONS = ['farmer.village.tehsil.district', 'validationStage', 'pipelineStage', 'assignee', 'requirements.product.brand', 'requirements.brand', 'requirements.variant', 'exchangeTractor'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAny(['enquiries.view_own', 'enquiries.view_team', 'enquiries.view_all']), 403);

        $filter = $request->query('filter', []);
        $enquiries = Enquiry::query()->visibleTo($user)->with(self::RELATIONS)
            ->when(($filter['view'] ?? null) === 'open', fn (Builder $query) => $query->whereNull('closed_at'))
            ->when(($filter['view'] ?? null) === 'validation', fn (Builder $query) => $query->whereNull('pipeline_stage_id')->whereNull('closed_at'))
            ->when(($filter['view'] ?? null) === 'pipeline', fn (Builder $query) => $query->whereNotNull('pipeline_stage_id')->whereNull('closed_at'))
            ->when($filter['farmer_id'] ?? null, fn (Builder $query, $farmerId) => $query->where('farmer_id', $farmerId))
            ->when(trim((string) $request->query('search', '')), fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('enquiry_no', $search)
                ->orWhereHas('farmer', fn (Builder $query) => $query->where('mobile', 'like', "{$search}%")->orWhere('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return ApiResponse::success(EnquiryResource::collection($enquiries));
    }

    public function show(Request $request, Enquiry $enquiry): JsonResponse
    {
        abort_unless(Enquiry::query()->visibleTo($request->user())->whereKey($enquiry->id)->exists(), 404);

        return ApiResponse::success(EnquiryResource::make($enquiry->load(self::RELATIONS)));
    }

    /**
     * Potential duplicates before submitting (SRS §9).
     */
    public function duplicates(Request $request, CrmDuplicateService $duplicates): JsonResponse
    {
        abort_unless($request->user()->can('enquiries.create'), 403);

        $validated = $request->validate([
            'farmer_id' => ['required', Rule::exists('farmers', 'id')],
            'deal_type' => ['required', Rule::enum(DealType::class)],
            'expected_purchase_date' => ['required', 'date'],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer'],
        ]);

        $matches = $duplicates->enquiries(
            Farmer::query()->findOrFail($validated['farmer_id']),
            DealType::from($validated['deal_type']),
            array_map('intval', $validated['product_ids'] ?? []),
            CarbonImmutable::parse($validated['expected_purchase_date']),
        );

        return ApiResponse::success(EnquiryResource::collection($matches), $matches->isEmpty() ? 'No potential duplicates.' : 'Potential duplicates found.');
    }

    public function store(StoreEnquiryRequest $request, CreateEnquiry $create): JsonResponse
    {
        $enquiry = $create->handle(
            $request->user(),
            Farmer::query()->findOrFail($request->integer('farmer_id')),
            EnquiryData::fromArray($request->validated()),
            $request->filled('assigned_employee_id') ? $request->integer('assigned_employee_id') : null,
            $request->input('duplicate_override_reason'),
        );

        return ApiResponse::success(EnquiryResource::make($enquiry->load(self::RELATIONS)), 'Enquiry created.', status: 201);
    }
}
