<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfisiRequest;
use App\Models\Ofisi;
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
}
