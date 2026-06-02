<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Build a success response.
     *
     * @param  mixed  $data
     * @param  array  $meta
     * @param  int  $code
     * @return JsonResponse
     */
    public function successResponse($data, $meta = [], $code = 200): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            $meta = array_merge([
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ], $meta);

            $data = $data->items();
        }

        return response()->json(array_filter([
            'status' => 'success',
            'data' => $data,
            'meta' => empty($meta) ? null : $meta,
        ]), $code);
    }

    /**
     * Build an error response.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  array|null  $errors
     * @return JsonResponse
     */
    public function errorResponse($message, $code = 400, $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ]), $code);
    }
}
