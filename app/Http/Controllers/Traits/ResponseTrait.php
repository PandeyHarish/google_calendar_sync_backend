<?php

namespace App\Http\Controllers\Traits;

trait ResponseTrait
{
    /**
     * Send a success JSON response.
     */
    protected function successResponse($data = null, $message = 'Success', $status = 200)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Send an error JSON response.
     */
    protected function errorResponse($message = 'Error', $status = 500, $errors = null)
    {
        $response = [
            'status' => $status,
            'message' => $message,
        ];
        if ($errors) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $status);
    }
} 