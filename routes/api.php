<?php

use App\Http\Controllers\Api\AinaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OfisiController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('logWithAccessToken', [AuthController::class, 'logWithAccessToken']);
    Route::get('logout', [AuthController::class, 'logout']);
    Route::get('getOfisiData', [OfisiController::class, 'getOfisiData']);
    Route::post('sajiliMteja', [CustomerController::class, 'sajiliMteja']);
    Route::post('ondoaUnreadMeseji', [MessageController::class, 'ondoaUnreadMeseji']);
    Route::post('sajiliMakato', [AinaController::class, 'sajiliMakato']);
    Route::post('sajiliMkopo', [LoanController::class, 'sajiliMkopo']);
});

Route::post('/upload', [UploadController::class, 'uploadImage']);
Route::get('getOfisiByLocation', [OfisiController::class, 'getOfisiByLocation']);
Route::get('validateNewRegisterRequest', [AuthController::class, 'validateNewRegisterRequest']);
Route::get('validateOldRegisterRequest', [AuthController::class, 'validateOldRegisterRequest']);
Route::post('registerMtumishiNewOfisi', [AuthController::class, 'registerMtumishiNewOfisi']);
Route::post('registerMtumishiOldOfisi', [AuthController::class, 'registerMtumishiOldOfisi']);
Route::get('login', [AuthController::class, 'login']);
