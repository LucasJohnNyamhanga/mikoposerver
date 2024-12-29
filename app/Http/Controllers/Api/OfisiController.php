<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfisiRequest;
use App\Models\Ofisi;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OfisiController extends Controller
{
    public function getOfisiByLocation(OfisiRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'mkoa' => 'required|string',
            'wilaya' => 'required|string',
            'kata' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Taarifa za kutafutia ofisi hazijawasilishwa', 'errors' => $validator->errors()], 400);
        }

        $mkoa = $request->input('mkoa');
        $wilaya = $request->input('wilaya');
        $kata = $request->input('kata');

        $ofisi = Ofisi::where('mkoa',$mkoa)->where('wilaya',$wilaya)->where('kata',$kata)->get();

        return response()->json(['ofisi' => $ofisi], 200);
    }

    public function registerMtumishiNewOfisi(OfisiRequest $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15',
            'makazi' => 'required|string|max:255',
            'picha' => 'required|string', 
            'jinaMdhamini' => 'required|string|max:255',
            'simuMdhamini' => 'required|string|max:15',
            'jinaMtumiaji' => 'required|string|max:255|unique:mtumishis', 
            'password' => 'required|string|min:8|confirmed', 
            'jinaOfisi' => 'required|string|max:255',
            'mkoa' => 'required|string|max:255',
            'wilaya' => 'required|string|max:255',
            'kata' => 'required|string|max:255',
        ]);

        // If validation fails, return a response with error messages
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'sms' => $validator->errors()->first()
            ], 400);
        }

        // If validation passes, create the Mtumishi record
        try {
            $user = User::create([
                'jina_kamili' => $request->jinaKamili,
                'simu' => $request->simu,
                'makazi' => $request->makazi,
                'picha' => $request->picha,
                'jina_mdhamini' => $request->jinaMdhamini,
                'simu_mdhamini' => $request->simuMdhamini,
                'jina_mtumiaji' => $request->jinaMtumiaji,
                'password' => $request->password, // Encrypt password
                'jina_ofisi' => $request->jinaOfisi,
                'mkoa' => $request->mkoa,
                'wilaya' => $request->wilaya,
                'kata' => $request->kata,
            ]);

            return response()->json([
                'status' => true,
                'sms' => 'Ombi limefanikiwa',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the save process
            return response()->json([
                'status' => false,
                'sms' => 'Hitilafu ya server: ' . $e->getMessage()
            ], 500);
        }
    }

    public function validateNewRegisterRequest(OfisiRequest $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15|unique:users,mobile',
            'makazi' => 'required|string|max:255',
            'jinaMtumiaji' => 'required|string|max:255|unique:users,username',
            'jinaOfisi' => 'required|string|max:255',
            'mkoa' => 'required|string|max:255',
            'wilaya' => 'required|string|max:255',
            'kata' => 'required|string|max:255',
        ]);

        // Return validation errors if they exist
        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 400);
        }

        // Check for unique 'simu'
        if (DB::table('users')->where('mobile', $request->simu)->exists()) {
            return response()->json([
                'message' => 'Namba ya simu imeshatumika'
            ], 400);
        }

        // Check for unique 'jinaMtumiaji'
        if (DB::table('users')->where('username', $request->jinaMtumiaji)->exists()) {
            return response()->json([
                'message' => 'Jina la mtumiaji limeshatumika'
            ], 400);
        }

        // Check for unique combination of 'jinaOfisi', 'mkoa', 'wilaya', 'kata'
        if (DB::table('ofisis')
            ->where('jina', $request->jinaOfisi)
            ->where('mkoa', $request->mkoa)
            ->where('wilaya', $request->wilaya)
            ->where('kata', $request->kata)
            ->exists()) {
            return response()->json([
                'message' => 'Jina la ofisi limeshatumika katika eneo hili'
            ], 400);
        }

        // If all validations pass
        return response()->json([
            'message' => 'Mteja anafaa kusajiliwa, anza ku-upload image'
        ], 200);
    }
}
