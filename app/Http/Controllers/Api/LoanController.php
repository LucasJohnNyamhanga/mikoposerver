<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoanRequest;
use App\Models\Loan;
use App\Models\Mabadiliko;
use App\Models\Mdhamini;
use App\Models\Message;
use App\Models\Ofisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoanController extends Controller
{
    public function sajiliMkopo(LoanRequest $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'kiasi' => 'required|numeric|min:0',
            // 'interest_rate' => 'required|numeric|min:0|max:100',
            // 'kipindi_malipo' => 'required|in:siku,wiki,mwezi,mwaka',
            // 'due_date' => 'required|date|after:today',
            'wadhaminiIds' => 'required|array|min:1',
            'wadhaminiIds.*' => 'exists:users,id', // Ensure each guarantor exists
            'mwombaMkopo' => 'required|exists:users,id',
            'kikundiId' => 'required|exists:kikundis,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $kikundi = Ofisi::find($request->kikundiId);

        $mkuu = $kikundi->users()
                    ->wherePivot('position_id', 1)
                    ->first();

        $loanExisting = Loan::where('user_id', $request->mwombaMkopo)
        ->where(function ($query) {
            $query->where('status', 'pending')
                ->orWhere('status', 'approved')
                ->orWhere('status', 'defaulted')
                ->orWhere('status', 'error');
        })
        ->exists();

        if ($loanExisting) {
            return response()->json(['message' => 'Mwanakikundi tayari ameshaomba mkopo au ana mkopo unaoendelea.'], 401);
        }

        DB::beginTransaction();
        try {
            // Save loan
            $loan = Loan::create([
                'user_id' => $request->mwombaMkopo,
                'kikundi_id' => $request->kikundiId,
                'amount' => $request->kiasi,
                'status' => 'pending',
            ]);

            // Save guarantors
            foreach ($request->wadhaminiIds as $guarantorId) {
                Mdhamini::create([
                    'loan_id' => $loan->id,
                    'user_id' => $guarantorId,
                ]);
            }

            $akauntiUser = Auth::user();

            $loanDescription = $loan->user->id == $akauntiUser->id 
                ? 'yako' 
                : 'ya '.$akauntiUser->jina_kamili;

            $loanDescriptionLog = $loan->user->id == $akauntiUser->id 
            ? 'yake' 
            : 'ya '.$akauntiUser->jina_kamili;

            // Create loan log
            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => Auth::id(),
                'action' => 'created',
                'description' => "Mkopo wa kiasi cha Tsh {$loan->amount} umeombwa na {$loan->user->jina_kamili} kupitia akaunti {$loanDescriptionLog}.",
            ]);

            $this->sendNotification(
                "Umefanikiwa kutuma ombi la mkopo wa Tsh {$loan->amount} kupitia akaunti {$loanDescription}, ombi lako linashughulikiwa. Asante kwa kutumia mfumo wa kikundi.",
                $request->mwombaMkopo,
                $mkuu->id,
                $request->kikundiId
            );

            $wadhamini = Mdhamini::with('user')
                        ->where('loan_id', $loan->id)
                        ->get();
            foreach ($wadhamini as $mdhamini) {
                $this->sendNotification(
                    "Ombi lako la kumdhamini {$loan->user->jina_kamili} kuchukua mkopo wa {$loan->amount} limepokelewa. Asante kwa kutumia mfumo wa kikundi.",
                    $mdhamini->user->id,
                    $mkuu->id,
                    $kikundi->id,
                );
            }

            DB::commit();
            return response()->json(['message' => 'Ombi la mkopo limewasilishwa kikamilifu.', 'loan' => $loan], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Ombi la mkopo limeshindikana kuwasilishwa.', 'message' => $e->getMessage()], 500);
        }
    }

    private function sendNotificationUongozi($messageContent, $ofisiId)
    {
        $kikundi = Ofisi::find($ofisiId);
        $groups = $kikundi->positionsWithUsers()->get();
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
        $kikundi = Ofisi::find($ofisiId);
        $groups = $kikundi->positionsWithUsers()->get();
        foreach ($groups as $group) {
            $users = $group['users'];
            $senderId = $group['users'][0]['id'];
            foreach ($users as $user) {
                if($userId != $user->id){
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
