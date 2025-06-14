<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function registerMtumishiNewOfisi(AuthRequest $request)
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
            $helpNumber = env('APP_HELP');

            $this->sendNotification(
                    "Hongera, karibu kwenye mfumo wa {$appName}, umefanikiwa kufungua akaunti ya ofisi ya {$ofisi->jina} iliyopo mkoa wa {$ofisi->mkoa}, wilaya ya {$ofisi->wilaya} kwenye kata ya {$ofisi->kata}, kwa sasa wewe unawadhifa wa {$cheo} kwenye ofisi hii. mabadiliko ya uwendeshaji wa ofisi unaweza kuyafanya kwenye menu ya mfumo kupitia Usimamizi. Kwa msaada piga simu namba {$helpNumber}, Asante.",
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

    

    public function validateNewRegisterRequest(AuthRequest $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15|unique:users,mobile', // Ensures unique phone number
            'makazi' => 'required|string|max:255',
            'jinaMtumiaji' => 'required|string|max:255|unique:users,username', // Ensures unique username
            'jinaOfisi' => 'required|string|max:255',
            'mkoa' => 'required|string|max:255',
            'wilaya' => 'required|string|max:255',
            'kata' => 'required|string|max:255',
        ], [
            'simu.unique' => 'Namba ya simu imeshatumika kwa mtumishi mwingine', // Custom message for phone uniqueness
            'jinaMtumiaji.unique' => 'Jina la mtumiaji limeshatumika kwa mtumishi mwingine', // Custom message for username uniqueness
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 400);
        }

        // Check for unique combination of 'jinaOfisi', 'mkoa', 'wilaya', 'kata'
        $ofisiExists = DB::table('ofisis')
            ->where('jina', $request->jinaOfisi)
            ->where('mkoa', $request->mkoa)
            ->where('wilaya', $request->wilaya)
            ->where('kata', $request->kata)
            ->exists();

        if ($ofisiExists) {
            return response()->json([
                'message' => "Jina la ofisi ya {$request->jinaOfisi} limeshatumika katika mkoa wa {$request->mkoa}, wilaya ya {$request->wilaya}, kata ya {$request->kata}"
            ], 400);
        }

        // If all validations pass
        return response()->json([
            'message' => 'Mteja anafaa kusajiliwa, anza ku-upload image'
        ], 200);
    }


    public function validateOldRegisterRequest(AuthRequest $request)
    {
        // Validate the incoming request with custom error messages
        $validator = Validator::make($request->all(), [
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15|unique:users,mobile', // Ensures the phone number is unique
            'makazi' => 'required|string|max:255',
            'jinaMtumiaji' => 'required|string|max:255|unique:users,username', // Ensures the username is unique
            'ofisiId' => 'required|integer', 
        ], [
            'simu.unique' => 'Namba ya simu imeshatumika tayari. Tafadhali tumia namba nyingine.', // Custom message for the 'unique' validation on 'simu'
            'jinaMtumiaji.unique' => 'Jina la mtumiaji limeshatumika tayari. Tafadhali tumia jina lingine.', // Custom message for the 'unique' validation on 'jinaMtumiaji'
        ]);

        // Return validation errors if they exist
        if ($validator->fails()) {

            // If there are no specific messages, use the default message
            $message = 'Jaza maeneo yote yaliyowazi kuendelea';

            return response()->json([
                'message' => $message,
            ], 400);
        }

        // Check if ofisi exists using Eloquent, this is just for consistency as you have already used it
        $ofisi = Ofisi::find($request->ofisiId);
        if (!$ofisi) {
            return response()->json([
                'message' => "Ofisi uliyoichagua haipo au imefutwa"
            ], 400);
        }

        // If all validations pass
        return response()->json([
            'message' => 'Mteja anafaa kusajiliwa, anza ku-upload image'
        ], 200);
    }


    public function registerMtumishiOldOfisi(AuthRequest $request)
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
            $helpNumber = env('APP_HELP');

            $this->sendNotification(
                    "Hongera, karibu kwenye mfumo wa {$appName}, umefanikiwa kufungua akaunti ya ofisi ya {$ofisi->jina} iliyopo mkoa wa {$ofisi->mkoa}, wilaya ya {$ofisi->wilaya} kwenye kata ya {$ofisi->kata}, kwa sasa wewe unawadhifa wa Mtumishi kwenye ofisi hii. tunakutakia kazi njema. Kwa msaada piga simu namba {$helpNumber}, Asante.",
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

    public function login(AuthRequest $request)
    {
        // Validate input fields
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Jaza nafasi zote zilizo wazi.', 'errors' => $validator->errors()], 400);
        }
        
        $username = $request->input('username');
        $password = $request->input('password');

        // Find the user by username
        $user = User::where("username", $username)->first();

        if (!$user) {
            // Return error if user is not found
            return response()->json(['message' => 'Jina au neno la siri uliotumia, siyo sahihi.'], 401);
        }

        $passwordDatabase = $user->password;
        if ($password == $passwordDatabase) {
            $token = $user->createToken('auth_token')->plainTextToken;

            // Return user and token if successful
            return response()->json([
                'user' => $user,
                'token' => $token,
            ], 200);
    
        } else {
            return response()->json(['message' => 'Jina au neno la siri uliotumia, siyo sahihi.'], 401);
        }
    }

    public function logWithAccessToken(AuthRequest $request)
    {
        // Retrieve the user with their active Kikundi details
        $user = User::where('id', Auth::id())->first();

        if (!$user) {
            return response()->json(['message' => 'Uhakiki wa utumishi wako umeshindikana, wasiliana na 0784477999.'], 404);
        }
        return response()->json([
            'user' => $user,
        ], 200);
    }


    public function logout(AuthRequest $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->json(['message'=> 'logout'],200);
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
