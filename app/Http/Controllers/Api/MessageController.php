<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Models\Message;
use App\Models\Ofisi;
use App\Services\OfisiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function ondoaUnreadMeseji(MessageRequest $request)
    {
        $helpNumber = config('services.help.number');
        $validated = $request->validate([
            'ofisiId' => 'required|integer',
        ]);

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => "Kuna tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu {$helpNumber} kwa msaada."
            ], 401);
        }

        $ofisi = Ofisi::find($validated['ofisiId']);

        if (!$ofisi) {
            return response()->json([
                'message' => "Ofisi yako haijapatikana. Tafadhali piga {$helpNumber} kutatua hitilafu."
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
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => "Tatizo ni: " . $e->getMessage()
            ], 500);
        }
    }

    public function getMessages( OfisiService $ofisiService)
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        try {
            $messages = Message::with(['sender', 'receiver'])
                ->where('receiver_id', Auth::id())
                ->where('ofisi_id', $ofisi->id)
                ->latest()
                ->paginate(7);

            return response()->json([
                'messages' =>  $messages
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => "Tatizo ni: " . $e->getMessage()
            ], 500);
        }
    }
}
