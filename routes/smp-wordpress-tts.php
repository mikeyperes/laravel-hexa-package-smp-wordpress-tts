<?php

use hexa_package_smp_wordpress_tts\Http\Controllers\SmpWordPressTtsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])->group(function () {
    Route::get('/smp-wordpress-tts', [SmpWordPressTtsController::class, 'dashboard'])->name('smp-wordpress-tts.dashboard');
    Route::get('/smp-wordpress-tts/settings', [SmpWordPressTtsController::class, 'settings'])->name('smp-wordpress-tts.settings');
    Route::post('/smp-wordpress-tts/settings', [SmpWordPressTtsController::class, 'saveSettings'])->name('smp-wordpress-tts.settings.save');
    Route::post('/smp-wordpress-tts/settings/test/{provider}', [SmpWordPressTtsController::class, 'testProvider'])->name('smp-wordpress-tts.settings.test');

    Route::post('/smp-wordpress-tts/accounts', [SmpWordPressTtsController::class, 'accounts'])->name('smp-wordpress-tts.accounts');
    Route::post('/smp-wordpress-tts/installs', [SmpWordPressTtsController::class, 'installs'])->name('smp-wordpress-tts.installs');
    Route::post('/smp-wordpress-tts/scan', [SmpWordPressTtsController::class, 'scan'])->name('smp-wordpress-tts.scan');
    Route::post('/smp-wordpress-tts/push-credentials', [SmpWordPressTtsController::class, 'pushCredentials'])->name('smp-wordpress-tts.push-credentials');
    Route::post('/smp-wordpress-tts/update-plugin', [SmpWordPressTtsController::class, 'updatePlugin'])->name('smp-wordpress-tts.update-plugin');
});
