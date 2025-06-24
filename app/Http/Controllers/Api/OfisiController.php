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

        $this->updateLoanStatuses($ofisi->id);//its working
        
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
                            ])
                            ->where('status', 'completed')
                            ->latest();
                        }
                        ,'wadhamini','dhamana','mabadiliko'=> function ($query) {
                            $query->latest();
                        }
                        ])->whereIn('status', ['approved','defaulted',])->latest();
                    },'transactions'=> function ($query) {
                            $query->with([
                                'user','approver','creator','customer'
                            ])
                            ->where('status', 'completed')
                            ->whereDate('created_at', now()->toDateString())->latest();//transaction status must be 'completed'
                        },'ainamikopo'])->where('id', $ofisi->id)->first();

        return response()->json([
        'user_id' => $user->id,
        'ofisi' => $ofisiYangu,
        ], 200);
    }

    public function getMapato(OfisiRequest $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada 0784477999'
            ], 401);
        }

        if (!$user->activeOfisi) {
            return response()->json([
                'message' => 'Huna usajili kwenye ofisi yeyote. Piga simu msaada 0784477999'
            ], 401);
        }

        $userOfisi = UserOfisi::where('user_id', $user->id)
            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
            ->first();

        $ofisi = $userOfisi->ofisi;

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

        $miamala = Transaction::with(['user', 'approver', 'creator', 'customer'])
            ->where('ofisi_id', $ofisi->id)
            ->where('type', 'kuweka') // Filter by type = kuweka (Mapato)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->get();

        return response()->json([
            'mapato' => $miamala,
        ], 200);
    }

    public function getMatumizi(OfisiRequest $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada 0784477999'
            ], 401);
        }

        if (!$user->activeOfisi) {
            return response()->json([
                'message' => 'Huna usajili kwenye ofisi yeyote. Piga simu msaada 0784477999'
            ], 401);
        }

        $userOfisi = UserOfisi::where('user_id', $user->id)
            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
            ->first();

        $ofisi = $userOfisi->ofisi;

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

        $miamala = Transaction::with(['user', 'approver', 'creator', 'customer'])
            ->where('ofisi_id', $ofisi->id)
            ->where('type', 'kutoa') // Filter by type = kuweka (Mapato)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->get();

        return response()->json([
            'matumizi' => $miamala,
        ], 200);
    }


    public function getUserOfisiSummary(): JsonResponse
    {
        $userId = Auth::id();

        $user = User::with(['ofisis' => function ($query) {
            $query->withCount('customers')
                ->withCount([
                    'acceptedUsers as users_count',
                ])
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
                'users_count' => $ofisi->users_count,
                'active_loans_count' => $ofisi->active_loans_count,
                'total_amount_loaned' => (float) $ofisi->total_amount_loaned,
                'amount_defaulted' => (float) $ofisi->amount_defaulted,
            ];
        });

        return response()->json($ofisis);
    }

    public function getMwamala(OfisiRequest $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada 0784477999'
            ], 401);
        }

        if (!$user->activeOfisi) {
            return response()->json([
                'message' => 'Huna usajili kwenye ofisi yeyote. Piga simu msaada 0784477999'
            ], 401);
        }

        $userOfisi = UserOfisi::where('user_id', $user->id)
            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
            ->first();

        $ofisi = $userOfisi->ofisi; //use ofisi->id compare it to ofisi_id of transaction, reject if not the same ofisi

        $validator = Validator::make($request->all(), [
            'mwamalaId' => 'required|integer|exists:transactions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za mwamala hazijawasilishwa ipasavyo',
                'errors'  => $validator->errors()
            ], 400);
        }

        $transaction = Transaction::getTransactionDetailsWithId($request->mwamalaId);

        // Check if transaction belongs to user's active ofisi
        if ($transaction->ofisi_id !== $ofisi->id) {
            return response()->json([
                'message' => 'Huna ruhusa ya kuona taarifa za mwamala huu. Piga simu msaada 0784477999'
            ], 403);
        }

        return response()->json([
            'mwamala' => $transaction,
        ], 200);
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

    public function badiliOfisi(OfisiRequest $request)
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

        $validator = Validator::make($request->all(), [
            'ofisiId' => 'required|integer|exists:ofisis,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za kubadili ofisi hazijawasilishwa ipasavyo',
                'errors' => $validator->errors()
            ], 400);
        }

        // Ensure user is assigned & accepted in the new ofisi
        $acceptedOfisiIds = $user->ofisis->pluck('id')->toArray();
        if (!in_array($request->ofisiId, $acceptedOfisiIds)) {
            return response()->json([
                'message' => 'Huwezi kubadili kwenda ofisi ambayo hujajiunga nayo au haijakubaliwa.',
            ], 403);
        }

        DB::table('actives')->updateOrInsert(
            ['user_id' => $user->id],
            ['ofisi_id' => $request->ofisiId, 'updated_at' => now(), 'created_at' => now()]
        );

        $updatedOfisi = Ofisi::find($request->ofisiId);

        return response()->json([
            'message' => "Umefanikiwa kubadili kwenda ofisi ya {$updatedOfisi->jina}.",
        ], 200);
    }

    public function sajiliOfisiBilaUser(OfisiRequest $request)
    {
        $user = Auth::user();
        $helpNumber = env('APP_HELP');

        if (!$user) {
            throw new \Exception("Kuna tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
        }

        if (!$user->activeOfisi) {
            throw new \Exception("Huna ofisi inayotumika kwa sasa. Piga simu msaada {$helpNumber}");
        }

        $userOfisi = UserOfisi::where('user_id', $user->id)
                            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                            ->first();

        if (!$userOfisi) {
            throw new \Exception("Hatukuweza kupata usajili wako kwenye ofisi hiyo.");
        }

        $ofisi = $user->maofisi->where('id', $userOfisi->ofisi_id)->first();

        if (!$ofisi || !$ofisi->pivot->position_id) {
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kukamilisha hichi kitendo.");
        }

        $position = Position::find($ofisi->pivot->position_id);
        if (!$position) {
            throw new \Exception("Cheo chako hakijapatikana. Tafadhali wasiliana na msaada.");
        }

        $validator = Validator::make($request->all(), [
            'jinaOfisi' => 'required|string|max:255',
            'mkoa' => 'required|string|max:255',
            'wilaya' => 'required|string|max:255',
            'kata' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za kufungua ofisi mpya hazijawasilishwa ipasavyo',
                'errors' => $validator->errors()
            ], 400);
        }

        $existingOfisi = Ofisi::where('jina', $request->jinaOfisi)
            ->where('mkoa', $request->mkoa)
            ->where('wilaya', $request->wilaya)
            ->where('kata', $request->kata)
            ->first();

        if ($existingOfisi) {
            return response()->json([
                'message' => 'Jina la ofisi limeshatumika katika eneo hili',
            ], 409);
        }


        // Create the new ofisi
        $ofisi = Ofisi::create([
            'jina' => $request->jinaOfisi,
            'mkoa' => $request->mkoa,
            'wilaya' => $request->wilaya,
            'kata' => $request->kata,
        ]);

        // Attach the user to the new ofisi as accepted with position_id = 1
        $user->maofisi()->attach($ofisi->id, [
            'position_id' => 1,
            'status' => 'accepted',
        ]);

        // Set this ofisi as active
        DB::table('actives')->updateOrInsert(
            ['user_id' => $user->id],
            ['ofisi_id' => $ofisi->id, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json([
            'message' => "Umefanikiwa kufungua ofisi ya {$ofisi->jina}.",
        ], 200);
    }

    public function jiungeOfisiBilaUser(OfisiRequest $request)
    {
        $user = Auth::user();
        $helpNumber = env('APP_HELP');

        if (!$user) {
            throw new \Exception("Kuna tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
        }

        if (!$user->activeOfisi) {
            throw new \Exception("Huna ofisi inayotumika kwa sasa. Piga simu msaada {$helpNumber}");
        }

        $userOfisi = UserOfisi::where('user_id', $user->id)
                            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                            ->first();

        if (!$userOfisi) {
            throw new \Exception("Hatukuweza kupata usajili wako kwenye ofisi hiyo.");
        }

        $ofisi = $user->maofisi->where('id', $userOfisi->ofisi_id)->first();

        if (!$ofisi || !$ofisi->pivot->position_id) {
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kukamilisha hichi kitendo.");
        }

        $position = Position::find($ofisi->pivot->position_id);
        if (!$position) {
            throw new \Exception("Cheo chako hakijapatikana. Tafadhali wasiliana na msaada.");
        }

        $validator = Validator::make($request->all(), [
            'ofisiId' => 'required|integer|exists:ofisis,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za kujiunga hazijawasilishwa ipasavyo',
                'errors' => $validator->errors()
            ], 400);
        }

        $ofisi = Ofisi::find($request->ofisiId);

        if (!$ofisi) {
            return response()->json([
                'message' => 'Ofisi uliyoichagua haipo au imefutwa'
            ], 400);
        }

        // Check if the relationship already exists
        $userOfisiPivot = $user->maofisi()->where('ofisi_id', $ofisi->id)->first();

        if ($userOfisiPivot) {
            $status = $userOfisiPivot->pivot->status;

            if ($status === 'pending') {
                return response()->json([
                    'message' => 'Umeshaomba kujiunga na ofisi hii, subiri kukubaliwa.'
                ], 400);
            }

            if ($status === 'denied') {
                $user->maofisi()->updateExistingPivot($ofisi->id, ['status' => 'pending']);
            }

            if ($status === 'accepted') {
                return response()->json([
                    'message' => 'Umeshajiunga na ofisi hii tayari.'
                ], 400);
            }
        } else {
            // Attach if it doesn't exist
            $user->maofisi()->attach($ofisi->id, ['status' => 'pending']);
        }

        // Update active ofisi
        DB::table('actives')->updateOrInsert(
            ['user_id' => $user->id],
            ['ofisi_id' => $ofisi->id, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json([
            'message' => "Umefanikiwa kutuma ombi la kujiunga na ofisi ya {$ofisi->jina}. Subiri kukubaliwa.",
        ], 200);
    }

    public function getOfisiUsersWithDetails()
    {
        $user = Auth::user();
        $helpNumber = env('APP_HELP');

        if (!$user) {
            throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
        }

        if (!$user->activeOfisi) {
            throw new \Exception("Kuna Tatizo. Huna usajili kwenye ofisi yeyote. Piga simu msaada {$helpNumber}");
        }

        $ofisiId = $user->activeOfisi->ofisi_id;

        $userOfisi = UserOfisi::where('user_id', $user->id)
                        ->where('ofisi_id', $ofisiId)
                        ->first();

        $position = Position::find($userOfisi?->position_id);

        if (!$position) {
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kuona watumishi wa ofisi na utendaji wao.");
        }

        $users = User::whereHas('maofisi', function ($query) use ($ofisiId) {
            $query->where('ofisi_id', $ofisiId)
                  ->where('user_ofisis.status', 'accepted');
        })
        ->with([
            'maofisi' => function ($query) use ($ofisiId) {
                $query->where('ofisi_id', $ofisiId);
            },
            'customer' => function ($query) use ($ofisiId) {
                $query->where('ofisi_id', $ofisiId);
            },
            'loans' => function ($query) use ($ofisiId) {
                $query->whereIn('status', ['approved', 'defaulted'])
                      ->where('ofisi_id', $ofisiId);
            },
            'loans.customers' => function ($query) use ($ofisiId) {
                $query->where('ofisi_id', $ofisiId);
            },
        ])
        ->get();
    

        $data = $users->map(function ($u) {
            $pivot = $u->maofisi->first()?->pivot;
            $positionId = $pivot?->position_id;
            $cheo = Position::find($positionId)?->name ?? 'Afisa';

            return [
                'id' => $u->id,
                'mobile' => $u->mobile,
                'jina_kamili' => $u->jina_kamili,
                'jina_mdhamini' => $u->jina_mdhamini,
                'simu_mdhamini' => $u->simu_mdhamini,
                'picha' => $u->picha,
                'username' => $u->username,
                'anakoishi' => $u->anakoishi,
                'is_manager' => $u->is_manager,
                'is_admin' => $u->is_admin,
                'is_active' => (bool) $u->is_active,
                'created_at' => $u->created_at?->toDateTimeString(),
                'updated_at' => $u->updated_at?->toDateTimeString(),

                'position' => $cheo,
                'position_id' => $positionId,

                'customers' => $u->customer->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'jina' => $c->jina,
                        'jinaMaarufu' => $c->jinaMaarufu,
                        'jinsia' => $c->jinsia,
                        'anapoishi' => $c->anapoishi,
                        'simu' => $c->simu,
                        'kazi' => $c->kazi,
                        'picha' => $c->picha,
                        'ofisi_id' => $c->ofisi_id,
                        'user_id' => $c->user_id,
                        'created_at' => $c->created_at,
                        'updated_at' => $c->updated_at,
                    ];
                }),

                'active_loans' => $u->loans->map(function ($loan) {
                    return [
                        'id' => $loan->id,
                        'amount' => $loan->amount,
                        'riba' => $loan->riba,
                        'fomu' => $loan->fomu,
                        'total_due' => $loan->total_due,
                        'status' => $loan->status,
                        'kipindi_malipo' => $loan->kipindi_malipo,
                        'loan_type' => $loan->loan_type,
                        'jina_kikundi' => $loan->jina_kikundi,
                        'muda_malipo' => $loan->muda_malipo,
                        'issued_date' => $loan->issued_date
                            ? (is_string($loan->issued_date) ? $loan->issued_date : $loan->issued_date->toDateTimeString())
                            : null,
                        'due_date' => $loan->due_date
                            ? (is_string($loan->due_date) ? $loan->due_date : $loan->due_date->toDateTimeString())
                            : null,
                        'status_details' => $loan->status_details,

                        'customers' => $loan->customers->map(function ($c) {
                            return [
                                'id' => $c->id,
                                'jina' => $c->jina,
                                'jinaMaarufu' => $c->jinaMaarufu,
                                'jinsia' => $c->jinsia,
                                'anapoishi' => $c->anapoishi,
                                'simu' => $c->simu,
                                'kazi' => $c->kazi,
                                'picha' => $c->picha,
                                'ofisi_id' => $c->ofisi_id,
                                'user_id' => $c->user_id,
                                'created_at' => $c->created_at,
                                'updated_at' => $c->updated_at,
                            ];
                        }),
                    ];
                }),
            ];
        });

        $positions = Position::select('id', 'name')->get();

        return response()->json([
            'users' => $data,
            'positions' => $positions,
        ], 200);
    }

    public function futaMtumishi(OfisiRequest $request)
    {
        $user = Auth::user();
        $helpNumber = env('APP_HELP');

        if (!$user) {
            throw new \Exception("Kuna tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
        }

        $activeOfisi = $user->activeOfisi;

        if (!$activeOfisi) {
            throw new \Exception("Kuna tatizo. Huna usajili kwenye ofisi yeyote. Piga simu msaada {$helpNumber}");
        }

        // Get user position in the current office
        $userOfisi = UserOfisi::where('user_id', $user->id)
                            ->where('ofisi_id', $activeOfisi->ofisi_id)
                            ->first();

        if (!$userOfisi || !$userOfisi->ofisi) {
            throw new \Exception("Hatukuweza kupata taarifa za ofisi yako kikamilifu.");
        }

        $positionRecord = Position::find($userOfisi->position_id);

        if (!$positionRecord) {
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kukamilisha hichi kitendo.");
        }

        // Validate target user ID
        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za mtumiaji hazijawasilishwa ipasavyo.',
                'errors' => $validator->errors()
            ], 400);
        }

        // Find target user within the same office
        $targetUserOfisi = UserOfisi::where('user_id', $request->userId)
                                    ->where('ofisi_id', $userOfisi->ofisi_id)
                                    ->first();

        if (!$targetUserOfisi) {
            return response()->json([
                'message' => 'Mtumiaji huyu hajajiunga na ofisi yako.',
            ], 404);
        }

        // Update pivot table status to 'denied'
        $targetUserOfisi->position_id = null;
        $targetUserOfisi->status = 'denied';
        $targetUserOfisi->save();

        return response()->json([
            'message' => 'Mtumishi amefutwa kwa mafanikio kutoka ofisi yako.',
        ], 200);
    }

    public function badiliCheo(OfisiRequest $request)
    {
        $user = Auth::user();
        $helpNumber = env('APP_HELP');

        if (!$user) {
            throw new \Exception("Kuna tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
        }

        $activeOfisi = $user->activeOfisi;

        if (!$activeOfisi) {
            throw new \Exception("Kuna tatizo. Huna usajili kwenye ofisi yeyote. Piga simu msaada {$helpNumber}");
        }

        $userOfisi = UserOfisi::where([
            'user_id' => $user->id,
            'ofisi_id' => $activeOfisi->ofisi_id,
        ])->first();

        if (!$userOfisi || !$userOfisi->ofisi) {
            throw new \Exception("Hatukuweza kupata taarifa za ofisi yako kikamilifu.");
        }

        // Check if user has a valid position (is a leader)
        if (!Position::find($userOfisi->position_id)) {
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kukamilisha hichi kitendo.");
        }

        // Validate incoming request
        $validated = Validator::make($request->all(), [
            'userId' => 'required|integer|exists:users,id',
            'positionId' => 'required|integer',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Taarifa za mtumiaji hazijawasilishwa ipasavyo.',
                'errors' => $validated->errors(),
            ], 400);
        }

        // Check if target user is in the same office
        $targetUserOfisi = UserOfisi::where([
            'user_id' => $request->userId,
            'ofisi_id' => $userOfisi->ofisi_id,
        ])->first();

        if (!$targetUserOfisi) {
            return response()->json([
                'message' => 'Mtumiaji huyu hajajiunga na ofisi yako.',
            ], 404);
        }

        // Update position (null if 0)
        $targetUserOfisi->position_id = $request->positionId == 0 ? null : $request->positionId;
        $targetUserOfisi->save();

        return response()->json([
            'message' => 'Mtumishi amebadilishiwa cheo kikamilifu.',
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
