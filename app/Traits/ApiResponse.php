<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     */
    protected function successResponse(mixed $data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return a standardized error JSON response.
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, mixed $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Return a standardized paginated resource JSON response.
     */
    protected function paginatedResponse(ResourceCollection $collection, string $message = 'Success', int $code = 200): JsonResponse
    {
        $response = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $response['data'] ?? [],
            'meta' => $response['meta'] ?? [],
            'links' => $response['links'] ?? [],
        ], $code);
    }
}
