<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Ofisi;
use App\Http\Requests\MiamalaRequest;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Position;
use App\Models\Transaction;
use App\Models\TransactionChange;
use App\Models\UserOfisi;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule as ValidationRule;

class TransactionChangeController extends Controller
{
    public function haliliMwamala(MiamalaRequest $request)
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
            'mwamalaId' => 'required|integer|exists:transactions,id',
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
            'actionType' => [
                'required',
                'string',
                ValidationRule::in(['edit', 'delete']),
            ],
            'changesDetails' => 'required|string|max:255',
            'reason' => 'required|string|max:255',
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Taarifa za mwamala hazijawasilishwa ipasavyo',
                'errors'  => $validator->errors()
            ], 400);
        }

        $transaction = Transaction::find($request->mwamalaId);

        if ($transaction->ofisi_id != $ofisi->id) {
            throw new \Exception("Huna ruhusa ya kuhalili mwamala huu.");
        }

        DB::beginTransaction();

        try {
            // Create the transaction record
            $transactionChange = TransactionChange::create([
                'type' => $transaction->type,
                'category' => $transaction->category,
                'status' => 'completed',
                'method' => $request->method,
                'amount' => $request->amount,
                'changes_details' => $request->changesDetails,
                'created_by' => $user->id,
                'user_id' => $user->id,
                'approved_by' => $user->id,
                'ofisi_id' => $ofisi->id,
                'admin_details' => null,
                'action_type' => $request->actionType,
                'transaction_id' => $transaction->id,
                'reason' => $request->reason,
            ]);

            // Send notifications
            $this->sendNotification(
                "Ombi la kuhariri mwamala wa Tsh {$transaction->amount} wa {$transaction->category} kwenda Tsh {$transactionChange->amount} yamepokelewa. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $user->id,
                null,
                $ofisi->id
            );

            $this->sendNotificationKwaViongoziWengine(
                "Ombi la kuhariri mwamala wa Tsh {$transaction->amount} wa {$transaction->category} kwenda Tsh {$transactionChange->amount} limewasilishwa na {$cheo} {$user->jina_kamili} mwenye namba {$user->mobile}. Unaweza kulifanyia mabadiliko kupitia menu kuu -> Usimamizi -> Marekebisho Miamala. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Ombi la kuhalili limesajiliwa kikamilifu.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ombi limeshindikana kupokelewa. Jaribu tena baadaye.',
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
