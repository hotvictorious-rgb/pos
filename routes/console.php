<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\BackupController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-Backup Schedule: runs daily at midnight, keeping only the last 7 days.
Schedule::call(function () {
    BackupController::generateBackup('System Auto-Backup');
})->daily();
