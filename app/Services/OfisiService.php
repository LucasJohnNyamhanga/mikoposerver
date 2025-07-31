<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Models\UserOfisi;
use App\Models\Ofisi;

class OfisiService
{
    /**
     * Get the authenticated user's active ofisi.
     *
     * @return Ofisi|JsonResponse
     */
    public function getAuthenticatedOfisiUser(): Ofisi|JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada 0784477999'
            ], 401);
        }

        if (!$user->activeOfisi) {
            return response()->json([
                'message' => 'Huna usajili kwenye ofisi yeyote. Piga simu msaada 0784477999'
            ], 401);
        }

        $userOfisi = UserOfisi::where('user_id', $user->id)
            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
            ->first();

        if (!$userOfisi || !$userOfisi->ofisi) {
            return response()->json([
                'message' => 'Ofisi haijapatikana. Piga simu msaada 0784477999'
            ], 404);
        }

        return $userOfisi->ofisi;
    }
}
