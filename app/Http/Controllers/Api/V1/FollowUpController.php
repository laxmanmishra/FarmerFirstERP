<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FollowUps\CompleteFollowUp;
use App\Http\Controllers\Controller;
use App\Http\Resources\FollowUpResource;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('follow_ups.view'), 403);

        $query = FollowUp::query()->visibleTo($request->user())
            ->with(['assignee', 'followable' => fn ($morph) => $morph->morphWith([Enquiry::class => ['farmer']])]);

        match ($request->query('filter')['status'] ?? 'today') {
            'overdue' => $query->overdue(),
            'upcoming' => $query->upcoming(),
            'all_pending' => $query->pending(),
            default => $query->pending()->where('due_at', '<=', now()->endOfDay()),
        };

        return ApiResponse::success(FollowUpResource::collection($query->orderBy('due_at')->paginate(min((int) $request->query('per_page', 25), 100))));
    }

    public function complete(Request $request, FollowUp $followUp, CompleteFollowUp $complete): JsonResponse
    {
        abort_unless($request->user()->can('follow_ups.manage'), 403);
        abort_unless(FollowUp::query()->visibleTo($request->user())->whereKey($followUp->id)->exists(), 404);

        $validated = $request->validate(['outcome' => ['required', 'string', 'min:3', 'max:1000']]);

        $complete->handle($request->user(), $followUp, $validated['outcome']);

        return ApiResponse::success(FollowUpResource::make($followUp->fresh(['assignee', 'followable'])), 'Follow-up completed.');
    }
}
