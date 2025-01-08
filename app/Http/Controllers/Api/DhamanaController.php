<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DhamanaRequest;
use App\Models\Customer;
use App\Models\Dhamana;
use App\Models\Loan;
use App\Models\Message;
use App\Models\Ofisi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DhamanaController extends Controller
{
    public function sajiliDhamana(DhamanaRequest $request)
    {
        DB::beginTransaction();

        try {
        // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'jina' => 'required|string|max:255',
                'thamani' => 'required|numeric|min:0',
                'maelezo' => 'required|string|max:255',
                'picha' => 'required|string|max:255',
                'loanId' => 'required|exists:loans,id',
                'customerId' => 'required|exists:customers,id',
                'ofisiId' => 'required|exists:ofisis,id',
            ]);

            $appName = env('APP_NAME');
            $helpNumber = env('APP_HELP');

            // If validation fails, return a response with error messages
            if ($validator->fails()) {

                throw new \Exception("Jaza maeneo yote yaliyowazi kuendelea.");
            }

            $user = Auth::user();

            if (!$user) {
                throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
            }

            $ofisi = Ofisi::find($request->ofisiId);

            if (!$ofisi) {
                throw new \Exception("Ofisi uliyoichagua haipo au imefutwa");
            }

            $loan = Loan::find($request->loanId);

            if (!$loan) {
                throw new \Exception("Mkopo ulio wasilisha haupo au umefutwa");
            }

            $mteja = Customer::find($request->customerId);
            if (!$mteja) {
                throw new \Exception("Mteja ulio mwasilisha hayupo au amefutwa");
            }
        
            $dhamana = Dhamana::create([
                'jina' => $request->jina,
                'thamani' => $request->thamani,
                'maelezo' => $request->maelezo,
                'picha' => $request->picha,
                'loan_id' => $request->loanId,
                'customer_id' => $request->customerId,
                'ofisi_id' => $request->ofisiId,
                'is_ofisi_owned' => false,
            ]);

            $this->sendNotification(
                    "Hongera, dhamana {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} ya mteja {$mteja->jina} imesajiliwa kikamilifu kwa mkopo wa Tsh {$loan->amount}, kwa msaada piga simu namba {$helpNumber}, Asante.",
                    $user->id,
                    null,
                    $ofisi->id,
                );

                $this->sendNotificationKwaViongoziWengine("Dhamana mpya ya {$dhamana->jina} yenye thamani ya Tsh {$dhamana->thamani} ya mteja {$mteja->jina} imesajiliwa kikamilifu kwa ajili ya mkopo wa Tsh {$loan->amount}. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}, Asante.", $ofisi->id, $user->id);

            DB::commit();

            return response()->json(['message' => 'Dhamana ya mteja imesajiliwa kikamilifu'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            $baseUrl = env('APP_URL');
            $imagePath = $request->picha;
            
            $filePathImage = trim(str_replace($baseUrl, '', $imagePath));
            $filePath = public_path($filePathImage);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            return response()->json([
                'message' => 'Hitilafu : ' . $e->getMessage()
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
