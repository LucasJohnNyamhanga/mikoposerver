<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kifurushi;
use Illuminate\Http\Request;

class KifurushiController extends Controller
{
     public function index()
    {
        try {
            // Pata vifurushi vyote
            $vifurushi = Kifurushi::all();

            // Return JSON response
            return response()->json([
                'status' => 'success',
                'data' => $vifurushi
            ], 200);
        } catch (\Exception $e) {
            // Error handling
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch vifurushi: ' . $e->getMessage()
            ], 500);
        }
    }
}
