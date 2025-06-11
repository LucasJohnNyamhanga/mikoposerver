<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AinaRequest;
use App\Models\Aina;
use App\Models\Position;
use App\Models\UserOfisi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AinaController extends Controller
{
    public function sajiliMakato(AinaRequest $request)
    {

        $validator = Validator::make($request->all(), [
            'jina' => 'required|string|max:255',
            'ofisiId' => 'required|integer',
            'riba' => 'required|integer',
            'fomu' => 'required|integer',
            'loanType' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Jaza nafasi zote zilizo wazi', 'errors' => $validator->errors()], 400);
        }

        $jina = $request->input('jina');
        $ofisiId = $request->input('ofisiId');
        $riba = $request->input('riba');
        $fomu = $request->input('fomu');
        $loanType = $request->input('loanType');

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

        $ofisi = Aina::create([
            'jina' => $jina,
            'riba' => $riba,
            'fomu' => $fomu,
            'loan_type' => $loanType,
            'ofisi_id' => $ofisi->id,
        ]);

        return response()->json(['message' => 'Aina ya mkopo umetengenezwa'], 200);

    }

    public function getAinaMakato(AinaRequest $request)
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
            

            $ainaMakato = Aina::where('id', $ofisi->id)
                        ->get();
            
            return response()->json([
            'makato' => $ainaMakato,
            ], 200);
        }

        return response()->json(['message' => 'Huna usajili kwenye ofisi hii. Piga simu msaada 0784477999'], 401);
    }
}
