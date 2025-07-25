<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\UserOfisi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function sajiliMteja(CustomerRequest $request)
    {
        DB::beginTransaction();
        try {
        // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'jina' => 'required|string|max:255',
                'jinaMaarufu' => 'required|string|max:15',
                'jinsia' => 'required|string|max:255',
                'anapoishi' => 'required|string', 
                'simu' => 'required|string|max:255|unique:customers,simu',
                'kazi' => 'required|string|max:255',
                'picha' => 'required|string|max:255', 
                'ofisiId' => 'required|integer',
            ], [
                'simu.unique' => 'Namba ya simu imeshatumika kwa mteja mwingine', // Custom message for username uniqueness
            ]);

            $appName = env('APP_NAME');
            $helpNumber = env('APP_HELP');

            // If validation fails, return a response with error messages
            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first()
                ], 400);
            }

            $user = Auth::user();

            if (!$user) {
                throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
            }

            $ofisi = Ofisi::find($request->ofisiId);

            if (!$ofisi) {
                throw new \Exception("Ofisi uliyoichagua haipo au imefutwa");
            }
        
            $mteja = Customer::create([
                'jina' => $request->jina,
                'jinaMaarufu' => $request->jinaMaarufu,
                'jinsia' => $request->jinsia,
                'anapoishi' => $request->anapoishi,
                'simu' => $request->simu,
                'kazi' => $request->kazi,
                'picha' => $request->picha,
                'ofisi_id' => $ofisi->id,
                'user_id' => $user->id,
            ]);

            $this->sendNotification(
                    "Hongera, mteja {$mteja->jina} amesajiliwa kikamilifu, anaweza kuombewa mkopo mpya au kutumika kama mdhamini wa mkopo. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}, Asante.",
                    $user->id,
                    null,
                    $ofisi->id,
                );

                $this->sendNotificationKwaViongoziWengine("Mteja mpya {$mteja->jina} amesajiliwa kikamilifu, anaweza kuombewa mkopo mpya au kutumika kama mdhamini wa mkopo. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.", $ofisi->id, $user->id);

            DB::commit();

            return response()->json(['message' => 'Akaunti ya mteja imetengenezwa kikamilifu'], 200);

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

    public function haririMteja(CustomerRequest $request)
    {
        DB::beginTransaction();
        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'mtejaId' => 'required|integer|exists:customers,id', // Add this
                'jina' => 'required|string|max:255',
                'jinaMaarufu' => 'required|string|max:15',
                'jinsia' => 'required|string|max:255',
                'anapoishi' => 'required|string', 
                'simu' => 'required|string|max:255|unique:customers,simu,' . $request->mtejaId, // exclude current mteja from unique check
                'kazi' => 'required|string|max:255',
                'picha' => 'required|string|max:255', 
            ], [
                'simu.unique' => 'Namba ya simu imeshatumika kwa mteja mwingine',
            ]);

            $appName = env('APP_NAME');
            $helpNumber = env('APP_HELP');

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first()
                ], 400);
            }

            $user = Auth::user();
            if (!$user) {
                throw new \Exception("Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}");
            }

            if (!$user->activeOfisi) {
                return response()->json(['message' => 'Huna usajili kwenye ofisi yeyote. Piga simu msaada 0784477999'], 401);
            }
            // Retrieve the KikundiUser record to get the position and the Kikundi details
            $userOfisi = UserOfisi::where('user_id', $user->id)
                                ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                                ->first();
    
            $ofisi = $userOfisi->ofisi;

            $mteja = Customer::find($request->mtejaId);
            if (!$mteja) {
                throw new \Exception("Mteja huyu hakupatikana");
            }

            $hasRestrictedLoan = $mteja->loans()
                ->whereIn('status', ['approved', 'defaulted'])
                ->exists();

            if ($hasRestrictedLoan) {
                return response()->json([
                    'message' => 'Huwezi hariri taarifa za mteja mwenye mkopo hai au anayedaiwa.'
                ], 400);
            }

            //mteja hatakiwi kuwa na mkopo aliodhamini ambao ['approved', 'defaulted']
            $hasRestrictedGuarantorLoan = $mteja->mikopoAliyodhamini()
                ->whereIn('status', ['approved', 'defaulted'])
                ->exists();

            if ($hasRestrictedGuarantorLoan) {
                return response()->json([
                    'message' => 'Huwezi hariri taarifa za mteja ambaye ni mdhamini wa mkopo hai au unaodaiwa.'
                ], 400);
            }


            // Update the specific customer
            $mteja->update([
                'jina' => $request->jina,
                'jinaMaarufu' => $request->jinaMaarufu,
                'jinsia' => $request->jinsia,
                'anapoishi' => $request->anapoishi,
                'simu' => $request->simu,
                'kazi' => $request->kazi,
                'picha' => $request->picha,
                'ofisi_id' => $ofisi->id,
                'user_id' => $user->id,
            ]);

            // Notify user and leaders
            $this->sendNotification(
                "Hongera, mteja {$mteja->jina} amehaririwa kikamilifu. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}, Asante.",
                $user->id,
                null,
                $ofisi->id,
            );

            $this->sendNotificationKwaViongoziWengine(
                "Mteja {$mteja->jina} amehaririwa kikamilifu. Asante kwa kutumia {$appName}, kwa msaada piga simu namba {$helpNumber}.",
                $ofisi->id,
                $user->id
            );

            DB::commit();

            return response()->json(['message' => 'Akaunti ya mteja imehaririwa kikamilifu'], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded image if error occurs
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
