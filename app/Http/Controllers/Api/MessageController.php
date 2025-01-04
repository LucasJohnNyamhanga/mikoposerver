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
        // Validate input fields
        $validator = Validator::make($request->all(), [
            'ofisiId' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Jaza nafasi zote zilizo wazi.',
                'errors' => $validator->errors()
            ], 400);
        }

        $ofisiId = $request->input('ofisiId');

        // Check if Kikundi exists
        $ofisi = Ofisi::find($ofisiId);
        if (!$ofisi) {
            return response()->json([
                'message' => 'Ofisi yako hakijapatikana, tafadhari piga 0784477999 kutatua hitilafu.'
            ], 404);
        }

        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu 0784477999 kwa msaada'], 401);
        }

        // Update unread messages to read in bulk for performance
        $updatedCount = Message::where('receiver_id', $user->id)
            ->where('ofisi_id', $ofisi->id)
            ->where('status', 'unread')
            ->update(['status' => 'read']);

        if ($updatedCount > 0) {
            return response()->json([
                'message' => "Message ($updatedCount) zimebadilishwa status kuwa zimesoma."
            ], 200);
        }

        return response()->json(['message' => 'Hakuna message za kubadili.'], 200);
    }
}
