<?php

use hexa_package_smp_wordpress_tts\Http\Controllers\SmpWordPressTtsApiController;
use hexa_package_smp_wordpress_tts\Http\Controllers\SmpWordPressTtsController;
use Illuminate\Support\Facades\Route;

Route::prefix("api/smp-wordpress-tts/v1")->name("smp-wordpress-tts.api.")->group(function () {
    Route::post("/status", [SmpWordPressTtsApiController::class, "status"])->name("status");
    Route::post("/synthesize", [SmpWordPressTtsApiController::class, "synthesize"])->name("synthesize");
    Route::get("/requests/{publicId}", [SmpWordPressTtsApiController::class, "showRequest"])->name("requests.show");
});

Route::middleware(["web", "auth", "locked", "system_lock", "two_factor", "role"])->group(function () {
    Route::get("/smp-wordpress-tts", [SmpWordPressTtsController::class, "dashboard"])->name("smp-wordpress-tts.dashboard");
    Route::get("/smp-wordpress-tts/settings", [SmpWordPressTtsController::class, "settings"])->name("smp-wordpress-tts.settings");
    Route::post("/smp-wordpress-tts/settings", [SmpWordPressTtsController::class, "saveSettings"])->name("smp-wordpress-tts.settings.save");
    Route::post("/smp-wordpress-tts/settings/test/{provider}", [SmpWordPressTtsController::class, "testProvider"])->name("smp-wordpress-tts.settings.test");

    Route::get("/smp-wordpress-tts/requests", [SmpWordPressTtsApiController::class, "adminHistory"])->name("smp-wordpress-tts.requests");
    Route::get("/smp-wordpress-tts/provider-keys/{provider}", [SmpWordPressTtsApiController::class, "providerKeys"])->name("smp-wordpress-tts.provider-keys");
    Route::post("/smp-wordpress-tts/provider-keys/{provider}", [SmpWordPressTtsApiController::class, "addProviderKey"])->name("smp-wordpress-tts.provider-keys.add");
    Route::post("/smp-wordpress-tts/provider-keys/{provider}/active", [SmpWordPressTtsApiController::class, "setActiveProviderKey"])->name("smp-wordpress-tts.provider-keys.active");
    Route::post("/smp-wordpress-tts/provider-keys/{provider}/test", [SmpWordPressTtsApiController::class, "testProviderKey"])->name("smp-wordpress-tts.provider-keys.test");
    Route::post("/smp-wordpress-tts/site-keys", [SmpWordPressTtsApiController::class, "generateSiteKey"])->name("smp-wordpress-tts.site-keys.generate");

    Route::post("/smp-wordpress-tts/accounts", [SmpWordPressTtsController::class, "accounts"])->name("smp-wordpress-tts.accounts");
    Route::post("/smp-wordpress-tts/installs", [SmpWordPressTtsController::class, "installs"])->name("smp-wordpress-tts.installs");
    Route::post("/smp-wordpress-tts/scan", [SmpWordPressTtsController::class, "scan"])->name("smp-wordpress-tts.scan");
    Route::post("/smp-wordpress-tts/push-credentials", [SmpWordPressTtsController::class, "pushCredentials"])->name("smp-wordpress-tts.push-credentials");
    Route::post("/smp-wordpress-tts/update-plugin", [SmpWordPressTtsController::class, "updatePlugin"])->name("smp-wordpress-tts.update-plugin");
});
