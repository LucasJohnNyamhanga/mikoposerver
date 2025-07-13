<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kifurushi;
use Illuminate\Http\JsonResponse;

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
}
