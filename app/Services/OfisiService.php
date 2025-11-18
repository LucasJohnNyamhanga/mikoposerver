<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use App\Models\Ofisi;
use Illuminate\Support\Facades\Auth;

class OfisiService
{
    /**
     * Get the authenticated user's active ofisi.
     *
     * @return Ofisi|JsonResponse
     */
    public function getAuthenticatedOfisiUser(): Ofisi|JsonResponse
    {
        $helpNumber = config('services.help.number');

        // 1️⃣ Check authentication
        if (!auth()->check()) {
            return response()->json([
                'message' => "Kuna tatizo. Hatukupata kwenye database. Piga msaada: {$helpNumber}"
            ], 401);
        }

        $user = auth()->user()->load('activeOfisi.ofisi');

        // 2️⃣ Check if user has active office
        if (!$user->activeOfisi) {
            return response()->json(['message' => 'Huna Ofisi'], 401);
        }

        $ofisi = $user->activeOfisi->ofisi;

        // 3️⃣ Validate office record exists
        if (!$ofisi) {
            return response()->json([
                'message' => "Ofisi haijapatikana. Piga msaada: {$helpNumber}"
            ], 404);
        }

        // 4️⃣ Update timestamps
        $user->update(['last_login_at' => now()]);//not updating last login time
        $ofisi->update(['last_seen' => now()]);

        // 5️⃣ Return actual ofisi model
        return $ofisi;
    }
}
