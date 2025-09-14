<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use App\Models\Kifurushi;
use App\Models\KifurushiPurchase;
use App\Models\Message;
use App\Models\Ofisi;
use App\Models\Payment;
use App\Models\Position;
use App\Models\SmsBalance;
use App\Models\User;
use App\Models\UserOfisi;
use App\Services\BeemSmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{

    protected string $appName;
    protected string $helpNumber;
    protected string $app_url;
    public function __construct()
    {
        $this->appName = config('services.app.name');
        $this->helpNumber = config('services.help.number');
        $this->app_url = config('services.appurl.name');
    }
    public function registerMtumishiNewOfisi(AuthRequest $request, BeemSmsService $smsService)
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

        try {
            DB::beginTransaction();
            // Create user (password not hashed)
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

            // Create office
            $ofisi = Ofisi::create([
                'jina' => $request->jinaOfisi,
                'mkoa' => $request->mkoa,
                'wilaya' => $request->wilaya,
                'kata' => $request->kata,
            ]);

            // Attach user to office
            $user->maofisi()->attach($ofisi->id, [
                'position_id' => 1,
                'status' => 'accepted',
            ]);

            // Update actives table
            DB::table('actives')->updateOrInsert(
                ['user_id' => $user->id],
                ['ofisi_id' => $ofisi->id, 'updated_at' => now(), 'created_at' => now()]
            );

            // Send notification
            $cheo = $user->getCheoKwaOfisi($ofisi->id);
            $appName = $this->appName;
            $helpNumber = $this->helpNumber;
            $this->sendNotification(
                "Hongera, karibu kwenye mfumo wa {$appName}, umefanikiwa kufungua akaunti ya ofisi ya {$ofisi->jina} iliyopo mkoa wa {$ofisi->mkoa}, wilaya ya {$ofisi->wilaya} kwenye kata ya {$ofisi->kata}, kwa sasa wewe unawadhifa wa {$cheo} kwenye ofisi hii. Mabadiliko ya uwendeshaji wa ofisi unaweza kuyafanya kwenye menu ya mfumo kupitia Usimamizi. Kwa msaada piga simu namba {$helpNumber}, Asante.",
                $user->id,
                null,
                $ofisi->id,
            );

            // ✅ Get default package by name
            $kifurushi = Kifurushi::where('name', 'ILIKE', 'majaribio')->first();


            if ($kifurushi) {
                // ✅ Update SMS balance if it's SMS package
                    $this->updateSmsBalance($user, $kifurushi, $ofisi->id);

                // ✅ Create purchase record
                $this->createKifurushiPurchaseIfNotExists($user, $kifurushi, $ofisi->id);
            }
            // If $kifurushi not found, skip SMS & purchase update

            DB::commit();
            if ($kifurushi) {
                // ✅ Send SMS
                $recipients = [$user->mobile];
                $message = "Habari {$this->jina($user->jina_kamili)}! Karibu Mikopo Center. Akaunti yako imefunguliwa na umezawadiwa kifurushi cha bure cha siku {$kifurushi->duration_in_days} pamoja na SMS {$kifurushi->sms}. Timu yetu itakupigia kukuelekeza jinsi mfumo utakavyorahisisha usimamizi wa mikopo na kukuza biashara yako. Kwa msaada piga {$helpNumber}. Maisha ni rahisi kupitia mfumo wa kidigitali!";
                $senderId = "Datasoft";
                $smsService->sendSms($senderId, $message, $recipients, $user);
            }

            return response()->json([
                'message' => 'Akaunti imetengenezwa, Ingia kwenye mfumo kuendelea.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded picture if exists
            $filePath = public_path(trim(str_replace($this->app_url, '', $request->picha)));
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


    public function registerMtumishiOldOfisi(AuthRequest $request, BeemSmsService $smsService)
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

            $appName = $this->appName;
            $helpNumber = $this->helpNumber;

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
            $baseUrl = $this->app_url;
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

    public function registerMtumishi(AuthRequest $request)
    {
        $user = Auth::user();
        $helpNumber = $this->helpNumber;
        $appName = $this->appName;

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
            $msajiliwa = User::create([
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
                    'message' => "Ofisi yako ina changamoto kupatikana. Kwa msaada piga simu namba {$helpNumber}"
                ], 400);
            }

            // Check if the relationship already exists
            $exists = $msajiliwa->maofisi()->where('ofisi_id', $ofisi->id)->exists();

            if ($exists) {
                // Update the status in the pivot table
                $msajiliwa->maofisi()->updateExistingPivot($ofisi->id, ['status' => 'accepted']);
            } else {
                // Attach if it doesn't exist
                $msajiliwa->maofisi()->attach($ofisi->id, ['status' => 'accepted']);
            }

            DB::table('actives')->updateOrInsert(
                ['user_id' => $msajiliwa->id],
                ['ofisi_id' => $ofisi->id, 'updated_at' => now(), 'created_at' => now()]
            );

            $this->sendNotification(
                    "Hongera, umefanikiwa kufungua akaunti ya {$msajiliwa->jina_kamili} mwenye simu namba {$msajiliwa->mobile}, kwa sasa jina la mtumiajia analotumia ni {$msajiliwa->username} na neno lake la siri ni {$msajiliwa->password}. Kwa msaada piga simu namba {$helpNumber}, Asante.",
                    $user->id,
                    null,
                    $ofisi->id,
                );

            $this->sendNotification(
                "Hongera, karibu kwenye mfumo wa {$appName}, akaunti yako imefunguliwa kikamilifu na {$cheo} {$user->jina_kamili} mwenye simu namba {$user->mobile}, kwa sasa jina la mtumiajia unalotumia ni {$msajiliwa->username} na neno lako la siri ni {$msajiliwa->password}. Kwa msaada piga simu namba {$helpNumber}, Asante.",
                $msajiliwa->id,
                null,
                $ofisi->id,
            );

            DB::commit();

            return response()->json(['message' => 'Akaunti imetengenezwa kikamilifu.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            $baseUrl = $this->app_url;
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

    public function editMtumishi(AuthRequest $request)
    {
        $user = Auth::user();
        $helpNumber = $this->helpNumber;
        $appName = $this->appName;

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
            throw new \Exception("Wewe sio kiongozi wa ofisi, huna uwezo wa kuona watumishi.");
        }
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'mtumishiId' => 'required|exists:users,id',
            'jinaKamili' => 'required|string|max:255',
            'simu' => 'required|string|max:15',
            'makazi' => 'required|string|max:255',
            'picha' => 'required|string',
            'jinaMdhamini' => 'required|string|max:255',
            'simuMdhamini' => 'required|string|max:255',
            'jinaMtumiaji' => 'required|string|max:255',
            'password' => 'nullable|string|max:255', // make password optional
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Jaza maeneo yote yaliyowazi kuendelea'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $msajiliwa = User::findOrFail($request->mtumishiId);

            // Backup old image path in case we need to delete
            $oldImage = $msajiliwa->picha;

            // Update user fields
            $msajiliwa->jina_kamili = $request->jinaKamili;
            $msajiliwa->mobile = $request->simu;
            $msajiliwa->anakoishi = $request->makazi;
            $msajiliwa->picha = $request->picha;
            $msajiliwa->jina_mdhamini = $request->jinaMdhamini;
            $msajiliwa->simu_mdhamini = $request->simuMdhamini;
            $msajiliwa->username = $request->jinaMtumiaji;

            if (!empty($request->password)) {
                $msajiliwa->password = $request->password; // consider hashing here
            }

            $msajiliwa->save();

            $ofisi = Ofisi::find($ofisiId);

            // Sync or attach office if not linked yet
            $exists = $msajiliwa->maofisi()->where('ofisi_id', $ofisi->id)->exists();
            if ($exists) {
                $msajiliwa->maofisi()->updateExistingPivot($ofisi->id, ['status' => 'accepted']);
            } else {
                $msajiliwa->maofisi()->attach($ofisi->id, ['status' => 'accepted']);
            }

            // Update or insert active office
            DB::table('actives')->updateOrInsert(
                ['user_id' => $msajiliwa->id],
                ['ofisi_id' => $ofisi->id, 'updated_at' => now(), 'created_at' => now()]
            );

            // Send notifications
            $this->sendNotification(
                "Taarifa za mtumiaji {$msajiliwa->jina_kamili} zimehaririwa kikamilifu. Username ni {$msajiliwa->username}" .
                (!empty($request->password) ? " na neno la siri ni {$request->password}." : ".") .
                " Kwa msaada piga simu namba {$helpNumber}, Asante.",
                $user->id,
                null,
                $ofisi->id,
            );

            $this->sendNotification(
                "Taarifa zako zimeboreshwa kikamilifu. Username yako ni {$msajiliwa->username}" .
                (!empty($request->password) ? " na neno lako la siri ni {$request->password}." : ".") .
                " Kwa msaada piga simu namba {$helpNumber}, Asante.",
                $msajiliwa->id,
                null,
                $ofisi->id,
            );

            // Delete old image if changed

            $oldImage = $request->get('old_image'); // e.g. https://yourdomain/uploads/... or full S3 URL
            $disk = config('filesystems.default');
            $aws_url = config('filesystems.disks.s3.url'); //show me how to update env('AWS_URL') and env('AWS_BUCKET') and env('AWS_DEFAULT_REGION')

            if ($oldImage && $oldImage !== $request->picha) {
                if ($disk === 's3') {
                    // Strip base URL to get the relative path (after bucket domain or AWS_URL)
                    if (env('AWS_URL')) {
                        $prefix = rtrim(env('AWS_URL'), '/') . '/';
                    } else {
                        $prefix = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                    }

                    $s3Path = str_replace($prefix, '', $oldImage); // Get S3 file key
                    Storage::disk('s3')->delete($s3Path);

                } else {
                    // Local deletion
                    $baseUrl = config('app.url');
                    $relativePath = trim(str_replace($baseUrl, '', $oldImage), '/'); // e.g. uploads/mikopo/images/filename.jpg
                    $filePath = public_path($relativePath);

                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }

            DB::commit();

            return response()->json(['message' => 'Taarifa za mtumiaji zimeboreshwa kikamilifu.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete new image on failure
            $baseUrl = $this->app_url;
            $imagePath = $request->picha;
            $filePath = public_path(trim(str_replace($baseUrl, '', $imagePath)));
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
            'username'  => 'required|string',
            'password'  => 'required|string',
            'fcm_token' => 'nullable|string', // accept FCM token
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Jaza nafasi zote zilizo wazi.',
                'errors'  => $validator->errors()
            ], 400);
        }

        $username = $request->input('username');
        $password = $request->input('password');
        $fcmToken = $request->input('fcm_token');

        // Find the user by username
        $user = User::where("username", $username)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Jina au neno la siri uliotumia, siyo sahihi.'
            ], 401);
        }

        // Simple password check (replace with Hash::check if using hashed passwords)
        if ($password == $user->password) {

            // Update FCM token if provided
            if (!empty($fcmToken)) {
                $user->update(['fcm_token' => $fcmToken]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user'  => $user,
                'token' => $token,
            ], 200);

        } else {
            return response()->json([
                'message' => 'Jina au neno la siri uliotumia, siyo sahihi.'
            ], 401);
        }
    }


    public function logWithAccessToken(AuthRequest $request)
    {
        try {
            $helpNumber = config('services.help.number');
            // Retrieve the user with their active Kikundi details
            $user = User::where('id', Auth::id())->first();

            if (!$user) {
                return response()->json([
                    'message' => "Uhakiki wa utumishi wako umeshindikana, wasiliana na {$helpNumber}."
                ], 404);
            }

            return response()->json([
                'user' => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Kuna hitilafu imetokea. Tafadhali jaribu tena au wasiliana na msaada.',
                'error' => $e->getMessage() // (Optional) remove in production for security
            ], 500);
        }
    }



    public function logout(AuthRequest $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();

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

    protected function createKifurushiPurchaseIfNotExists(User $user, Kifurushi $kifurushi, int $ofisi_id): void
    {
        $now = now()->setTimezone('Africa/Nairobi');

        KifurushiPurchase::create([
            'user_id'       => $user->id,
            'kifurushi_id'  => $kifurushi->id,
            'status'        => 'approved',
            'start_date'    => $now->copy()->toDateTimeString(),
            'end_date'      => $now->copy()->addDays($kifurushi->duration_in_days)->toDateTimeString(),
            'is_active'     => true,
            'approved_at'   => $now->toDateTimeString(),
            'reference'     => 'Majaribio',
            'ofisi_id'      => $ofisi_id,
        ]);
    }

    /**
     * Update SMS balance when a user purchases SMS bundles.
     */
    protected function updateSmsBalance(User $user, Kifurushi $kifurushi, int $ofisi_id): void
    {
        $now = now()->setTimezone('Africa/Nairobi');
        $balance = SmsBalance::where('user_id', $user->id)
            ->where('ofisi_id', $ofisi_id)
            ->where('status', 'active')
            ->first();

        if ($balance) {
            // Add offered SMS to existing balance
            $balance->offered_sms = $kifurushi->sms;

            // Update expiry using addDays from package duration
            $balance->expires_at = $now->copy()->addDays($kifurushi->duration_in_days)->toDateString();
            $balance->save();
        } else {
            // Create a new SMS balance record
            SmsBalance::create([
                'user_id' => $user->id,
                'ofisi_id' => $ofisi_id,
                'used_sms' => 0,
                'offered_sms' => $kifurushi->sms,
                'bought_sms' => 0,
                'start_date' => $now->copy()->toDateString(),
                'expires_at' => $now->copy()->addDays($kifurushi->duration_in_days)->toDateString(),
                'status' => 'active',
                'sender_id' => null, // free SMS, no sender
                'phone' => $user->mobile,
            ]);
        }
    }

    protected function jina(string $jina): string
    {
        if (!$jina) {
            return '';
        }

        // Split by space and return the first part
        $parts = explode(' ', trim($jina));
        return $parts[0] ?? '';
    }

}
