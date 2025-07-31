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
use App\Services\OfisiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule as ValidationRule;
use App\Services\LoanService;
use Throwable;

class MiamalaController extends Controller
{
    public function lipiaRejesho(MiamalaRequest $request, OfisiService $ofisiService)
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) return $ofisi;

        $data = $request->validate([
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

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $appName = config('services.app.name');
            $helpNumber = config('services.help.number');

            $loan = Loan::findOrFail($data['loanId']);

            if (!in_array($loan->status, ['approved', 'defaulted'])) {
                return response()->json([
                    'message' => 'Mkopo huu haujaruhusiwa kwa marejesho.'
                ], 400);
            }

            $totalRejesho = Transaction::where('loan_id', $loan->id)
                ->where('category', 'rejesho')
                ->sum('amount');

            $remaining = $loan->total_due - $totalRejesho;

            if ($data['amount'] > $remaining) {
                return response()->json([
                    'message' => "Rejesho limezidi kiasi kilichobaki. Tafadhali lipa kiasi kisichozidi Tsh " . number_format($remaining) . "."
                ], 400);
            }

            $transaction = Transaction::create([
                'type' => $data['type'],
                'category' => $data['category'],
                'status' => $data['status'],
                'method' => $data['method'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'created_by' => $user->id,
                'user_id' => $user->id,
                'approved_by' => $user->id,
                'ofisi_id' => $data['ofisiId'],
                'loan_id' => $data['loanId'],
                'customer_id' => $data['mtejaId'],
            ]);

            $newTotal = $totalRejesho + $data['amount'];

            if ($newTotal >= $loan->total_due) {
                $loan->update(['status' => 'repaid']);
            }

            $customerNames = Customer::whereIn(
                'id',
                DB::table('loan_customers')->where('loan_id', $data['loanId'])->pluck('customer_id')
            )->pluck('jina');

            $names = $this->formatCustomerNames($customerNames);

            $this->sendNotification(
                "Hongera, rejesho la Tsh {$data['amount']} la mkopo wa $names limepokelewa. Asante kwa kutumia $appName, kwa msaada piga simu namba $helpNumber.",
                $user->id,
                null,
                $data['ofisiId']
            );

            $this->sendNotificationKwaViongoziWengine(
                "Rejesho la Tsh {$data['amount']} la mkopo wa $names limepokelewa na mtumishi $user->jina_kamili mwenye simu namba $user->mobile, wateja wa mkopo huo ni $names. Asante kwa kutumia mfumo wa Mikopo Center.",
                $data['ofisiId'],
                $user->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Rejesho limepokelewa kikamilifu.',
                'transaction' => $transaction
            ]);

        } catch (Throwable  $e) {
            try{
                DB::rollBack();
            }
            catch (Throwable $e){
                return response()->json([
                    'error' => 'Rejesho limeshindikana kupokelewa.',
                    'message' => $e->getMessage()
                ], 500);
            }

            return response()->json([
                'error' => 'Rejesho limeshindikana kupokelewa.',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function lipiaFaini(MiamalaRequest $request, OfisiService $ofisiService)
    {

        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

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



        try {

            $ofisi = Ofisi::findOrFail($request->ofisiId); // Ensure the office exists
            $user = Auth::user();

            $appName = config('services.app.name');
            $helpNumber = config('services.help.number');

            try {
                DB::beginTransaction();
            } catch (Throwable $e) {
                return response()->json([
                    'error' => 'Faini imeshindikana kupokelewa.',
                    'message' => $e->getMessage()
                ], 500);
            }
            // Create the transaction record
            Transaction::create([
                'type' => $request->type,
                'category' => $request->category,
                'status' => $request->status,
                'method' => $request->get('method'),
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
                throw new Exception("$message");
            }

            // Send notifications
            $this->sendNotification(
                "Imethibitishwa faini ya Tsh $request->amount ya mteja $mteja->jina imepokelewa. Asante kwa kutumia $appName, kwa msaada piga simu namba $helpNumber.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Imethibitishwa faini ya Tsh $request->amount ya mteja $mteja->jina imepokelewa kwa sababu ya $request->description na afisa $user->jina_kamili mwenye namba $user->mobile. Asante kwa kutumia $appName, kwa msaada piga simu namba $helpNumber.",
                $ofisi->id,
                $user->id
            );

            try {
                DB::commit();
            } catch (Throwable $e) {
                return response()->json([
                    'error' => 'Faini imeshindikana kupokelewa.',
                    'message' => $e->getMessage()
                ], 500);
            }

            return response()->json([
                'message' => 'Faini imepokelewa kikamilifu.',
            ]);

        } catch (Exception $e) {
            try {
                DB::rollBack();
            } catch (Throwable $e) {
                return response()->json([
                    'error' => 'Faini imeshindikana kupokelewa.',
                    'message' => $e->getMessage()
                ], 500);
            }
            return response()->json([
                'error' => 'Faini imeshindikana kupokelewa.',
                'message' => $e->getMessage()
            ], 500);

        }
    }

    public function sajiliPato(MiamalaRequest $request, OfisiService $ofisiService)
    {
        $appName = config('services.app.name');
        $helpNumber = config('services.help.number');

        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        $user = Auth::user();

        $position = $ofisi->pivot->position_id;

        $positionRecord = Position::find($position);

        if (!$positionRecord) {
            throw new Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kusajili pato.");
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



        try {
            try {
                DB::beginTransaction();
            } catch (Throwable $e) {
                return response()->json([
                    'error' => 'Pato limeshindikana kupokelewa.',
                    'message' => $e->getMessage()
                ], 500);
            }
            // Create the transaction record
            Transaction::create([
                'type' => $request->type,
                'category' => $request->category,
                'status' => 'completed',
                'method' => $request->get('method'),
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
                "Imethibitishwa pato la Tsh $request->amount limepokelewa kwa ajili ya $request->description. Asante kwa kutumia $appName, kwa msaada piga simu namba $helpNumber.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Imethibitishwa pato la Tsh $request->amount limepokelewa kwa ajili ya $request->description na kuwekwa na $cheo $user->jina_kamili mwenye namba $user->mobile. Asante kwa kutumia $appName, kwa msaada piga simu namba $helpNumber.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Pato limepokelewa kikamilifu.',
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Pato limeshindikana kupokelewa.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sajiliTumizi(MiamalaRequest $request, OfisiService $ofisiService)
    {
        $appName = config('services.app.name');
        $helpNumber = config('services.help.number');
        $user = Auth::user();

        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        $position = $ofisi->pivot->position_id;

        $positionRecord = Position::find($position);

        if (!$positionRecord) {
            throw new Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kusajili tumizi.");
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
                'method' => $request->get('method'),
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

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Tumizi limeshindikana kupokelewa.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getMarekebishoMiamala(MiamalaRequest $request, OfisiService $ofisiService)
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        $position = $ofisi->pivot->position_id;

        $positionRecord = Position::find($position);

        if (!$positionRecord) {
            throw new Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kuona miamala.");
        }

        $miamala = Transaction::with(['user', 'approver', 'creator', 'customer', 'transactionChanges'])
            ->where('ofisi_id', $ofisi->id)
            ->where('status', 'completed')
            ->whereHas('transactionChanges', function ($query) {
                $query->where('status', 'pending');
            })
            ->latest()
            ->get();

        return response()->json([
            'miamala' => $miamala,
        ], 200);
    }

    public function getMiamalaByDay(MiamalaRequest $request, LoanService $loanService, OfisiService $ofisiService)
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        $position = $ofisi->pivot->position_id;

        $positionRecord = Position::find($position);

        if (!$positionRecord) {
            throw new Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kuona miamala.");
        }

        $openBalance = Transaction::where('ofisi_id', $ofisi->id)
            ->where('status', 'completed')
            ->whereDate('created_at', '<', now()->toDateString())
            ->get()
            ->reduce(function ($carry, $item) {
                return $carry + ($item->type === 'kuweka' ? $item->amount : -$item->amount);
            }, 0);


        $miamala = Transaction::with(['user', 'approver', 'creator', 'customer', 'transactionChanges'])
            ->where('ofisi_id', $ofisi->id)
            ->where('status', 'completed')
            ->whereDate('created_at', now()->toDateString())
            ->latest()
            ->get();

        $mikopoRejesho = $loanService->getLoansWithPendingRejesho();

        $countMikopoNjeMuda = $loanService->countDefaultedLoans();

        $profit = $loanService->getProfitFromActiveLoans();

        return response()->json([
            'miamala' => $miamala,
            'openBalance' => $openBalance,
            'mikopoRejeshoDeni' => $mikopoRejesho,
            'countMikopoNjeMuda' => $countMikopoNjeMuda,
            'faidaMikopoHai' => $profit,
        ], 200);
    }

    public function getMiamalaByDates(MiamalaRequest $request, LoanService $loanService, OfisiService $ofisiService)
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        $positionId = optional($ofisi->pivot)->position_id;
        $positionRecord = Position::find($positionId);

        abort_unless((bool)$positionRecord, 403, 'Wewe sio kiongozi wa ofisi, huna uwezo wa kuona miamala.');

        $validator = Validator::make($request->all(), [
            'startDate' => 'required|date',
            'endDate'   => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za tarehe hazijawasilishwa ipasavyo',
                'errors'  => $validator->errors()
            ], 400);
        }

        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = $request->endDate
            ? Carbon::parse($request->endDate)->endOfDay()
            : $startDate->copy()->endOfDay();

        // Opening balance before the start date
        $openBalance = Transaction::where('ofisi_id', $ofisi->id)
            ->where('status', 'completed')
            ->whereDate('created_at', '<', $startDate)
            ->get()
            ->sum(function ($tx) {
                return $tx->type === 'kuweka' ? $tx->amount : -$tx->amount;
            });

        // Transactions within date range
        $miamala = Transaction::with(['user', 'approver', 'creator', 'customer', 'transactionChanges'])
            ->where('ofisi_id', $ofisi->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->get();

        $mikopoRejesho = $loanService->getLoansWithPendingRejeshoUntilDate($endDate);
        $countMikopoNjeMuda = $loanService->countDefaultedLoansUntilDate($endDate);
        $profit = $loanService->getProfitFromActiveLoansUntilDate($endDate);

        return response()->json([
            'miamala' => $miamala,
            'openBalance' => $openBalance,
            'mikopoRejeshoDeni' => $mikopoRejesho,
            'countMikopoNjeMuda' => $countMikopoNjeMuda,
            'faidaMikopoHai' => $profit,
        ]);
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
