<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Uniform API envelope (docs/API_SPECIFICATION.md):
 * { success, message, data, meta, request_id } and, for errors, { type, errors }.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof AbstractPaginator) {
            $paginator = $data->resource;
            $meta['pagination'] = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => method_exists($paginator, 'total') ? $paginator->total() : null,
                'last_page' => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : null,
            ];
        }

        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
            'request_id' => request()->attributes->get('request_id'),
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $context  machine-readable details, e.g. duplicate record ids
     */
    public static function error(string $message, string $type, int $status, array $errors = [], array $context = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'type' => $type,
            'errors' => $errors === [] ? null : $errors,
            'context' => $context === [] ? null : $context,
            'request_id' => request()->attributes->get('request_id'),
        ], fn ($value) => $value !== null), $status);
    }
}
