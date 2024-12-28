<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfisiRequest;
use App\Models\Ofisi;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

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
}
