<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register custom commands
Artisan::command('supplier:create {name} {--mode=webhook} {--controller=} {--force}', function () {
    $command = new \App\Console\Commands\CreateSupplierCommand();
    $command->setLaravel($this->laravel);
    $command->setInput($this->input);
    $command->setOutput($this->output);
    return $command->handle();
})->purpose('Create a new supplier with adapter and optional controller');

Artisan::command('supplier:list', function () {
    $command = new \App\Console\Commands\ListSuppliersCommand();
    $command->setLaravel($this->laravel);
    $command->setInput($this->input);
    $command->setOutput($this->output);
    return $command->handle();
})->purpose('List all registered suppliers');
