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
        // Check if user is authenticated
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada 0784477999'
            ], 401);
        }

        $user = auth()->user();

        // Check if user has an activeOfisi record
        if (!$user->activeOfisi) {
            return response()->json([
                'message' => 'Huna usajili kwenye ofisi yeyote. Piga simu msaada 0784477999'
            ], 401);
        }

        // Find the UserOfisi record for user and the activeOfisi's ofisi_id
        $userOfisi = UserOfisi::where('user_id', $user->id)
            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
            ->first();

        // Validate that userOfisi and its related ofisi exist
        if (!$userOfisi || !$userOfisi->ofisi) {
            return response()->json([
                'message' => 'Ofisi haijapatikana. Piga simu msaada 0784477999'
            ], 404);
        }

        // Return the Ofisi model
        return $userOfisi->ofisi;
    }

}
