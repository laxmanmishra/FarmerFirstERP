<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load(['employee.departments', 'employee.designation']);

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'must_change_password' => $user->must_change_password,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->isSuperAdmin() ? ['*'] : $user->getAllPermissions()->pluck('name')->sort()->values(),
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'employee_code' => $user->employee->employee_code,
                'designation' => $user->employee->designation?->name,
                'departments' => $user->employee->departments->pluck('name'),
            ] : null,
            'branches' => BranchResource::collection($user->accessibleBranches())->resolve($request),
            'current_branch_id' => $user->current_branch_id,
        ]);
    }
}
