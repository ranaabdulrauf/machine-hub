<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WMFController;
use App\Http\Controllers\FrankeController;

Route::get('/', function () {
    return view('welcome');
});

// Webhook routes for suppliers that support webhooks
Route::group(['prefix' => 'webhook'], function () {
    // WMF webhook routes with subscription verification
    Route::match(['POST', 'OPTIONS'], '/wmf/{tenant}', [WMFController::class, 'handle'])
        ->middleware('verify.subscription')
        ->where('tenant', '[a-zA-Z0-9_-]+');

    // Franke webhook routes (different verification approach)
    Route::post('/franke/{tenant}', [FrankeController::class, 'handle'])
        ->where('tenant', '[a-zA-Z0-9_-]+');
});
