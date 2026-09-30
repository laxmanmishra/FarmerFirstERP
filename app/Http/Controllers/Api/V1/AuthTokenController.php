<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Services\AuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthTokenController extends Controller
{
    public function __construct(private readonly AuthenticationService $authentication) {}

    public function store(IssueTokenRequest $request): JsonResponse
    {
        $user = $this->authentication->attempt($request->string('email'), $request->string('password'), $request, 'api');

        $token = $user->createToken(
            $request->string('device_name'),
            ['*'],
            now()->addMinutes(config('erp.security.api_token_expiry_minutes')),
        );

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'must_change_password' => $user->must_change_password,
        ], 'Authenticated.', status: 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authentication->recordLogout($request->user(), $request, 'api');
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(message: 'Logged out.');
    }
}
