<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Models\Message;
use App\Models\Ofisi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function ondoaUnreadMeseji(MessageRequest $request)
    {
        $validated = $request->validate([
            'ofisiId' => 'required|integer',
        ]);

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Kuna tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu 0784477999 kwa msaada.'
            ], 401);
        }

        $ofisi = Ofisi::find($validated['ofisiId']);

        if (!$ofisi) {
            return response()->json([
                'message' => 'Ofisi yako haijapatikana. Tafadhali piga 0784477999 kutatua hitilafu.'
            ], 404);
        }

        try {
            Message::where([
                    ['receiver_id', '=', $user->id],
                    ['ofisi_id', '=', $ofisi->id],
                    ['status', '=', 'unread'],
                ])
                ->update(['status' => 'read']);

            return response()->json([
                'message' =>  "Ujumbe umewekwa kuwa zimesoma."
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => "Tatizo ni: " . $e->getMessage()
            ], 500);
        }
    }
}
