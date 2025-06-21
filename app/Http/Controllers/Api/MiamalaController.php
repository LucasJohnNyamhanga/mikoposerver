<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiamalaRequest;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\Position;
use App\Models\Transaction;
use App\Models\UserOfisi;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule as ValidationRule;

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
            'mtejaId' => 'required|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $ofisi = Ofisi::findOrFail($request->ofisiId); // Ensure the office exists
            $user = Auth::user();

            $appName = env('APP_NAME');
            $helpNumber = env('APP_HELP');

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
                'customer_id' => $request->mtejaId,
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

    public function lipiaFaini(MiamalaRequest $request)
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
            'mtejaId' => 'required|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $ofisi = Ofisi::findOrFail($request->ofisiId); // Ensure the office exists
            $user = Auth::user();

            $appName = env('APP_NAME');
            $helpNumber = env('APP_HELP');

            // Create the transaction record
            Transaction::create([
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
                'loan_id' => null,
                'customer_id' => $request->mtejaId,
            ]);

            $mteja = Customer::findOrFail($request->mtejaId);

            // Check if any of the selected customers already have active loans
            $loanExisting = DB::table('loan_customers')
                ->join('loans', 'loan_customers.loan_id', '=', 'loans.id')
                ->where('loan_customers.customer_id', $request->mtejaId)
                ->whereIn('loans.status', ['approved', 'defaulted'])
                ->exists();

            // Respond based on loan type
            if (!$loanExisting) {
                $message = 'Mteja hana mkopo wowote unaoendelea.';
                throw new \Exception("{$message}");
            }

            // Send notifications
            $this->sendNotification(
                "Imethibitishwa faini ya Tsh {$request->amount} ya mteja {$mteja->jina} imepokelewa. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Imethibitishwa faini ya Tsh {$request->amount} ya mteja {$mteja->jina} imepokelewa kwa sababu ya {$request->description} na afisa {$user->jina_kamili} mwenye namba {$user->mobile}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Faini imepokelewa kikamilifu.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Faini imeshindikana kupokelewa.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sajiliPato(MiamalaRequest $request)
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
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kusajili pato.");
        }

        $cheo = $positionRecord->name;
        // Validate input
        $validator = Validator::make($request->all(), [
            'type' => [
                'required',
                'string',
                ValidationRule::in(['kuweka']),
            ],
            'category' => [
                'required',
                'string',
                ValidationRule::in(['fomu', 'rejesho', 'pato', 'tumizi', 'faini', 'mkopo']),
            ],
            'method' => [
                'required',
                'string',
                ValidationRule::in(['benki', 'mpesa', 'halopesa', 'airtelmoney', 'mix by yas', 'pesa mkononi']),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],
            'description' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Create the transaction record
            Transaction::create([
                'type' => $request->type,
                'category' => $request->category,
                'status' => 'completed',
                'method' => $request->method,
                'amount' => $request->amount,
                'description' => $request->description,
                'created_by' => $user->id,
                'user_id' => $user->id,
                'approved_by' => $user->id,
                'ofisi_id' => $ofisi->id,
                'loan_id' => null,
                'customer_id' => null,
            ]);

            // Send notifications
            $this->sendNotification(
                "Imethibitishwa pato la Tsh {$request->amount} limepokelewa kwa ajili ya {$request->description}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Imethibitishwa pato la Tsh {$request->amount} limepokelewa kwa ajili ya {$request->description} na kuwekwa na {$cheo} {$user->jina_kamili} mwenye namba {$user->mobile}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Pato limepokelewa kikamilifu.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Pato limeshindikana kupokelewa.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sajiliTumizi(MiamalaRequest $request)
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
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kusajili tumizi.");
        }

        $cheo = $positionRecord->name;
        // Validate input
        $validator = Validator::make($request->all(), [
            'type' => [
                'required',
                'string',
                ValidationRule::in(['kutoa']),
            ],
            'category' => [
                'required',
                'string',
                ValidationRule::in(['fomu', 'rejesho', 'pato', 'tumizi', 'faini', 'mkopo']),
            ],
            'method' => [
                'required',
                'string',
                ValidationRule::in(['benki', 'mpesa', 'halopesa', 'airtelmoney', 'mix by yas', 'pesa mkononi']),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],
            'description' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Create the transaction record
            Transaction::create([
                'type' => $request->type,
                'category' => $request->category,
                'status' => 'completed',
                'method' => $request->method,
                'amount' => $request->amount,
                'description' => $request->description,
                'created_by' => $user->id,
                'user_id' => $user->id,
                'approved_by' => $user->id,
                'ofisi_id' => $ofisi->id,
                'loan_id' => null,
                'customer_id' => null,
            ]);

            // Send notifications
            $this->sendNotification(
                "Imethibitishwa tumizi la Tsh {$request->amount} limesajiliwa kwa ajili ya {$request->description}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Imethibitishwa tumizi la Tsh {$request->amount} limesajiliwa kwa ajili ya {$request->description} na kutumiwa na {$cheo} {$user->jina_kamili} mwenye namba {$user->mobile}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Tumizi limesajiliwa kikamilifu.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Tumizi limeshindikana kupokelewa.',
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
