<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::post('coffee-machine/{supplier}/webhook', [WebhookController::class, 'handle'])->middleware(['verify.subscription', 'throttle:30,1']);
