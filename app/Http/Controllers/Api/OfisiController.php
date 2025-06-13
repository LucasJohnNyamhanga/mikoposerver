<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfisiRequest;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Mabadiliko;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\Position;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use App\Models\UserOfisi;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class OfisiController extends Controller
{
    public function getOfisiByLocation(OfisiRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'mkoa' => 'required|string',
            'wilaya' => 'required|string',
            'kata' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Taarifa za kutafutia ofisi hazijawasilishwa', 'errors' => $validator->errors()], 400);
        }

        $mkoa = $request->input('mkoa');
        $wilaya = $request->input('wilaya');
        $kata = $request->input('kata');

        $ofisi = Ofisi::where('mkoa',$mkoa)->where('wilaya',$wilaya)->where('kata',$kata)->get();

        return response()->json(['ofisi' => $ofisi], 200);
    }

    public function getOfisiData(OfisiRequest $request)
    {
        $user = Auth::user();

        if (!$user) {
            // Return error if user is not found
            return response()->json(['message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada 0784477999'], 401);
        }

        // Check if the user has an active ofisi
        if (!$user->activeOfisi) {
            return response()->json(['message' => 'Huna usajili kwenye ofisi yeyote. Piga simu msaada 0784477999'], 401);
        }
        // Retrieve the KikundiUser record to get the position and the Kikundi details
        $userOfisi = UserOfisi::where('user_id', $user->id)
                            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                            ->first();

        $ofisi = $userOfisi->ofisi;

        $this->updateLoanStatuses($ofisi->id); //not being caled! why?
        
        $ofisiYangu = Ofisi::with(['users'=> function ($query) {
                $query->with(['receivedMessages' => function ($query) {
                            $query->with(['sender', 'receiver'])
                            ->where('receiver_id', Auth::id())
                                ->latest();
                        }
                    ])->latest();
                },'customers'=> function ($query) {
                            $query->latest();
                        }
                        ,'loans' => function ($query) {
                        $query->with(['customers','user','transactions'=> function ($query) {
                            $query->with([
                                'user','approver','creator','customer'
                            ])->latest();
                        }
                        ,'wadhamini','dhamana','mabadiliko'=> function ($query) {
                            $query->latest();
                        }
                        ])->whereIn('status', ['approved','defaulted',])->latest();
                    },'transactions'=> function ($query) {
                            $query->with([
                                'user','approver','creator','customer'
                            ])->whereDate('created_at', now()->toDateString()) ->latest();//get only today's transactions
                        },'ainamikopo'])->where('id', $ofisi->id)->first();

        return response()->json([
        'user_id' => $user->id,
        'ofisi' => $ofisiYangu,
        ], 200);
    }

    public function getUserOfisiSummary(): JsonResponse
    {
        $userId = Auth::id(); // get current user ID

        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = User::with(['ofisis' => function ($query) {
            $query->withCount('customers')
                ->withCount([
                    'loans as active_loans_count' => function ($q) {
                        $q->whereIn('status', ['approved', 'defaulted']);
                    }
                ])
                ->withSum([
                    'loans as total_amount_loaned' => function ($q) {
                        $q->whereIn('status', ['approved', 'defaulted']);
                    }
                ], 'amount')
                ->withSum([
                    'loans as amount_defaulted' => function ($q) {
                        $q->where('status', 'defaulted');
                    }
                ], 'amount');
        }])->findOrFail($userId);

        $ofisis = $user->ofisis->map(function ($ofisi) {
            return [
                'id' => $ofisi->id,
                'jina' => $ofisi->jina,
                'mkoa' => $ofisi->mkoa,
                'wilaya' => $ofisi->wilaya,
                'kata' => $ofisi->kata,
                'customers_count' => $ofisi->customers_count,
                'active_loans_count' => $ofisi->active_loans_count,
                'total_amount_loaned' => (float) $ofisi->total_amount_loaned,
                'amount_defaulted' => (float) $ofisi->amount_defaulted,
            ];
        });

        return response()->json($ofisis);
    }


    public function badiliUshirika(OfisiRequest $request)
    {
        $user = Auth::user();
        $helpNumber = env('APP_HELP');
        $appName = env('APP_NAME');

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

        $ofisi = $user->maofisi->where('id', $ofisi->id)->first();

        $position = $ofisi->pivot->position_id;

        $positionRecord = Position::find($position);

        if (!$positionRecord) {
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kukamilisha hichi kitendo.");
        }

        $cheo = $positionRecord->name;

        // Validate input
        $validator = Validator::make($request->all(), [
            'status' => 'required|boolean',
            'userId' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za kubadili ushirika hazijawasilishwa ipasavyo',
                'errors' => $validator->errors()
            ], 400);
        }

        // Find target user's ofisi record
        $targetUserOfisi = UserOfisi::where('user_id', $request->userId)
                                    ->where('ofisi_id', $ofisi->id)
                                    ->first();

        if (!$targetUserOfisi) {
            return response()->json([
                'message' => 'Mtumiaji huyu hajajiunga na ofisi yako.',
            ], 404);
        }

        $targetUserOfisi->status = $request->status ? 'accepted' : 'denied';
        $targetUserOfisi->save();

        return response()->json([
            'message' => 'Mabadiliko ya ushirika yamefanikiwa',
        ], 200);
    }


    private function updateLoanStatuses($ofisiId)
    {
        $now = Carbon::now();

        // Fetch loans where due_date has passed and status is not 'defaulted', 'repaid', or 'closed'
        $loansToUpdate = Loan::where('ofisi_id', $ofisiId)
            ->where('due_date', '<', $now)
            ->where('status', 'approved')
            ->get();

        foreach ($loansToUpdate as $loan) {
            $loan->update([
                'status' => 'defaulted',
                'status_details' => 'Mkopo huu upo nje ya muda wake wa kimkataba, umemaliza siku rasmi zilizopangwa za marejesho.',
            ]);

            $sumTransactions = Transaction::where('loan_id', $loan->id)
                ->where('type', 'kuweka')
                ->where('category', 'rejesho')
                ->sum('amount');

            // Calculate the remaining balance
            $remainingBalance = $loan->total_due - $sumTransactions;

            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => Auth::id(),
                'action' => 'created',
                'description' => "Mkopo huu umemaliza muda wake wa kimkataba, hivyo upo nje ya muda wake wa marejesho, deni lililobaki ni {$remainingBalance}.",
            ]);

            $customers = Customer::whereIn(
                'id',
                DB::table('loan_customers')
                    ->where('loan_id', $loan->id)
                    ->pluck('customer_id')
            )->pluck('jina');

            // Format customer names
            $names = $this->formatCustomerNames($customers);

            $this->sendNotificationUongozi("Mkopo wa kiasi cha Tsh {$loan->total_due} umemaliza muda wake wa kimkataba, hivyo upo nje ya muda wake wa marejesho. Deni lililobakia ni {$remainingBalance} na wateja wanaohusika na mkopo huu ni {$names}.", $ofisiId);
        }
    }

    private function formatCustomerNames($customers)
    {
        if ($customers->isEmpty()) {
            return 'Mteja';
        }

        if ($customers->count() === 1) {
            return $customers->first();
        }

        if ($customers->count() === 2) {
            return $customers->join(' pamoja na ');
        }

        $lastCustomer = $customers->pop(); // Get the last customer name
        return $customers->implode(', ') . ' pamoja na ' . $lastCustomer;
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
