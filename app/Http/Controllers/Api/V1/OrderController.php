<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\StoreDocument;
use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Models\FulfilmentTask;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orders with fulfilment tasks and the document checklist; document upload against a
 * requirement for field staff (camera capture on mobile).
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('orders.view'), 403);
        $search = trim((string) $request->query('search', ''));

        $orders = Order::query()->visibleTo($request->user())->with(['stage', 'customer'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('order_no', $search)->orWhereHas('customer', fn (Builder $query) => $query->where('mobile', 'like', "{$search}%")->orWhere('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 25), 100))
            ->through(fn (Order $order) => $this->orderPayload($order));

        return ApiResponse::success($orders->items(), meta: ['pagination' => [
            'current_page' => $orders->currentPage(), 'per_page' => $orders->perPage(), 'total' => $orders->total(), 'last_page' => $orders->lastPage(),
        ]]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($request->user()->can('orders.view'), 403);
        abort_unless(Order::query()->visibleTo($request->user())->whereKey($order->id)->exists(), 404);

        $order->load(['stage', 'customer', 'fulfilment.tasks.type', 'fulfilment.tasks.stage', 'documentRequirements.documentType', 'documentRequirements.department', 'documentRequirements.document.type']);

        return ApiResponse::success($this->orderPayload($order) + [
            'fulfilment_no' => $order->fulfilment?->fulfilment_no,
            'tasks' => $order->fulfilment?->tasks->map(fn (FulfilmentTask $task) => [
                'id' => $task->id,
                'type' => $task->type->code,
                'name' => $task->type->name,
                'requirement_state' => $task->requirement_state->value,
                'stage' => ['code' => $task->stage->code, 'name' => $task->stage->name, 'is_completion' => $task->stage->is_completion],
                'blocks_delivery' => $task->blocks_delivery,
                'responsible_employee_id' => $task->responsible_employee_id,
            ])->values() ?? [],
            'documents' => $order->documentRequirements->map(fn (DocumentRequirement $requirement) => [
                'requirement_id' => $requirement->id,
                'document_type' => $requirement->documentType->code,
                'name' => $requirement->documentType->name,
                'department' => $requirement->department->name,
                'requirement_state' => $requirement->requirement_state->value,
                'status' => $requirement->status()->value,
                'satisfied' => $requirement->isSatisfied(),
                'blocks_delivery' => $requirement->blocks_delivery,
                'due_date' => $requirement->due_date?->toDateString(),
                'document_id' => $requirement->document_id,
            ])->values(),
        ]);
    }

    /**
     * multipart/form-data: file, optional reference_no, issue_date, expiry_date, remarks.
     */
    public function upload(Request $request, Order $order, DocumentRequirement $requirement, StoreDocument $store): JsonResponse
    {
        abort_unless($request->user()->can('documents.upload'), 403);
        abort_unless(Order::query()->visibleTo($request->user())->whereKey($order->id)->exists(), 404);
        abort_unless($requirement->order_id === $order->id, 404);

        $request->validate(['file' => ['required', 'file']]);
        $requirement->load(['documentType', 'order.customer']);

        $document = $store->upload($request->user(), $requirement->documentType, $order->customer, $order, $request->file('file'),
            $request->only(['reference_no', 'issue_date', 'expiry_date', 'remarks']), $requirement);

        return ApiResponse::success([
            'document_id' => $document->id,
            'document_no' => $document->document_no,
            'status' => $document->status->value,
            'version' => $document->current_version,
        ], __('Document uploaded.'), status: 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'order_date' => $order->order_date->toDateString(),
            'stage' => ['code' => $order->stage->code, 'name' => $order->stage->name],
            'customer' => ['id' => $order->customer->id, 'customer_no' => $order->customer->customer_no, 'name' => $order->customer->name, 'mobile' => $order->customer->mobile],
            'order_value' => $order->order_value,
            'finance_required' => $order->finance_required,
            'customer_contribution' => $order->customer_contribution,
            'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
            'cancelled' => $order->isCancelled(),
        ];
    }
}
