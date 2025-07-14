<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VifurushiRequest;
use App\Models\Kifurushi;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;


class VifurushiController extends Controller
{
    public function getVifurushi(): JsonResponse
    {
        try {
            $vifurushi = Kifurushi::where('is_active', true)->get();

            return response()->json([
                'status' => 'success',
                'data' => $vifurushi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tatizo limetokea wakati wa kuchukua vifurushi. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function paymentStatus(VifurushiRequest $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Taarifa za reference hazijawasilishwa ipasavyo.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $payment = Payment::where('reference', $request->reference)->first();

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Malipo haya hayajapatikana.',
                ], 404);
            }

            if ($payment->status === 'completed') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Malipo haya yamekamilika na kuthibitishwa.',
                ], 200);
            }

            return response()->json([
                'status' => 'pending',
                'message' => 'Malipo hayajakamilika. Pesa bado haijapokelewa.',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hitilafu imetokea: ' . $e->getMessage(),
            ], 500);
        }
    }

}
