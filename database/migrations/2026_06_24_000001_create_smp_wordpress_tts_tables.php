<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable("smp_wordpress_tts_site_keys")) {
            Schema::create("smp_wordpress_tts_site_keys", function (Blueprint $table): void {
                $table->id();
                $table->string("name")->nullable();
                $table->string("site_url", 2048)->nullable();
                $table->string("site_domain", 255)->nullable()->index();
                $table->unsignedBigInteger("whm_server_id")->nullable()->index();
                $table->unsignedBigInteger("wordpress_install_id")->nullable()->index();
                $table->string("account", 255)->nullable()->index();
                $table->string("credential_key", 120)->nullable();
                $table->string("api_key_hash", 64)->unique();
                $table->string("api_key_last4", 12)->nullable();
                $table->string("status", 32)->default("active")->index();
                $table->json("settings")->nullable();
                $table->timestamp("last_seen_at")->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable("smp_wordpress_tts_requests")) {
            Schema::create("smp_wordpress_tts_requests", function (Blueprint $table): void {
                $table->id();
                $table->string("public_id", 64)->unique();
                $table->unsignedBigInteger("site_key_id")->nullable()->index();
                $table->string("status", 32)->default("queued")->index();
                $table->string("status_message", 1024)->nullable();
                $table->string("provider", 80)->nullable()->index();
                $table->string("provider_key_id", 120)->nullable();
                $table->string("provider_key_last4", 12)->nullable();
                $table->string("site_url", 2048)->nullable();
                $table->string("site_domain", 255)->nullable()->index();
                $table->string("article_url", 2048)->nullable();
                $table->unsignedBigInteger("post_id")->nullable()->index();
                $table->unsignedBigInteger("wordpress_user_id")->nullable();
                $table->string("wordpress_user_login", 255)->nullable();
                $table->string("requester_ip", 64)->nullable();
                $table->longText("submitted_content")->nullable();
                $table->string("content_sha256", 64)->nullable()->index();
                $table->unsignedInteger("character_count")->default(0);
                $table->unsignedInteger("word_count")->default(0);
                $table->unsignedInteger("audio_bytes")->default(0);
                $table->string("audio_mime", 120)->nullable();
                $table->string("audio_archive_path", 2048)->nullable();
                $table->string("audio_archive_url", 2048)->nullable();
                $table->decimal("cost_usd", 12, 6)->nullable();
                $table->json("meta")->nullable();
                $table->timestamp("started_at")->nullable();
                $table->timestamp("finished_at")->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists("smp_wordpress_tts_requests");
        Schema::dropIfExists("smp_wordpress_tts_site_keys");
    }
};
