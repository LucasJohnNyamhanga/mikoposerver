<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AinaRequest;
use App\Models\Aina;
use App\Models\Position;
use App\Models\UserOfisi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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

    public function futaKato(AinaRequest $request)
    {
        $user = Auth::user();
        $helpNumber = env('APP_HELP', '0767 887 999'); // fallback just in case

        if (!$user) {
            throw new HttpResponseException(response()->json([
                'message' => "Kuna Tatizo. Tumeshindwa kukupata kwenye database yetu. Piga simu msaada {$helpNumber}"
            ], 401));
        }

        if (!$user->activeOfisi) {
            throw new HttpResponseException(response()->json([
                'message' => "Kuna Tatizo. Huna usajili kwenye ofisi yeyote. Piga simu msaada {$helpNumber}"
            ], 403));
        }

        $userOfisi = UserOfisi::where('user_id', $user->id)
                            ->where('ofisi_id', $user->activeOfisi->ofisi_id)
                            ->first();

        if (!$userOfisi || !$userOfisi->ofisi) {
            throw new HttpResponseException(response()->json([
                'message' => "Hatukuweza kuthibitisha ushiriki wako kwenye ofisi hii."
            ], 403));
        }

        $ofisiId = $userOfisi->ofisi->id;

        $userOfisiPivot = $user->maofisi->where('id', $ofisiId)->first();

        if (!$userOfisiPivot || !$userOfisiPivot->pivot) {
            throw new HttpResponseException(response()->json([
                'message' => "Hatukuweza kuthibitisha nafasi yako ya uongozi."
            ], 403));
        }

        $positionId = $userOfisiPivot->pivot->position_id;
        $positionRecord = Position::find($positionId);

        if (!$positionRecord) {
            throw new HttpResponseException(response()->json([
                'message' => "Wewe sio kiongozi wa ofisi, huna uwezo wa kukamilisha hichi kitendo."
            ], 403));
        }

        $validator = Validator::make($request->all(), [
            'katoId' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Id ya kato imeshindwa kupatikana',
                'errors' => $validator->errors()
            ], 400);
        }

        $kato = Aina::find($request->input('katoId'));

        if (!$kato) {
            return response()->json(['message' => 'Kato hilo halipo kwenye mfumo.'], 404);
        }

        $kato->delete();

        return response()->json(['message' => 'Kato limefanikiwa kufutwa'], 200);
    }

}
