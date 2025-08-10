<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KifurushiPurchase;
use App\Models\User;
use App\Models\VerifiedAccount as VerifiedAccountModel;
use App\Services\OfisiService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use LaravelIdea\Helper\App\Models\_IH_User_C;

class VerifiedAccount extends Controller
{


    public function addVerifiedOfisi(Request $request, OfisiService $ofisiService): JsonResponse
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        $user = Auth::user();
        $cheoModel = $user->getCheoKwaOfisi($ofisi->id);

        if (!$cheoModel) {
            return response()->json([
                'message' => 'Wewe sio kiongozi wa ofisi, huna uwezo wa kuongeza ofisi.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'ofisiId' => 'required|integer|exists:ofisis,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za kutafutia ofisi hazijawasilishwa',
                'errors'  => $validator->errors()
            ], 400);
        }

        $ofisiTargetId = $request->input('ofisiId');

        // Wrap the whole process in a transaction
        return DB::transaction(function () use ($user, $ofisiTargetId) {
            // Check if already verified
            $alreadyVerified = VerifiedAccountModel::where('user_id', $user->id)
                ->where('ofisi_id', $ofisiTargetId)
                ->exists();

            if ($alreadyVerified) {
                return response()->json([
                    'message' => 'Ofisi hii tayari imeshaongezwa, jaribu nyingine.'
                ], 409);
            }

            // Get all user IDs in this ofisi
            $userIdsInOfisi = DB::table('user_ofisis')
                ->where('ofisi_id', $ofisiTargetId)
                ->pluck('user_id');

            // Find an active kifurushi purchase from any user in this office
            $activePurchase = KifurushiPurchase::whereIn('user_id', $userIdsInOfisi)
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$activePurchase) {
                return response()->json([
                    'message' => 'Hakuna kifurushi hai katika ofisi hii, nunua kifurushi kuendelea.'
                ], 400);
            }

            // Create verified account for the owner of the kifurushi
            VerifiedAccountModel::create([
                'user_id'              => $activePurchase->user_id,
                'kifurushi_id'         => $activePurchase->kifurushi_id,
                'ofisi_id'             => $ofisiTargetId,
                'ofisi_changes_count'  => 0,
                'ofisi_creation_count' => 0,
            ]);

            return response()->json([
                'message' => 'Ofisi imeongezwa kikamilifu.'
            ], 201);
        });
    }

    public function getValidOfisi(OfisiService $ofisiService): JsonResponse
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();

        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }


        $userId = auth()->user()->id;

        // Step 1: Get user IDs with active kifurushi
        $activeUserIds = KifurushiPurchase::where('status', 'active')
            ->pluck('user_id')
            ->unique();

        // Step 2: Load the user with only offices that
        // have verified accounts AND
        // are linked to users with active kifurushi

        $user = User::with(['ofisis' => function ($query) use ($activeUserIds) {
            $query->select('ofisis.*')
                ->whereHas('verifiedAccounts')
                ->whereHas('users', function ($q) use ($activeUserIds) {
                    $q->whereIn('users.id', $activeUserIds);
                })
                ->withCount('customers')
                ->withCount(['acceptedUsers as users_count'])
                ->withCount(['loans as active_loans_count' => function ($q) {
                    $q->whereIn('status', ['approved', 'defaulted']);
                }])
                ->withSum(['loans as total_amount_loaned' => function ($q) {
                    $q->whereIn('status', ['approved', 'defaulted']);
                }], 'amount')
                ->withSum(['loans as amount_defaulted' => function ($q) {
                    $q->where('status', 'defaulted');
                }], 'amount');
        }])->findOrFail($userId);

        return $this->ofisiSummaryResults($user);
    }

    /**
     * @param Model|Collection|array|User|_IH_User_C|null $user
     * @return JsonResponse
     */
    public function ofisiSummaryResults(Model|Collection|array|User|_IH_User_C|null $user): JsonResponse
    {
        $ofisis = $user->ofisis->map(function ($ofisi) {
            return [
                'id' => $ofisi->id,
                'jina' => $ofisi->jina,
                'mkoa' => $ofisi->mkoa,
                'wilaya' => $ofisi->wilaya,
                'kata' => $ofisi->kata,
                'customers_count' => $ofisi->customers_count,
                'users_count' => $ofisi->users_count,
                'active_loans_count' => $ofisi->active_loans_count,
                'total_amount_loaned' => (float)$ofisi->total_amount_loaned,
                'amount_defaulted' => (float)$ofisi->amount_defaulted,
                'position_id' => $ofisi->pivot?->position_id,
            ];
        });

        return response()->json($ofisis);
    }
}
