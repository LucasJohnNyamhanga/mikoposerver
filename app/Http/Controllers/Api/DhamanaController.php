<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DhamanaRequest;
use App\Models\Customer;
use App\Models\Dhamana;
use App\Models\Loan;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\UserOfisi;
use App\Services\OfisiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule as ValidationRule;

class DhamanaController extends Controller
{
    public function sajiliDhamana(DhamanaRequest $request, OfisiService $ofisiService)
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) return $ofisi;

        $ofisiId = $ofisi->id;

        try {
            DB::beginTransaction();

            $user = Auth::user();
            if (!$user) throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada " . env('APP_HELP'));

            $loan = $request->loanId ? Loan::find($request->loanId) : null;
            $mteja = $request->customerId ? Customer::find($request->customerId) : null;

            $dhamana = Dhamana::create([
                'jina' => $request->jina,
                'thamani' => $request->thamani,
                'maelezo' => $request->maelezo,
                'picha' => $request->picha,
                'loan_id' => $loan?->id,
                'customer_id' => $mteja?->id,
                'ofisi_id' => $ofisiId,
                'is_ofisi_owned' => $request->dhamanaIlipo === 'ofisi' && $mteja === null,
                'is_sold' => false,
                'stored_at' => $request->dhamanaIlipo,
            ]);

            $appName = env('APP_NAME');
            $helpNumber = env('APP_HELP');
            $mkopoAmount = $loan ? " kwa ajili ya mkopo wa Tsh {$loan->amount}" : "";

            if ($mteja) {
                $this->sendNotification(
                    "Hongera, dhamana {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} ya mteja {$mteja->jina} imesajiliwa kikamilifu{$mkopoAmount}, msaada piga simu {$helpNumber}.",
                    $user->id, null, $ofisiId
                );
                $this->sendNotificationKwaViongoziWengine(
                    "Dhamana mpya ya {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} ya mteja {$mteja->jina} imesajiliwa kikamilifu{$mkopoAmount}. Asante kwa kutumia {$appName}, msaada piga simu {$helpNumber}.",
                    $ofisiId, $user->id
                );
            } else {
                $this->sendNotification(
                    "Hongera, dhamana {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} imesajiliwa kikamilifu, msaada piga simu {$helpNumber}.",
                    $user->id, null, $ofisiId
                );
                $this->sendNotificationKwaViongoziWengine(
                    "Dhamana mpya ya {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} imesajiliwa kikamilifu. Asante kwa kutumia {$appName}, msaada piga simu {$helpNumber}.",
                    $ofisiId, $user->id
                );
            }

            DB::commit();
            return response()->json(['message' => 'Dhamana imesajiliwa kikamilifu'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->picha) {
                $filePath = str_replace(env('APP_URL') . '/storage/', '', $request->picha);
                Storage::disk(config('filesystems.default'))->delete($filePath);
            }
            return response()->json([
                'message' => 'Hitilafu imetokea wakati wa kusajili dhamana.',
                'error' => $e->getMessage()
            ], 500);
        }

    }


    public function ondoaDhamana(DhamanaRequest $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'dhamanaId' => 'required|exists:dhamanas,id',
            ]);

            if ($validator->fails()) throw new \Exception("Jaza maeneo yote yaliyowazi kuendelea.");

            $user = Auth::user();
            if (!$user) throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada " . env('APP_HELP'));

            $dhamana = Dhamana::find($request->dhamanaId);
            if (!$dhamana) throw new \Exception("Dhamana haipo au tayari imeshafutwa");

            $mteja = $dhamana->customer;

            // Notifications
            $messageUser = $mteja
                ? "Hongera, dhamana {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} ya mteja {$mteja->jina} imefutwa kikamilifu."
                : "Hongera, dhamana {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} imefutwa kikamilifu.";

            $this->sendNotification($messageUser, $user->id, null, $dhamana->ofisi_id);

            $messageLeaders = $mteja
                ? "Dhamana {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} ya mteja {$mteja->jina} imefutwa kikamilifu."
                : "Dhamana {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} imefutwa kikamilifu.";

            $this->sendNotificationKwaViongoziWengine($messageLeaders, $dhamana->ofisi_id, $user->id);

            // Delete file (local or S3)
            $imagePath = $dhamana->picha;
            $filePath = trim(str_replace(env('APP_URL'), '', $imagePath));

            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            $dhamana->delete();
            DB::commit();

            return response()->json(['message' => 'Dhamana imefutwa kikamilifu'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Hitilafu: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getAllDhamana(Request $request, OfisiService $ofisiService)
    {
        try {
            $ofisi = $ofisiService->getAuthenticatedOfisiUser();
            if ($ofisi instanceof JsonResponse) {
                return $ofisi;
            }

            $ofisiId = $ofisi->id;

            // Sanitize inputs
            $filter = $request->query('filter'); // "dhamana", "mali", au null
            $perPage = $request->query('per_page', 10); // default 10 kwa page
            $page = $request->query('page', 1);

            $query = Dhamana::query();

            if ($ofisiId) {
                $query->where([
                    'ofisi_id' => $ofisiId,
                    'is_active' => true,
                ]);
            }else{
                return response()->json([
                    'success' => false,
                    'message' => 'Ofisi yako haijapatikana'
                ], 500);
            }

            // Apply filters
            if ($filter === 'dhamana') {
                // Mali zilizo dhamana za wateja
                $query->where('is_ofisi_owned', false);
            } elseif ($filter === 'mali') {
                // Mali za ofisi tu zisizo na wateja
                $query->where('is_ofisi_owned', true);
            }

            // Load relations and paginate
            $dhamanas = $query->with([
                'customer',
                'loan' => function ($q) {
                    $q->with('customers')->latest();
                },
            ])
                ->latest()
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'dhamana' => $dhamanas
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hitilafu: ' . $e->getMessage()
            ], 500);
        }
    }


    private function sendNotificationUongozi($messageContent, $ofisiId)
    {
        $ofisi = Ofisi::find($ofisiId);
        $groups = $ofisi->positionsWithUsers()->get();
        foreach ($groups as $group) {
            $users = $group['users'];
            $senderId = $group['users'][0]['id'];
            foreach ($users as $user) {
                $this->sendNotification($messageContent, $user['id'], $senderId, $ofisiId);
            }
        }
    }

    private function sendNotificationKwaViongoziWengine($messageContent, $ofisiId, $userId)
    {
        $ofisi = Ofisi::find($ofisiId);
        $groups = $ofisi->positionsWithUsers()->get();
        foreach ($groups as $group) {
            $users = $group['users'];
            $senderId = $group['users'][0]['id'];
            foreach ($users as $user) {
                if ($userId != $user->id) {
                    $this->sendNotification($messageContent, $user['id'], $senderId, $ofisiId);
                }
            }
        }
    }

    private function sendNotification($messageContent, $receiverId, $senderId, $ofisiId)
    {
        Message::create([
            'message' => $messageContent,
            'receiver_id' => $receiverId,
            'sender_id' => $senderId,
            'ofisi_id' => $ofisiId,
        ]);
    }
}
