<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Deal;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only customer and deal lookups for field staff.
 */
class SalesController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('customers.view'), 403);
        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()->visibleTo($request->user())
            ->with('village.tehsil.district')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "{$search}%")->orWhere('customer_no', $search)))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 25), 100))
            ->through(fn (Customer $customer) => $this->customerPayload($customer));

        return ApiResponse::success($customers->items(), meta: ['pagination' => [
            'current_page' => $customers->currentPage(), 'per_page' => $customers->perPage(), 'total' => $customers->total(), 'last_page' => $customers->lastPage(),
        ]]);
    }

    public function customer(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->can('customers.view'), 403);
        abort_unless(Customer::query()->visibleTo($request->user())->whereKey($customer->id)->exists(), 404);

        $customer->load(['village.tehsil.district', 'deals.stage']);

        return ApiResponse::success($this->customerPayload($customer) + [
            'deals' => $customer->deals->map(fn (Deal $deal) => $this->dealPayload($deal))->values(),
        ]);
    }

    public function deals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('deals.view'), 403);

        $deals = Deal::query()->visibleTo($request->user())->with(['stage', 'customer'])
            ->latest('id')->paginate(min((int) $request->query('per_page', 25), 100))
            ->through(fn (Deal $deal) => $this->dealPayload($deal) + ['customer' => ['id' => $deal->customer->id, 'customer_no' => $deal->customer->customer_no, 'name' => $deal->customer->name]]);

        return ApiResponse::success($deals->items(), meta: ['pagination' => [
            'current_page' => $deals->currentPage(), 'per_page' => $deals->perPage(), 'total' => $deals->total(), 'last_page' => $deals->lastPage(),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_no' => $customer->customer_no,
            'farmer_id' => $customer->farmer_id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'location' => $customer->locationLabel(),
            'customer_since' => $customer->customer_since->toDateString(),
            'possible_duplicate_of_id' => $customer->possible_duplicate_of_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dealPayload(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'deal_no' => $deal->deal_no,
            'stage' => ['code' => $deal->stage->code, 'name' => $deal->stage->name],
            'deal_value' => $deal->deal_value,
            'finance_required' => $deal->finance_required,
            'finance_amount' => $deal->finance_amount,
            'customer_contribution' => $deal->customer_contribution,
            'expected_delivery_date' => $deal->expected_delivery_date?->toDateString(),
        ];
    }
}
