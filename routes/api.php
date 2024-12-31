<?php

use App\Http\Controllers\Api\OfisiController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('logWithAccessToken', [OfisiController::class, 'logWithAccessToken']);
    Route::post('logout', [OfisiController::class, 'logout']);
});

Route::post('/upload', [UploadController::class, 'uploadImage']);
Route::get('getOfisiByLocation', [OfisiController::class, 'getOfisiByLocation']);
Route::get('validateNewRegisterRequest', [OfisiController::class, 'validateNewRegisterRequest']);
Route::get('validateOldRegisterRequest', [OfisiController::class, 'validateOldRegisterRequest']);
Route::post('registerMtumishiNewOfisi', [OfisiController::class, 'registerMtumishiNewOfisi']);
Route::post('registerMtumishiOldOfisi', [OfisiController::class, 'registerMtumishiOldOfisi']);
Route::get('login', [OfisiController::class, 'login']);
