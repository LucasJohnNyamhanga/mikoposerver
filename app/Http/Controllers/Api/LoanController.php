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
use App\Models\UserOfisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoanController extends Controller
{
    public function sajiliMkopo(LoanRequest $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'jinaKikundi' => 'nullable|string|max:255',
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
                if ($request->loanType == 'kikundi') {
                    return response()->json([
                        'message' => 'Mteja mmoja au zaidi tayari ana ombi la mkopo au ana mkopo unaoendelea.'
                    ], 401);
                }else{
                    return response()->json([
                        'message' => 'Mteja tayari ana ombi la mkopo au ana mkopo unaoendelea.'
                    ], 401);
                }
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
                'jina_kikundi' => $request->jinaKikundi,
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
                    $names = $customers->join(' pamoja na ');
                } else {
                    $lastCustomer = $customers->pop(); // Get the last customer name
                    $names = $customers->implode(', ') . ' pamoja na ' . $lastCustomer;
                }
            }

            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => Auth::id(),
                'action' => 'created',
                'description' => "Mkopo wa kiasi cha Tsh {$loan->amount} umefunguliwa na afisa {$user->jina_kamili} mwenye simu namba {$user->mobile}. Ombi la mkopo limewasilishwa na {$names}.",
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

    public function ombaPitishaMkopo(LoanRequest $request)
    {   
         // Validate input
        $validator = Validator::make($request->all(), [
            'jinaKikundi' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'riba' => 'required|numeric|min:0|max:100',
            'fomu' => 'required|numeric|min:0|max:100',
            'totalDue' => 'required|numeric|min:0',
            'kipindiMalipo' => 'required|in:siku,wiki,mwezi,mwaka',
            'mudaMalipo' => 'nullable|integer|min:1',
            'userId' => 'required|exists:users,id',
            'ofisiId' => 'required|exists:ofisis,id',
            'loanId' => 'required|exists:loans,id',
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
            $user = Auth::user();
            $helpNumber = env('APP_HELP');

            if (!$user) {
                throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
            }

            if (!$user->activeOfisi) {
                throw new \Exception("Kuna Tatizo. Huna usajili kwenye ofisi yeyote. Piga simu msaada {$helpNumber}");
            }

            // Retrieve the KikundiUser record to get the position and the Kikundi details
            $userOfisi = UserOfisi::where('user_id', $user->id)
                                ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                                ->first();

            $ofisi = $userOfisi->ofisi;

            $loan = Loan::findOrFail($request->loanId);

            $loan->update([
                'amount' => $request->amount,
                'riba' => $request->riba,
                'fomu' => $request->fomu,
                'total_due' => $request->totalDue,
                'kipindi_malipo' => $request->kipindiMalipo,
                'muda_malipo' => $request->mudaMalipo,
                'loan_type' => $request->loanType,
                'user_id' => $request->userId,
                'ofisi_id' => $request->ofisiId,
                'jina_kikundi' => $request->jinaKikundi,
                'status' => 'waiting',
            ]);

            // Handle Loan Customers
            if ($request->has('wateja')) {
                LoanCustomer::where('loan_id', $loan->id)->delete();
                $loanCustomers = array_map(fn($id) => ['loan_id' => $loan->id, 'customer_id' => $id], $request->wateja);
                LoanCustomer::insert($loanCustomers);
            }

            // Handle Guarantors
            if ($request->has('wadhamini')) {
                Mdhamini::where('loan_id', $loan->id)->delete();
                $guarantors = array_map(fn($id) => ['loan_id' => $loan->id, 'customer_id' => $id], $request->wadhamini);
                Mdhamini::insert($guarantors);
            }

            // Format customer names
            $customers = Customer::whereIn('id', $request->wateja)->pluck('jina');
            $names = $customers->join(', ', ' pamoja na ');

            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => Auth::id(),
                'action' => 'updated',
                'description' => "Ombi la mkopo wa kiasi cha Tsh {$loan->amount} linasubili uhakiki na kupitishwa. Afisa mwasilishi ni {$user->jina_kamili}. Majina ya wakopaji ni {$names}.",
            ]);

            $this->sendNotification(
                "Ombi la mkopo wa Tsh {$loan->amount} wa {$names} limewasilishwa na linasubili uhakiki na kupitishwa.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Ombi la mkopo la kiasi cha Tsh {$loan->amount} la {$names} linasubilia uhakiki na kupitishwa. Limewasilishwa na afisa {$user->jina_kamili} mwenye namba {$user->mobile}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();
            return response()->json(['message' => 'Ombi la mkopo limewasilishwa kikamilifu.', 'loan' => $loan], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Ombi la mkopo limeshindikana kuwasilishwa.', 'message' => $e->getMessage()], 500);
        }
    }


    public function getMikopoMipya(LoanRequest $request)
    {

        $user = Auth::user();

        if (!$user) {
            // Return error if user is not found
            return response()->json(['message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada 0784477999'], 401);
        }

        // Check if the user has an active Kikundi
        if ($user->activeOfisi) {
            // Retrieve the KikundiUser record to get the position and the Kikundi details
            $userOfisi = UserOfisi::where('user_id', $user->id)
                                ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                                ->first();

            $ofisi = $userOfisi->ofisi;
            

            $mikopoOfisi = Ofisi::with(['loans' => function ($query) {
                            $query->with(['customers','user','transactions'=> function ($query) {
                                $query->with([
                                    'user','approver','creator','customer'
                                ])->latest();
                            }
                            ,'wadhamini','dhamana','mabadiliko'=> function ($query) {
                                $query->latest();
                            }
                            ])->whereIn('status', ['pending'])->latest();
                        }])->where('id', $ofisi->id)
                        ->first();
            
            return response()->json([
            'kikundi' => $mikopoOfisi,
            ], 200);
        }

        return response()->json(['message' => 'Huna usajili kwenye kikundi chochote. Piga simu msaada 0784477999'], 401);
    }

    public function getMikopoPitisha(LoanRequest $request)
    {

        $user = Auth::user();

        if (!$user) {
            // Return error if user is not found
            return response()->json(['message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada 0784477999'], 401);
        }

        // Check if the user has an active Kikundi
        if ($user->activeOfisi) {
            // Retrieve the KikundiUser record to get the position and the Kikundi details
            $userOfisi = UserOfisi::where('user_id', $user->id)
                                ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                                ->first();

            $ofisi = $userOfisi->ofisi;
            

            $mikopoOfisi = Ofisi::with(['loans' => function ($query) {
                            $query->with(['customers','user','transactions'=> function ($query) {
                                $query->with([
                                    'user','approver','creator','customer'
                                ])->latest();
                            }
                            ,'wadhamini','dhamana','mabadiliko'=> function ($query) {
                                $query->latest();
                            }
                            ])->whereIn('status', ['waiting'])->latest();
                        }])->where('id', $ofisi->id)
                        ->first();
            
            return response()->json([
            'kikundi' => $mikopoOfisi,
            ], 200);
        }

        return response()->json(['message' => 'Huna usajili kwenye kikundi chochote. Piga simu msaada 0784477999'], 401);
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
