<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiamalaRequest;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MiamalaController extends Controller
{
    public function lipiaRejesho(MiamalaRequest $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'method' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'description' => 'required|string|max:255',
            'ofisiId' => 'required|exists:ofisis,id',
            'loanId' => 'required|exists:loans,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $ofisi = Ofisi::findOrFail($request->ofisiId); // Ensure the office exists
            $user = Auth::user();

            $appName = config('app.name'); // Use Laravel's config helper for environment variables
            $helpNumber = config('app.help'); // Custom key for help number

            // Create the transaction record
            $transaction = Transaction::create([
                'type' => $request->type,
                'category' => $request->category,
                'status' => $request->status,
                'method' => $request->method,
                'amount' => $request->amount,
                'description' => $request->description,
                'created_by' => $user->id,
                'user_id' => $user->id,
                'approved_by' => $user->id,
                'ofisi_id' => $ofisi->id,
                'loan_id' => $request->loanId,
            ]);

            // Retrieve the loan and associated customers
            $loan = Loan::findOrFail($request->loanId);
            $customers = Customer::whereIn(
                'id',
                DB::table('loan_customers')
                    ->where('loan_id', $request->loanId)
                    ->pluck('customer_id')
            )->pluck('jina');

            // Format customer names
            $names = $this->formatCustomerNames($customers);

            // Send notifications
            $this->sendNotification(
                "Hongera, rejesho la Tsh {$request->amount} la mkopo wa {$names} limepokelewa. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Rejesho la Tsh {$request->amount} la mkopo wa {$names} limepokelewa na mtumishi {$user->jina_kamili} mwenye simu namba {$user->mobile}, wateja wa mkopo huo ni {$names}. Asante kwa kutumia mfumo wa mikopo center.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Rejesho limepokelewa kikamilifu.',
                'transaction' => $transaction
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Rejesho limeshindikana kupokelewa.',
                'message' => $e->getMessage()
            ], 500);
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
