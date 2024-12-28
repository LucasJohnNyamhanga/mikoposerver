<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Handle image upload without access token.
     *
     * @param UploadRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(UploadRequest $request)
    {
        try {
            $image = $request->file('file');
            
            // Validate file type and size
            $validator = Validator::make($request->all(), [
                'file' => 'image|mimes:jpeg,png,jpg|max:2048' // 2MB Max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Picha inatakiwa kuwa aina ya jpeg, png, jpg na size isizidi 2mb',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Set the upload path
            $imagePath = public_path('uploads/kikundi/images/');
            
            // Generate unique file name
            $new_name = Str::random(10) . '_' . time() . '.' . $image->getClientOriginalExtension();

            // Move the file
            $image->move($imagePath, $new_name);

            // Return success response
            return response()->json([
                'message' => 'http://10.0.2.2:8000/uploads/kikundi/images/' . $new_name,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
