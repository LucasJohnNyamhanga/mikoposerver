<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoanRequest;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanCustomer;
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
            'amount' => 'required|numeric|min:0',
            'riba' => 'required|numeric|min:0|max:100',
            'fomu' => 'required|numeric|min:0|max:100',
            'totalDue' => 'required|numeric|min:0',
            'kipindiMalipo' => 'required|in:siku,wiki,mwezi,mwaka',
            'mudaMalipo' => 'nullable|integer|min:1',
            'userId' => 'required|exists:users,id',
            'ofisiId' => 'required|exists:ofisis,id',
            'loanType' => 'required|in:kikundi,binafsi',
            'wateja' => 'nullable|array',
            'wateja.*' => 'exists:customers,id',
            'wadhamini' => 'nullable|array',
            'wadhamini.*' => 'exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $ofisi = Ofisi::find($request->ofisiId);
            $user = Auth::user();

            // Check if any of the customers already have active loans
            $loanExisting = Loan::whereIn('user_id', $request->wateja)
                ->whereIn('status', ['pending', 'approved', 'defaulted', 'error'])
                ->exists();

            if ($loanExisting) {
                return response()->json([
                    'message' => 'Mmoja au zaidi ya wateja tayari wana mkopo unaoendelea au ombi lililopo.'
                ], 401);
            }

            // Create a loan
            $loan = Loan::create([
                'amount' => $request->amount,
                'riba' => $request->riba,
                'fomu' => $request->fomu,
                'total_due' => $request->totalDue,
                'kipindi_malipo' => $request->kipindiMalipo,
                'muda_malipo' => $request->mudaMalipo,
                'loan_type' => $request->loanType,
                'user_id' => $request->userId,
                'ofisi_id' => $request->ofisiId,
                'status' => 'pending',
            ]);

            // Attach wateja
            foreach ($request->wateja as $mkopajiId) {
                LoanCustomer::create([
                    'loan_id' => $loan->id,
                    'customer_id' => $mkopajiId,
                ]);
            }

            // Attach guarantors
            foreach ($request->wadhamini as $guarantorId) {
                Mdhamini::create([
                    'loan_id' => $loan->id,
                    'customer_id' => $guarantorId,
                ]);
            }

            $customers = Customer::whereIn('id', $request->wateja)->pluck('jina');


            if ($customers->isEmpty()) {
                $names = 'Mteja';
            } else {
                // Format names into a string
                if ($customers->count() == 1) {
                    $names = $customers->first();
                } elseif ($customers->count() == 2) {
                    $names = $customers->join(' and ');
                } else {
                    $lastCustomer = $customers->pop(); // Get the last customer name
                    $names = $customers->implode(', ') . ' pamoja na ' . $lastCustomer;
                }
            }

            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => Auth::id(),
                'action' => 'created',
                'description' => "Mkopo wa kiasi cha Tsh {$loan->amount} umefunguliwa na {$user->jina_kamili} mwenye simu namba {$user->mobile}. Ombi la mkopo limewasilishwa na {$names}.",
            ]);

            $this->sendNotification(
                    "Hongera, mkopo wa {$names} umefunguliwa, boresha taarifa zaidi za mkopo huu kwenye Mikopo mipya ya {$request->loanType} kwenye eneo la wateja, Asante kwa kutumia mfumo wa mikopo center.",
                    $user->id,
                    null,
                    $ofisi->id,
                );

            $this->sendNotificationKwaViongoziWengine("Mkopo mpya wa kiasi cha Tsh {$loan->amount} umefunguliwa na mtumishi {$user->jina_kamili} mwenye simu namba {$user->mobile}, wateja wa mkopo huo ni {$names}. Asante kwa kutumia mfumo wa mikopo center.", $ofisi->id, $user->id);

            DB::commit();
            return response()->json([
                'message' => 'Ombi la mkopo limewasilishwa kikamilifu.',
                'loan' => $loan
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ombi la mkopo limeshindikana kuwasilishwa.',
                'message' => $e->getMessage()
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
            $senderId = $userId;
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
