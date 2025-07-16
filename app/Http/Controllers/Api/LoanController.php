<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoanRequest;
use App\Models\Customer;
use App\Models\Dhamana;
use App\Models\Loan;
use App\Models\LoanCustomer;
use App\Models\Mabadiliko;
use App\Models\Mdhamini;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\Position;
use App\Models\Transaction;
use App\Models\UserOfisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
            $appName = env('APP_NAME');
            $helpNumber = env('APP_HELP');

            // Check if any of the selected customers already have active loans
            $loanExisting = DB::table('loan_customers')
                ->join('loans', 'loan_customers.loan_id', '=', 'loans.id')
                ->whereIn('loan_customers.customer_id', $request->wateja)
                ->whereIn('loans.status', ['pending', 'approved', 'defaulted', 'error'])
                ->exists();

            // Respond based on loan type
            if ($loanExisting) {
                $message = $request->loanType === 'kikundi'
                    ? 'Mteja mmoja au zaidi tayari ana ombi la mkopo au ana mkopo unaoendelea.'
                    : 'Mteja tayari ana ombi la mkopo au ana mkopo unaoendelea.';
                throw new \Exception("{$message}");
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
                'status_details' => null,
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
                    "Hongera, mkopo wa {$names} umefunguliwa, boresha taarifa zaidi za mkopo huu kwenye Mikopo mipya ya {$request->loanType} kwenye eneo la wateja, Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
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
                'status_details' => null,
            ]);

            // Handle Loan Customers
            if ($request->has('wateja')) {
                LoanCustomer::where('loan_id', $loan->id)->delete();
                $loanCustomers = array_map(fn($id) => ['loan_id' => $loan->id, 'customer_id' => $id], $request->wateja);
                LoanCustomer::insert($loanCustomers);
            }

            // Handle Guarantors
            if ($request->has('wadhamini')) {
                $oldGuarantors = Mdhamini::where('loan_id', $loan->id)
                    ->pluck('customer_id')
                    ->toArray();

                $newGuarantors = $request->wadhamini ?? [];

                // Which guarantors were removed?
                $removedGuarantors = array_diff($oldGuarantors, $newGuarantors);

                if (!empty($removedGuarantors)) {
                    // Dhamana belonging to removed guarantors
                    $oldDhamanas = Dhamana::where('loan_id', $loan->id)
                        ->whereIn('customer_id', $removedGuarantors)
                        ->get();

                    foreach ($oldDhamanas as $dhamana) {
                        if ($dhamana->picha) {
                            $imagePath = public_path('uploads/dhamana/' . $dhamana->picha);
                            if (is_file($imagePath)) {
                                @unlink($imagePath); // suppress warnings
                            }
                        }
                    }

                    // Delete dhamana for removed guarantors
                    Dhamana::where('loan_id', $loan->id)
                        ->whereIn('customer_id', $removedGuarantors)
                        ->delete();
                }

                // Remove all current guarantor rows & insert new ones
                Mdhamini::where('loan_id', $loan->id)->delete();

                $guarantors = collect($newGuarantors)
                    ->map(fn($id) => [
                        'loan_id'     => $loan->id,
                        'customer_id' => $id,
                    ])->toArray();

                if (!empty($guarantors)) {
                    Mdhamini::insert($guarantors);
                }
            }

            

            // Format customer names
            $customers = Customer::whereIn('id', $request->wateja)->pluck('jina');
            $names = $customers->join(', ', ' pamoja na ');

            // Format dhamana names
            $dhamana = Dhamana::where('loan_id', $loan->id)->get();

            // Extract the names of the dhamanas
            $dhamanaNames = $dhamana->pluck('jina')->join(', ', ' pamoja na ');

            // Calculate the total sum of 'thamani' column
            $totalThamani = $dhamana->sum('thamani');


            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => Auth::id(),
                'action' => 'updated',
                'description' => "Ombi la mkopo wa kiasi cha Tsh {$loan->amount} linasubili uhakiki na kupitishwa likiwa na dhamana {$dhamanaNames}, yenye jumla ya thamani ya {$totalThamani}. Afisa mwasilishi ni {$user->jina_kamili} mwenye namba {$user->mobile}. Majina ya wakopaji ni {$names}.",
            ]);

            

            $this->sendNotification(
                "Ombi la mkopo wa Tsh {$loan->amount} wa {$names} limewasilishwa  likiwa na dhamana {$dhamanaNames}, yenye jumla ya thamani ya {$totalThamani} na linasubili uhakiki na kupitishwa. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Ombi la mkopo la kiasi cha Tsh {$loan->amount} la {$names} linasubilia uhakiki na kupitishwa likiwa na dhamana {$dhamanaNames}, yenye jumla ya thamani ya {$totalThamani}. Limewasilishwa na afisa {$user->jina_kamili} mwenye namba {$user->mobile}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
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

    public function pitishaMkopo(LoanRequest $request)
    {   
         // Validate input
        $validator = Validator::make($request->all(), [
            'loanId' => 'required|exists:loans,id',
            'mwanzoMkopo' => 'required|string|max:255',
            'mwishoMkopo' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
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

            $loan = Loan::findOrFail($request->loanId);

            $loan->update([
                'status' => 'approved',
                'issued_date' =>  Carbon::parse($request->mwanzoMkopo),
                'due_date' =>  Carbon::parse($request->mwishoMkopo),
                'status_details' => null,
            ]);

            // Handle Loan Customers
            $customers = $loan->customers->pluck('jina');
            $names = $customers->join(', ', ' pamoja na ');

            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => Auth::id(),
                'action' => 'updated',
                'description' => "Mkopo wa kiasi cha Tsh {$loan->amount}, wa {$names} umepitishwa na {$cheo} {$user->jina_kamili} wa simu namba {$user->mobile}.",
            ]);

            $fomuMkopo = $loan->amount * ($loan->fomu/100);

            // Create the transaction record
            Transaction::create([
                'type' => 'kuweka',
                'category' => 'fomu',
                'status' => 'completed',
                'method' => 'pesa mkononi',
                'amount' => $fomuMkopo,
                'description' => "Makato Fomu ya tsh {$fomuMkopo} kwenye mkopo wa {$names}",
                'created_by' => $user->id,
                'user_id' => $user->id,
                'approved_by' => $user->id,
                'ofisi_id' => $ofisi->id,
                'loan_id' => $loan->id,
                'customer_id' => null,
                'is_loan_source' => true,
            ]);

            // Create the transaction record
            Transaction::create([
                'type' => 'kutoa',
                'category' => 'mkopo',
                'status' => 'completed',
                'method' => 'pesa mkononi',
                'amount' => $loan->amount,
                'description' => "Mkopo umelipwa kwa {$names}",
                'created_by' => $user->id,
                'user_id' => $user->id,
                'approved_by' => $user->id,
                'ofisi_id' => $ofisi->id,
                'loan_id' => $loan->id,
                'customer_id' => null,
                'is_loan_source' => true,
            ]);

            $this->sendNotification(
                "Mkopo wa Tsh {$loan->amount} wa {$names} umepitishwa na {$cheo} {$user->jina_kamili} mwenye namba {$user->mobile}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Ombi la mkopo la kiasi cha Tsh {$loan->amount} la {$names} limepitishwa. Limepitishwa na {$cheo} {$user->jina_kamili} mwenye namba {$user->mobile}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();
            return response()->json(['message' => 'Ombi la mkopo limepitishwa kikamilifu.', 'loan' => $loan], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Ombi la mkopo limeshindikana kupitishwa.', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMikopoKasoro(LoanRequest $request)
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
                            ])->whereIn('status', ['error'])->latest();
                        }])->where('id', $ofisi->id)
                        ->first();
            
            return response()->json([
            'kikundi' => $mikopoOfisi,
            ], 200);
        }

        return response()->json(['message' => 'Huna usajili kwenye kikundi chochote. Piga simu msaada 0784477999'], 401);
    }

    public function batilishaMkopo(LoanRequest $request)
    {   
         // Validate input
        $validator = Validator::make($request->all(), [
            'loanId' => 'required|exists:loans,id',
            'error' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $helpNumber = env('APP_HELP');
            $appName = env('APP_NAME');

            if (!$user) {
                throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
            }

            if (!$user->activeOfisi) {
                throw new \Exception("Kuna Tatizo. Huna usajili kwenye ofisi yeyote. Piga simu msaada {$helpNumber}");
            }

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

            $loan = Loan::findOrFail($request->loanId);

            $loan->update([
                'status' => 'error',
                'status_details' => $request->error,
            ]);

            $customers = $loan->customers->pluck('jina');
            $names = $customers->join(', ', ' pamoja na ');

            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => Auth::id(),
                'action' => 'updated',
                'description' => "Mkopo wa kiasi cha Tsh {$loan->amount}, wa {$names} umebatilishwa na kuwekewa kasoro ya {$request->error} na {$cheo} {$user->jina_kamili} wa simu namba {$user->mobile}.",
            ]);

            $this->sendNotification(
                "Mkopo wa {$loan->loan_type} wa Tsh {$loan->amount} wa {$names} unakasoro ya {$request->error}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Ombi la mkopo wa {$loan->loan_type} la kiasi cha Tsh {$loan->amount} la {$names} lina kasoro ya {$request->error}. limekataliwa na {$cheo} {$user->jina_kamili} mwenye namba {$user->mobile}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();
            return response()->json(['message' => 'Ombi la mkopo limewekewa kasoro kikamilifu.', 'loan' => $loan], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Ombi la mkopo limeshindikana kuwekewa kasoro.', 'message' => $e->getMessage()], 500);
        }
    }

    public function haririMkopo(LoanRequest $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'jinaKikundi'   => 'nullable|string|max:255',
            'amount'        => 'required|numeric|min:0',
            'riba'          => 'required|numeric|min:0|max:100',
            'fomu'          => 'required|numeric|min:0|max:100',
            'totalDue'      => 'required|numeric|min:0',
            'kipindiMalipo' => 'required|in:siku,wiki,mwezi,mwaka',
            'mudaMalipo'    => 'nullable|integer|min:1',
            'userId'        => 'required|exists:users,id',
            'ofisiId'       => 'required|exists:ofisis,id',
            'loanId'        => 'required|exists:loans,id',
            'loanType'      => 'required|in:kikundi,binafsi',
            'wateja'        => 'nullable|array',
            'wateja.*'      => 'exists:customers,id',
            'wadhamini'     => 'nullable|array',
            'wadhamini.*'   => 'exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user       = Auth::user();
            $helpNumber = env('APP_HELP');
            $appName    = env('APP_NAME');

            if (!$user) {
                throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
            }

            if (!$user->activeOfisi) {
                throw new \Exception("Huna usajili kwenye ofisi yeyote. Piga simu msaada {$helpNumber}");
            }

            // Validate user's office + position
            $userOfisi = UserOfisi::where('user_id', $user->id)
                ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                ->first();

            if (!$userOfisi || !$userOfisi->ofisi) {
                throw new \Exception("Kuna hitilafu kwenye usajili wako wa ofisi. Wasiliana na msaada.");
            }

            $ofisi    = $userOfisi->ofisi;
            $position = Position::find($userOfisi->position_id);

            if (!$position) {
                throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kufanya kitendo hiki.");
            }

            $cheo = $position->name;

            // Target loan
            $loan = Loan::findOrFail($request->loanId);

            // Backup before update
            $loanBackUp = $loan->only([
                'amount', 'riba', 'fomu', 'total_due',
                'kipindi_malipo', 'muda_malipo', 'loan_type',
                'user_id', 'ofisi_id', 'jina_kikundi'
            ]);

            // Update loan core fields
            $loan->update([
                'amount'         => $request->amount,
                'riba'           => $request->riba,
                'fomu'           => $request->fomu,
                'total_due'      => $request->totalDue,
                'kipindi_malipo' => $request->kipindiMalipo,
                'muda_malipo'    => $request->mudaMalipo,
                'loan_type'      => $request->loanType,
                'user_id'        => $request->userId,
                'ofisi_id'       => $request->ofisiId,
                'jina_kikundi'   => $request->jinaKikundi,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Update Loan Customers (Borrowers)
            |--------------------------------------------------------------------------
            */
            if ($request->has('wateja')) {
                LoanCustomer::where('loan_id', $loan->id)->delete();

                $loanCustomers = collect($request->wateja ?? [])
                    ->map(fn($id) => [
                        'loan_id'     => $loan->id,
                        'customer_id' => $id,
                    ])->toArray();

                if (!empty($loanCustomers)) {
                    LoanCustomer::insert($loanCustomers);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Update Guarantors + Clean Up Dhamana for Removed Guarantors Only
            |--------------------------------------------------------------------------
            | Steps:
            | 1. Get old guarantor IDs.
            | 2. Compare with new list (if provided).
            | 3. Delete dhamana + images for removed guarantors.
            | 4. Replace Mdhamini rows with new list.
            */
            if ($request->has('wadhamini')) {
                $oldGuarantors = Mdhamini::where('loan_id', $loan->id)
                    ->pluck('customer_id')
                    ->toArray();

                $newGuarantors = $request->wadhamini ?? [];

                // Which guarantors were removed?
                $removedGuarantors = array_diff($oldGuarantors, $newGuarantors);

                if (!empty($removedGuarantors)) {
                    // Dhamana belonging to removed guarantors
                    $oldDhamanas = Dhamana::where('loan_id', $loan->id)
                        ->whereIn('customer_id', $removedGuarantors)
                        ->get();

                    foreach ($oldDhamanas as $dhamana) {
                        if ($dhamana->picha) {
                            $imagePath = public_path('uploads/dhamana/' . $dhamana->picha);
                            if (is_file($imagePath)) {
                                @unlink($imagePath); // suppress warnings
                            }
                        }
                    }

                    // Delete dhamana for removed guarantors
                    Dhamana::where('loan_id', $loan->id)
                        ->whereIn('customer_id', $removedGuarantors)
                        ->delete();
                }

                // Remove all current guarantor rows & insert new ones
                Mdhamini::where('loan_id', $loan->id)->delete();

                $guarantors = collect($newGuarantors)
                    ->map(fn($id) => [
                        'loan_id'     => $loan->id,
                        'customer_id' => $id,
                    ])->toArray();

                if (!empty($guarantors)) {
                    Mdhamini::insert($guarantors);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Names & Dhamana Info (for logging & notifications)
            |--------------------------------------------------------------------------
            */
            // Borrower names (if request gave new list use that, else load from DB)
            if ($request->has('wateja')) {
                $customers = !empty($request->wateja)
                    ? Customer::whereIn('id', $request->wateja)->pluck('jina')
                    : collect();
            } else {
                $customers = $loan->customers()->pluck('jina');
            }
            $names = $customers->join(', ', ' pamoja na ');

            // Dhamana info (post-update state)
            $dhamana = Dhamana::where('loan_id', $loan->id)->get();
            $dhamanaNames = $dhamana->pluck('jina')->join(', ', ' pamoja na ');
            $totalThamani = $dhamana->sum('thamani');

            /*
            |--------------------------------------------------------------------------
            | Update Related Transactions
            |--------------------------------------------------------------------------
            */
            $fomuMkopo = $loan->amount * ($loan->fomu / 100);

            Transaction::where('loan_id', $loan->id)
                ->where('category', 'fomu')
                ->update([
                    'amount'      => $fomuMkopo,
                    'description' => "Makato Fomu ya Tsh {$fomuMkopo} kwenye mkopo wa {$names}",
                ]);

            Transaction::where('loan_id', $loan->id)
                ->where('category', 'mkopo')
                ->update([
                    'amount'      => $loan->amount,
                    'description' => "Mkopo umelipwa kwa {$names}",
                ]);

            /*
            |--------------------------------------------------------------------------
            | Log Change (Mabadiliko)
            |--------------------------------------------------------------------------
            */
            Mabadiliko::create([
                'loan_id'      => $loan->id,
                'performed_by' => Auth::id(),
                'action'       => 'updated',
                'description'  =>
                    "Mkopo huu ulihaririwa na kubadilishwa, maelezo ya mkopo kabla ya mabadiliko:\n" .
                    json_encode($loanBackUp, JSON_PRETTY_PRINT) .
                    "\nMhariri: {$cheo} {$user->jina_kamili} ({$user->mobile}).",
            ]);

            /*
            |--------------------------------------------------------------------------
            | Send Notifications
            |--------------------------------------------------------------------------
            */
            $this->sendNotification(
                "Mkopo ulio hai umefanyiwa mabadiliko:\n" .
                json_encode($loanBackUp, JSON_PRETTY_PRINT) .
                "\nDhamana: {$dhamanaNames} (Jumla Thamani: {$totalThamani})." .
                "\nMhariri: {$cheo} {$user->jina_kamili} ({$user->mobile}). Asante kwa kutumia {$appName}, kwa msaada piga simu {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Mkopo umefanyiwa mabadiliko:\n" .
                json_encode($loanBackUp, JSON_PRETTY_PRINT) .
                "\nMhariri: {$cheo} {$user->jina_kamili} ({$user->mobile}). Asante kwa kutumia {$appName}, msaada: {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            // Optionally reload updated relations if client needs fresh data:
            // $loan->load(['customers', 'wadhamini', 'dhamana']);

            return response()->json([
                'message' => 'Mkopo umehaririwa kikamilifu.',
                'loan'    => $loan,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error'   => 'Ombi la mkopo limeshindikana kuhaririwa.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function fungaMkopo(LoanRequest $request)
    {
        $user = Auth::user();
        $helpNumber = env('APP_HELP');
        $appName = env('APP_NAME');

        if (!$user) {
            throw new \Exception("Kuna tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
        }

        if (!$user->activeOfisi) {
            throw new \Exception("Kuna tatizo. Huna usajili kwenye ofisi yeyote. Piga simu msaada {$helpNumber}");
        }

        $userOfisi = UserOfisi::where('user_id', $user->id)
            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
            ->first();

        if (!$userOfisi || !$userOfisi->ofisi) {
            throw new \Exception("Kuna tatizo kwenye usajili wako wa ofisi. Tafadhali wasiliana na msaada.");
        }

        $ofisi = $userOfisi->ofisi;
        $ofisi = $user->maofisi->where('id', $ofisi->id)->first();
        $position = $ofisi->pivot->position_id;

        $positionRecord = Position::find($position);
        if (!$positionRecord) {
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kukamilisha hichi kitendo.");
        }

        $cheo = $positionRecord->name;

        $validator = Validator::make($request->all(), [
            'loanId' => 'required|exists:loans,id',
            'sababu' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        DB::beginTransaction();
        try {
            $loan = Loan::with('customers')->findOrFail($request->loanId);

            // Funga mkopo
            $loan->update([
                'status' => 'closed',
                'status_details' => $request->sababu,
            ]);

            // Log mabadiliko
            Mabadiliko::create([
                'loan_id' => $loan->id,
                'performed_by' => $user->id,
                'action' => 'updated',
                'description' => "Mkopo huu umefungwa kwa sababu ya {$request->sababu} na {$cheo} {$user->jina_kamili} ({$user->mobile})",
            ]);

            // Futa miamala husika
            Transaction::where('loan_id', $loan->id)->update([
                'status' => 'cancelled',
            ]);

            // Tayarisha majina ya wateja (kwa ajili ya notification)
            $customerNames = $loan->customers->pluck('jina');
            $namesString = $customerNames->count() > 1
                ? $customerNames->slice(0, -1)->implode(', ') . ' pamoja na ' . $customerNames->last()
                : $customerNames->first();

            // Tuma notification kwa mhusika
            $this->sendNotification(
                "Mkopo wa {$namesString} umefungwa kikamilifu kwa sababu ya {$request->sababu}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            // Tuma notification kwa viongozi wengine
            $this->sendNotificationKwaViongoziWengine(
                "Mkopo wa {$namesString} umefungwa na {$cheo} {$user->jina_kamili} ({$user->mobile}), kwa sababu ya {$request->sababu}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Mkopo umefungwa kikamilifu.',
                'loan' => $loan,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ombi la mkopo limeshindikana kufungwa.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getCustomerLoanDetails(LoanRequest $request)
    {
        try {
            $user = Auth::user();
            $helpNumber = env('APP_HELP');

            if (!$user) {
                throw new \Exception("Kuna tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
            }

            if (!$user->activeOfisi) {
                throw new \Exception("Huna ofisi unayoitumia kwa sasa. Piga simu msaada {$helpNumber}");
            }

            $activeOfisiId = $user->activeOfisi->ofisi_id;

            $userOfisi = UserOfisi::where('user_id', $user->id)
                ->where('ofisi_id', $activeOfisiId)
                ->first();

            if (!$userOfisi || !$userOfisi->ofisi) {
                throw new \Exception("Kuna tatizo kwenye usajili wako wa ofisi. Wasiliana na msaada.");
            }

            $position = Position::find($userOfisi->position_id);
            if (!$position) {
                throw new \Exception("Wewe sio kiongozi wa ofisi, huna ruhusa ya kufanya kitendo hiki.");
            }

            $validator = Validator::make($request->all(), [
                'mtejaId' => 'required|exists:customers,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => $validator->errors()->first()], 422);
            }

            $customerId = $request->mtejaId;

            // Load customer with both relationships filtered by office
            $customer = Customer::with([
                'loans' => function ($loanQuery) use ($activeOfisiId) {
                    $loanQuery->with([
                        'user',
                        'customers',
                        'wadhamini',
                        'dhamana',
                        'transactions' => function ($query) {
                            $query->with(['user', 'approver', 'creator', 'customer'])
                                ->where('status', 'completed')
                                ->latest();
                        },
                        'mabadiliko' => function ($query) {
                            $query->with(['user'])->latest();
                        },
                    ])->where('ofisi_id', $activeOfisiId);
                },
                'mikopoAliyodhamini' => function ($loanQuery) use ($activeOfisiId, $customerId) {
                    $loanQuery->with([
                        'user',
                        'customers',
                        'wadhamini',
                        'dhamana' => function ($query) use ($customerId) {
                            $query->where('customer_id', $customerId)->latest();
                        },
                        'transactions' => function ($query) {
                            $query->with(['user', 'approver', 'creator', 'customer'])
                                ->where('status', 'completed')
                                ->latest();
                        },
                        'mabadiliko' => function ($query) {
                            $query->with(['user'])->latest();
                        },
                    ])->where('ofisi_id', $activeOfisiId);
                },
            ])->findOrFail($customerId);

            // Add position info for loan users in borrowed loans
            foreach ($customer->loans as $loan) {
                if ($loan->user) {
                    $loan->user->position_in_active_ofisi = $loan->user->positionInOfisi($activeOfisiId);
                }
            }

            // Add position info for loan users in guaranteed loans
            foreach ($customer->mikopoAliyodhamini as $loan) {
                if ($loan->user) {
                    $loan->user->position_in_active_ofisi = $loan->user->positionInOfisi($activeOfisiId);
                }
            }

            return response()->json([
                'borrowed_loans' => $customer,
                'guaranteed_loans' => $customer,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Mteja hakupatikana kwenye mfumo, jaribu tena'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
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
