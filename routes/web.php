<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WMFController;
use App\Http\Controllers\FrankeController;

Route::get('/', function () {
    return view('welcome');
});

// Webhook routes for suppliers that support webhooks
Route::group(['prefix' => 'webhook'], function () {
    // WMF webhook routes
    Route::post('/wmf/{tenant}', [WMFController::class, 'handle'])
        ->where('tenant', '[a-zA-Z0-9_-]+');

    // Franke webhook routes
    Route::post('/franke/{tenant}', [FrankeController::class, 'handle'])
        ->where('tenant', '[a-zA-Z0-9_-]+');
});
