<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AinaRequest;
use App\Models\Aina;
use App\Models\Ofisi;
use Illuminate\Support\Facades\Validator;

class AinaController extends Controller
{
    public function storeAinaMkopo(AinaRequest $request)
    {

        $validator = Validator::make($request->all(), [
            'jina' => 'required|string|max:255',
            'ofisiId' => 'required|integer',
            'riba' => 'required|integer',
            'kipindiMalipo' => 'required|integer',
            'fomu' => 'required|integer',
            'mudaMalipo' => 'required|integer',
            'loanType' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Jaza nafasi zote zilizo wazi', 'errors' => $validator->errors()], 400);
        }

        $jina = $request->input('jina');
        $ofisiId = $request->input('ofisiId');
        $riba = $request->input('riba');
        $kipindiMalipo = $request->input('kipindiMalipo');
        $fomu = $request->input('fomu');
        $mudaMalipo = $request->input('mudaMalipo');
        $loanType = $request->input('loanType');

        $office = Ofisi::find($ofisiId);

        // Check if the customer exists
        if (!$office) {
            return response()->json(['message' => 'Ofisi unayoiboresha haipo au imefutwa'], 404);
        }

        $ofisi = Aina::create([
            'jina' => $jina,
            'riba' => $riba,
            'fomu' => $fomu,
            'kipindi_malipo' => $kipindiMalipo,
            'muda_malipo' => $mudaMalipo,
            'loan_type' => $loanType,
            'ofisi_id' => $office->id,
        ]);

        return response()->json(['message' => 'Aina ya mkopo umetengenezwa'], 200);

    }
}
