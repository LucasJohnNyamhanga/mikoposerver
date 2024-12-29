<?php

use App\Http\Controllers\Api\OfisiController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
});

Route::post('/upload', [UploadController::class, 'uploadImage']);
Route::get('getOfisiByLocation', [OfisiController::class, 'getOfisiByLocation']);
Route::post('validateNewRegisterRequest', [OfisiController::class, 'validateNewRegisterRequest']);
