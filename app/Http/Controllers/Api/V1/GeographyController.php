<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Tehsil;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cascading territory lookups for web and mobile forms.
 */
class GeographyController extends Controller
{
    public function districts(Request $request): JsonResponse
    {
        $districts = District::query()->active()
            ->when($request->integer('filter.state_id'), fn ($query, int $stateId) => $query->where('state_id', $stateId))
            ->orderBy('name')
            ->get(['id', 'state_id', 'code', 'name']);

        return ApiResponse::success($districts);
    }

    public function tehsils(District $district): JsonResponse
    {
        return ApiResponse::success($district->tehsils()->active()->orderBy('name')->get(['id', 'district_id', 'code', 'name']));
    }

    public function villages(Tehsil $tehsil): JsonResponse
    {
        return ApiResponse::success($tehsil->villages()->active()->orderBy('name')->get(['id', 'tehsil_id', 'code', 'name', 'pin_code']));
    }
}
