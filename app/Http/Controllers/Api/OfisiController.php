<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfisiRequest;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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

    public function registerMtumishiNewOfisi(OfisiRequest $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15',
            'makazi' => 'required|string|max:255',
            'picha' => 'required|string', 
            'jinaMdhamini' => 'required|string|max:255',
            'simuMdhamini' => 'required|string|max:255',
            'jinaMtumiaji' => 'required|string|max:255', 
            'password' => 'required|string|max:255', 
            'jinaOfisi' => 'required|string|max:255',
            'mkoa' => 'required|string|max:255',
            'wilaya' => 'required|string|max:255',
            'kata' => 'required|string|max:255',
        ]);

        // If validation fails, return a response with error messages
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'sms' => $validator->errors()->first()
            ], 400);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'jina_kamili' => $request->jinaKamili,
                'mobile' => $request->simu,
                'anakoishi' => $request->makazi,
                'picha' => $request->picha,
                'jina_mdhamini' => $request->jinaMdhamini,
                'simu_mdhamini' => $request->simuMdhamini,
                'username' => $request->jinaMtumiaji,
                'password' => $request->password,
            ]);


            $ofisi = Ofisi::create([
                'jina' => $request->jinaOfisi, 
                'mkoa' => $request->mkoa,
                'wilaya' => $request->wilaya,
                'kata' => $request->kata,
            ]);


            $user->maofisi()->attach($ofisi->id, [
                'position_id' => 1,
                'status' => 'accepted',
            ]);

            DB::table('actives')->updateOrInsert(
                ['user_id' => $user->id], 
                ['ofisi_id' => $ofisi->id, 'updated_at' => now(), 'created_at' => now()]
            );

            $ofisiYake = $user->maofisi->where('id', $ofisi->id)->first();

            $position = $ofisiYake->pivot->position_id;

            $positionRecord = Position::find($position);

            $cheo = $positionRecord->name;

            $appName = env('APP_NAME');

            $this->sendNotification(
                    "Hongera, karibu kwenye mfumo wa {$appName}, umefanikiwa kufungua akaunti ya ofisi ya {$ofisi->jina} iliyopo mkoa wa {$ofisi->mkoa}, wilaya ya {$ofisi->wilaya} kwenye kata ya {$ofisi->kata}, kwa sasa wewe unawadhifa wa {$cheo} kwenye ofisi hii. mabadiliko ya uwendeshaji wa ofisi unaweza kuyafanya kwenye menu ya mfumo kupitia Usimamizi. Kwa msaada piga simu namba 0784477999, Asante.",
                    $user->id,
                    null,
                    $ofisi->id,
                );

            DB::commit();

            return response()->json(['message' => 'Akaunti imetengenezwa, Ingia kwenye mfumo kuendelea.'], 200);
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

    

    public function validateNewRegisterRequest(OfisiRequest $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15',
            'makazi' => 'required|string|max:255',
            'jinaMtumiaji' => 'required|string|max:255',
            'jinaOfisi' => 'required|string|max:255',
            'mkoa' => 'required|string|max:255',
            'wilaya' => 'required|string|max:255',
            'kata' => 'required|string|max:255',
        ]);

        // Return validation errors if they exist
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Jaza maeneo yote yaliyowazi kuendelea'
            ], 400);
        }

        // Check for unique 'simu'
        if (DB::table('users')->where('mobile', $request->simu)->exists()) {
            return response()->json([
                'message' => 'Namba ya simu imeshatumika'
            ], 400);
        }

        // Check for unique 'jinaMtumiaji'
        if (DB::table('users')->where('username', $request->jinaMtumiaji)->exists()) {
            return response()->json([
                'message' => 'Jina la mtumiaji limeshatumika'
            ], 400);
        }

        // Check for unique combination of 'jinaOfisi', 'mkoa', 'wilaya', 'kata'
        if (DB::table('ofisis')
            ->where('jina', $request->jinaOfisi)
            ->where('mkoa', $request->mkoa)
            ->where('wilaya', $request->wilaya)
            ->where('kata', $request->kata)
            ->exists()) {
            return response()->json([
                'message' => 'Jina la ofisi limeshatumika katika eneo hili'
            ], 400);
        }

        // If all validations pass
        return response()->json([
            'message' => 'Mteja anafaa kusajiliwa, anza ku-upload image'
        ], 200);
    }

    public function validateOldRegisterRequest(OfisiRequest $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15',
            'makazi' => 'required|string|max:255',
            'jinaMtumiaji' => 'required|string|max:255',
            'ofisiId' => 'required|integer',
        ]);

        // Return validation errors if they exist
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Jaza maeneo yote yaliyowazi kuendelea'
            ], 400);
        }

        // Check for unique 'simu'
        if (DB::table('users')->where('mobile', $request->simu)->exists()) {
            return response()->json([
                'message' => 'Namba ya simu imeshatumika'
            ], 400);
        }

        // Check for unique 'jinaMtumiaji'
        if (DB::table('users')->where('username', $request->jinaMtumiaji)->exists()) {
            return response()->json([
                'message' => 'Jina la mtumiaji limeshatumika'
            ], 400);
        }

        // Check if ofisi exists
        $ofisi = Ofisi::find($request->ofisiId);

        if (!$ofisi) {
            return response()->json([
                'message' => 'Ofisi uliyoichagua haipo au imefutwa'
            ], 400);
        }

        // If all validations pass
        return response()->json([
            'message' => 'Mteja anafaa kusajiliwa, anza ku-upload image'
        ], 200);
    }

    public function registerMtumishiOldOfisi(OfisiRequest $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15',
            'makazi' => 'required|string|max:255',
            'picha' => 'required|string', 
            'jinaMdhamini' => 'required|string|max:255',
            'simuMdhamini' => 'required|string|max:255',
            'jinaMtumiaji' => 'required|string|max:255', 
            'password' => 'required|string|max:255', 
            'ofisiId' => 'required|integer',
        ]);

        // If validation fails, return a response with error messages
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Jaza maeneo yote yaliyowazi kuendelea'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'jina_kamili' => $request->jinaKamili,
                'mobile' => $request->simu,
                'anakoishi' => $request->makazi,
                'picha' => $request->picha,
                'jina_mdhamini' => $request->jinaMdhamini,
                'simu_mdhamini' => $request->simuMdhamini,
                'username' => $request->jinaMtumiaji,
                'password' => $request->password,
            ]);


            $ofisi = Ofisi::find($request->ofisiId);

            if (!$ofisi) {
                return response()->json([
                    'message' => 'Ofisi uliyoichagua haipo au imefutwa'
                ], 400);
            }

            // Check if the relationship already exists
            $exists = $user->maofisi()->where('ofisi_id', $ofisi->id)->exists();

            if ($exists) {
                // Update the status in the pivot table
                $user->maofisi()->updateExistingPivot($ofisi->id, ['status' => 'pending']);
            } else {
                // Attach if it doesn't exist
                $user->maofisi()->attach($ofisi->id, ['status' => 'pending']);
            }

            DB::table('actives')->updateOrInsert(
                ['user_id' => $user->id], 
                ['ofisi_id' => $ofisi->id, 'updated_at' => now(), 'created_at' => now()]
            );

            $appName = env('APP_NAME');

            $this->sendNotification(
                    "Hongera, karibu kwenye mfumo wa {$appName}, umefanikiwa kufungua akaunti ya ofisi ya {$ofisi->jina} iliyopo mkoa wa {$ofisi->mkoa}, wilaya ya {$ofisi->wilaya} kwenye kata ya {$ofisi->kata}, kwa sasa wewe unawadhifa wa Mtumishi kwenye ofisi hii. tunakutakia kazi njema. Kwa msaada piga simu namba 0784477999, Asante.",
                    $user->id,
                    null,
                    $ofisi->id,
                );

            DB::commit();

            return response()->json(['message' => 'Akaunti imetengenezwa, Ingia kwenye mfumo kuendelea.'], 200);
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
        $kikundi = Ofisi::find($ofisiId);
        $groups = $kikundi->positionsWithUsers()->get();
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
