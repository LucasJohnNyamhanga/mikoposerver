<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kifurushi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KifurushiController extends Controller
{
     public function getVifurushi()
    {
        try {
            // Pata vifurushi vyote
            $vifurushi = Kifurushi::latest()->get();

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

    public function sajili(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'jina' => 'required|string|max:255|unique:kifurushis,name',
            'maelezo' => 'nullable|string',
            'idadiOfisi' => 'required|integer|min:1',
            'idadiSiku' => 'required|integer|min:1',
            'bei' => 'required|numeric|min:0',
            'idadiSms' => 'required|integer|min:0',
            'ofa' => 'nullable|string',
            'maarufu' => 'required|boolean',
            'spesho' => 'required|boolean',
            'ainaKifurushi' => 'required|string|in:kifurushi,sms',
            'mudaKifurushi' => 'required|string|in:siku,wiki,mwezi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $kifurushi = Kifurushi::create([
                'name' => $request->jina,
                'description' => $request->maelezo,
                'number_of_offices' => $request->idadiOfisi,
                'duration_in_days' => $request->idadiSiku,
                'price' => $request->bei,
                'sms' => $request->idadiSms,
                'offer' => $request->ofa,
                'is_popular' => $request->maarufu,
                'special' => $request->spesho,
                'type' => $request->ainaKifurushi,
                'muda' => $request->mudaKifurushi,
                'is_active' => false, // default false
            ]);

            return response()->json([
                'message' => 'Kifurushi kimesajiliwa kwa mafanikio',
                'kifurushi' => $kifurushi,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Hitilafu ya seva: ' . $e->getMessage()
            ], 500);
        }
    }

    public function zimaWashaKifurushi(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'kifurushiId' => 'required|integer|exists:kifurushis,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $id = $request->kifurushiId;

            // Fetch kifurushi
            $kifurushi = Kifurushi::find($id);

            if (!$kifurushi) {
                return response()->json([
                    'message' => 'Kifurushi hakijapatikana'
                ], 404);
            }

            // Toggle is_active
            $kifurushi->is_active = !$kifurushi->is_active;
            $kifurushi->save();

            // Custom message
            $msg = $kifurushi->is_active
                ? 'Kifurushi kimewashwa kikamilifu'
                : 'Kifurushi kimezimwa kikamilifu';

            return response()->json([
                'message' => $msg,
                'is_active' => $kifurushi->is_active
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Hitilafu ya seva: ' . $e->getMessage()
            ], 500);
        }
    }

}
