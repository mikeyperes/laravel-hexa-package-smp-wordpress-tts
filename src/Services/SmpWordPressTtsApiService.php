<?php

namespace hexa_package_smp_wordpress_tts\Services;

use hexa_core\Models\Setting;
use hexa_core\Services\CredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SmpWordPressTtsApiService
{
    private const SITE_CREDENTIAL_SLUG = "smp-wordpress-tts-sites";
    private const PROVIDER_CREDENTIAL_SLUG = "smp-wordpress-tts";
    private const PROVIDER_META_PREFIX = "smp_wordpress_tts_keyring_";
    private const REQUEST_TABLE = "smp_wordpress_tts_requests";
    private const SITE_TABLE = "smp_wordpress_tts_site_keys";
    private const UNREAL_STREAM_ENDPOINT = "https://api.v8.unrealspeech.com/stream";
    private const UNREAL_COST_PER_CHARACTER = 0.000016333333;

    public function __construct(private CredentialService $credentials)
    {
    }

    public function providerDefinitions(): array
    {
        return [
            "unrealspeech" => [
                "label" => "UnrealSpeech",
                "summary" => "Primary low-cost article narration provider. The central API calls UnrealSpeech server-side so WordPress never exposes the vendor key.",
                "docs" => [
                    ["label" => "Dashboard", "url" => "https://unrealspeech.com/dashboard"],
                    ["label" => "API docs", "url" => "https://docs.v8.unrealspeech.com/"],
                    ["label" => "Stream endpoint", "url" => "https://docs.v8.unrealspeech.com/reference/stream"],
                    ["label" => "Pricing", "url" => "https://unrealspeech.com/pricing"],
                ],
                "instructions" => [
                    "Open the UnrealSpeech dashboard and sign in to the billing account that should pay for article narration.",
                    "Copy the API key from the dashboard. Use one named key per billing owner or publication group so usage remains traceable.",
                    "Add the key in this package keyring, give it a clear name, and set it active. Old keys can stay stored for quick rollback.",
                    "Use Test Key before making it active. The test sends a tiny /stream request and confirms audio bytes return.",
                    "Default /stream voice is af. Other documented /stream voices include af_bella, af_sarah, am_adam, am_michael, bf_emma, bf_isabella, bm_george, bm_lewis, af_nicole, and af_sky.",
                ],
                "voices" => ["af", "af_bella", "af_sarah", "am_adam", "am_michael", "bf_emma", "bf_isabella", "bm_george", "bm_lewis", "af_nicole", "af_sky"],
                "defaults" => ["voice" => "af", "bitrate" => "192k", "speed" => "0", "pitch" => "1", "codec" => "libmp3lame"],
            ],
            "elevenlabs" => [
                "label" => "ElevenLabs",
                "summary" => "Premium provider slot. Keyring-ready; synthesis can be enabled after cost/voice approval.",
                "docs" => [
                    ["label" => "API keys", "url" => "https://elevenlabs.io/app/settings/api-keys"],
                    ["label" => "Authentication", "url" => "https://elevenlabs.io/docs/api-reference/authentication"],
                ],
                "instructions" => ["Create a scoped ElevenLabs API key, add it to the provider keyring, and mark it active when approved."],
                "voices" => [],
                "defaults" => ["voice" => "", "model" => "eleven_multilingual_v2"],
            ],
        ];
    }

    public function profiles(): array
    {
        return [
            "default" => ["label" => "Default Article Narration", "provider" => "unrealspeech", "voice" => "af", "speed" => "0"],
            "news" => ["label" => "News Read", "provider" => "unrealspeech", "voice" => "af_sarah", "speed" => "0"],
            "male" => ["label" => "Male News Read", "provider" => "unrealspeech", "voice" => "am_michael", "speed" => "0"],
        ];
    }

    public function defaultProvider(): string
    {
        $provider = (string) Setting::getValue("smp_wordpress_tts_api_default_provider", "unrealspeech");
        return isset($this->providerDefinitions()[$provider]) ? $provider : "unrealspeech";
    }

    public function providerRuntimeSnapshot(): array
    {
        $snapshot = [];
        foreach ($this->providerDefinitions() as $providerId => $definition) {
            $active = $this->activeProviderKeyEntry($providerId);
            $snapshot[$providerId] = [
                "id" => $providerId,
                "label" => $definition["label"],
                "summary" => $definition["summary"],
                "configured" => is_array($active),
                "active_key_id" => $active["id"] ?? null,
                "active_key_name" => $active["name"] ?? null,
                "active_key_last4" => $active["last4"] ?? null,
                "voices" => $definition["voices"] ?? [],
                "defaults" => $definition["defaults"] ?? [],
                "docs" => $definition["docs"] ?? [],
                "instructions" => $definition["instructions"] ?? [],
            ];
        }

        return $snapshot;
    }

    public function statusForSite(object $site): array
    {
        $domain = (string) ($site->site_domain ?? "");
        $monthStart = now("America/New_York")->startOfMonth()->timezone("UTC");
        $usage = DB::table(self::REQUEST_TABLE)
            ->where("site_key_id", $site->id)
            ->where("created_at", ">=", $monthStart)
            ->selectRaw("COUNT(*) as requests, COALESCE(SUM(character_count),0) as characters, COALESCE(SUM(cost_usd),0) as cost")
            ->first();

        return [
            "success" => true,
            "message" => "Publish Scale Text-to-Speech API is connected, valid, and active.",
            "usable" => true,
            "site" => [
                "id" => $site->id,
                "name" => $site->name,
                "domain" => $domain,
                "status" => $site->status,
                "last_seen_at" => $site->last_seen_at,
            ],
            "default_provider" => $this->defaultProvider(),
            "profiles" => $this->profiles(),
            "available_apis" => $this->providerRuntimeSnapshot(),
            "usage" => [
                "period" => now("America/New_York")->format("Y-m"),
                "requests" => (int) ($usage->requests ?? 0),
                "characters" => (int) ($usage->characters ?? 0),
                "estimated_cost_usd" => round((float) ($usage->cost ?? 0), 6),
            ],
            "credit_balance" => [
                "status" => "provider_dashboard_required",
                "message" => "UnrealSpeech does not expose a balance endpoint in the public docs scanned here; check the UnrealSpeech dashboard for exact remaining credit.",
            ],
        ];
    }

    public function authenticate(Request $request): ?object
    {
        $key = (string) ($request->bearerToken() ?: $request->header("X-SMP-TTS-Key") ?: $request->input("api_key", ""));
        $key = trim($key);
        if ($key === "") {
            return null;
        }

        $site = DB::table(self::SITE_TABLE)
            ->where("api_key_hash", hash("sha256", $key))
            ->where("status", "active")
            ->first();

        if ($site) {
            DB::table(self::SITE_TABLE)->where("id", $site->id)->update(["last_seen_at" => now(), "updated_at" => now()]);
        }

        return $site ?: null;
    }

    public function generateSiteApiKey(array $target): array
    {
        $siteUrl = trim((string) ($target["site_url"] ?? $target["url"] ?? ""));
        $domain = $this->domainFromUrl($siteUrl);
        $installId = (int) ($target["wordpress_install_id"] ?? $target["install_id"] ?? 0);
        $account = trim((string) ($target["account"] ?? ""));

        $existing = DB::table(self::SITE_TABLE)
            ->when($domain !== "", fn ($query) => $query->where("site_domain", $domain))
            ->when($installId > 0, fn ($query) => $query->orWhere("wordpress_install_id", $installId))
            ->orderByDesc("id")
            ->first();

        if ($existing && is_string($existing->credential_key ?? null) && $existing->credential_key !== "") {
            $stored = $this->credentials->get(self::SITE_CREDENTIAL_SLUG, $existing->credential_key);
            if (is_string($stored) && $stored !== "") {
                return [
                    "success" => true,
                    "site_key_id" => $existing->id,
                    "api_key" => $stored,
                    "api_key_last4" => substr($stored, -4),
                    "message" => "Existing active API key reused.",
                ];
            }
        }

        $apiKey = "pstts_" . Str::random(48);
        $credentialKey = "site_api_key_" . Str::lower(Str::random(14));
        $id = DB::table(self::SITE_TABLE)->insertGetId([
            "name" => $target["name"] ?? ($domain ?: "WordPress Site"),
            "site_url" => $siteUrl ?: null,
            "site_domain" => $domain ?: null,
            "whm_server_id" => isset($target["server_id"]) ? (int) $target["server_id"] : null,
            "wordpress_install_id" => $installId ?: null,
            "account" => $account ?: null,
            "credential_key" => $credentialKey,
            "api_key_hash" => hash("sha256", $apiKey),
            "api_key_last4" => substr($apiKey, -4),
            "status" => "active",
            "settings" => json_encode(["created_by" => "smp-wordpress-tts-dashboard"], JSON_UNESCAPED_SLASHES),
            "created_at" => now(),
            "updated_at" => now(),
        ]);
        $this->credentials->store(self::SITE_CREDENTIAL_SLUG, $credentialKey, $apiKey);

        return [
            "success" => true,
            "site_key_id" => $id,
            "api_key" => $apiKey,
            "api_key_last4" => substr($apiKey, -4),
            "message" => "New site API key generated.",
        ];
    }

    public function addProviderKey(string $providerId, string $name, string $apiKey, bool $makeActive = true): array
    {
        $providerId = $this->normalizeProvider($providerId);
        $state = $this->providerKeyState($providerId);
        $id = "key_" . Str::lower(Str::random(12));
        $credentialKey = $providerId . "_api_key_" . $id;
        $timestamp = now()->toIso8601String();

        $this->credentials->store(self::PROVIDER_CREDENTIAL_SLUG, $credentialKey, $apiKey);
        $state["keys"][] = [
            "id" => $id,
            "name" => trim($name) !== "" ? Str::limit(trim($name), 255, "") : ucfirst($providerId) . " API Key",
            "credential_key" => $credentialKey,
            "created_at" => $timestamp,
            "updated_at" => $timestamp,
        ];
        if ($makeActive || empty($state["active_key_id"])) {
            $state["active_key_id"] = $id;
        }
        $this->persistProviderKeyState($providerId, $state);

        return ["success" => true, "keys" => $this->listProviderKeys($providerId), "active_key_id" => $state["active_key_id"]];
    }

    public function listProviderKeys(string $providerId): array
    {
        $providerId = $this->normalizeProvider($providerId);
        $state = $this->providerKeyState($providerId);
        return array_map(function (array $entry) use ($state): array {
            $raw = $this->credentials->get(self::PROVIDER_CREDENTIAL_SLUG, $entry["credential_key"]);
            return [
                "id" => $entry["id"],
                "name" => $entry["name"],
                "masked_key" => $this->credentials->mask($raw),
                "last4" => is_string($raw) && $raw !== "" ? substr($raw, -4) : "",
                "is_active" => $entry["id"] === ($state["active_key_id"] ?? null),
                "created_at" => $entry["created_at"] ?? null,
                "updated_at" => $entry["updated_at"] ?? null,
            ];
        }, $state["keys"]);
    }

    public function setActiveProviderKey(string $providerId, string $keyId): array
    {
        $providerId = $this->normalizeProvider($providerId);
        $state = $this->providerKeyState($providerId);
        $ids = array_column($state["keys"], "id");
        if (!in_array($keyId, $ids, true)) {
            return ["success" => false, "message" => "Unknown provider key."];
        }
        $state["active_key_id"] = $keyId;
        $this->persistProviderKeyState($providerId, $state);
        return ["success" => true, "message" => "Active key updated.", "keys" => $this->listProviderKeys($providerId)];
    }

    public function testProviderKey(string $providerId, ?string $keyId = null, ?string $draftKey = null): array
    {
        $providerId = $this->normalizeProvider($providerId);
        $apiKey = $draftKey;
        if (!$apiKey) {
            $entry = $keyId ? $this->providerKeyEntry($providerId, $keyId) : $this->activeProviderKeyEntry($providerId);
            $apiKey = is_array($entry) ? (string) $this->credentials->get(self::PROVIDER_CREDENTIAL_SLUG, $entry["credential_key"]) : "";
        }

        if ($apiKey === "") {
            return ["success" => false, "message" => "No API key configured for " . $providerId . "."];
        }

        if ($providerId === "unrealspeech") {
            return $this->testUnrealSpeechKey($apiKey);
        }

        return ["success" => false, "message" => "Provider test is not implemented for " . $providerId . "."];
    }

    public function synthesizeForSite(object $site, array $payload, ?Request $request = null): array
    {
        $content = trim((string) ($payload["content"] ?? ""));
        if ($content === "") {
            return ["success" => false, "message" => "No content submitted."];
        }

        $provider = $this->normalizeProvider((string) ($payload["provider"] ?? $this->defaultProvider()));
        $profileId = trim((string) ($payload["profile"] ?? "default"));
        $profiles = $this->profiles();
        $profile = $profiles[$profileId] ?? $profiles["default"];
        if (!isset($this->providerDefinitions()[$provider])) {
            $provider = (string) ($profile["provider"] ?? "unrealspeech");
        }

        $publicId = "tts_" . Str::lower(Str::random(20));
        $characters = function_exists("mb_strlen") ? mb_strlen($content) : strlen($content);
        $words = str_word_count(strip_tags($content));
        $requestId = DB::table(self::REQUEST_TABLE)->insertGetId([
            "public_id" => $publicId,
            "site_key_id" => $site->id,
            "status" => "processing",
            "status_message" => "Request accepted by Publish Scale API.",
            "provider" => $provider,
            "site_url" => $site->site_url,
            "site_domain" => $site->site_domain,
            "article_url" => $payload["article_url"] ?? null,
            "post_id" => isset($payload["post_id"]) ? (int) $payload["post_id"] : null,
            "wordpress_user_id" => isset($payload["wordpress_user_id"]) ? (int) $payload["wordpress_user_id"] : null,
            "wordpress_user_login" => $payload["wordpress_user_login"] ?? null,
            "requester_ip" => $request?->ip(),
            "submitted_content" => $content,
            "content_sha256" => hash("sha256", $content),
            "character_count" => $characters,
            "word_count" => $words,
            "meta" => json_encode(["profile" => $profileId, "runtime" => $payload["runtime"] ?? []], JSON_UNESCAPED_SLASHES),
            "started_at" => now(),
            "created_at" => now(),
            "updated_at" => now(),
        ]);

        try {
            if ($provider !== "unrealspeech") {
                throw new \RuntimeException("Provider " . $provider . " is keyring-ready but synthesis is not enabled yet.");
            }

            $runtime = array_merge($this->providerDefinitions()["unrealspeech"]["defaults"], $profile, (array) ($payload["runtime"] ?? []));
            $synth = $this->synthesizeUnrealSpeech($content, $runtime);
            if (!($synth["success"] ?? false)) {
                throw new \RuntimeException((string) ($synth["message"] ?? "UnrealSpeech synthesis failed."));
            }

            $archive = $this->archiveAudio($publicId, $synth["bytes"], "mp3");
            $cost = round($characters * self::UNREAL_COST_PER_CHARACTER, 6);
            DB::table(self::REQUEST_TABLE)->where("id", $requestId)->update([
                "status" => "complete",
                "status_message" => "Audio generated and archived.",
                "provider_key_id" => $synth["provider_key_id"] ?? null,
                "provider_key_last4" => $synth["provider_key_last4"] ?? null,
                "audio_bytes" => strlen($synth["bytes"]),
                "audio_mime" => "audio/mpeg",
                "audio_archive_path" => $archive["path"],
                "audio_archive_url" => $archive["url"],
                "cost_usd" => $cost,
                "finished_at" => now(),
                "updated_at" => now(),
            ]);

            return [
                "success" => true,
                "message" => "Audio generated by Publish Scale API and archived.",
                "request_id" => $publicId,
                "provider" => $provider,
                "provider_key_last4" => $synth["provider_key_last4"] ?? null,
                "audio_base64" => base64_encode($synth["bytes"]),
                "audio_mime" => "audio/mpeg",
                "audio_extension" => "mp3",
                "archive_url" => $archive["url"],
                "bytes" => strlen($synth["bytes"]),
                "characters" => $characters,
                "word_count" => $words,
                "cost_usd" => $cost,
                "timestamp_est" => now("America/New_York")->toDateTimeString(),
            ];
        } catch (Throwable $e) {
            DB::table(self::REQUEST_TABLE)->where("id", $requestId)->update([
                "status" => "failed",
                "status_message" => $e->getMessage(),
                "finished_at" => now(),
                "updated_at" => now(),
            ]);
            return ["success" => false, "request_id" => $publicId, "message" => $e->getMessage()];
        }
    }

    public function recentRequests(int $limit = 50): array
    {
        return DB::table(self::REQUEST_TABLE)
            ->leftJoin(self::SITE_TABLE, self::SITE_TABLE . ".id", "=", self::REQUEST_TABLE . ".site_key_id")
            ->orderByDesc(self::REQUEST_TABLE . ".id")
            ->limit(max(1, min($limit, 200)))
            ->get([
                self::REQUEST_TABLE . ".public_id",
                self::REQUEST_TABLE . ".status",
                self::REQUEST_TABLE . ".status_message",
                self::REQUEST_TABLE . ".provider",
                self::REQUEST_TABLE . ".provider_key_last4",
                self::REQUEST_TABLE . ".article_url",
                self::REQUEST_TABLE . ".post_id",
                self::REQUEST_TABLE . ".wordpress_user_login",
                self::REQUEST_TABLE . ".character_count",
                self::REQUEST_TABLE . ".word_count",
                self::REQUEST_TABLE . ".audio_bytes",
                self::REQUEST_TABLE . ".audio_archive_url",
                self::REQUEST_TABLE . ".cost_usd",
                self::REQUEST_TABLE . ".created_at",
                self::REQUEST_TABLE . ".updated_at",
                self::SITE_TABLE . ".site_domain",
                self::SITE_TABLE . ".name as site_name",
            ])
            ->map(fn ($row) => $this->requestRow($row))
            ->all();
    }

    public function activeRequests(): array
    {
        return DB::table(self::REQUEST_TABLE)
            ->whereIn("status", ["queued", "processing"])
            ->orderBy("id")
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->requestRow($row))
            ->all();
    }

    public function requestByPublicId(string $publicId): ?array
    {
        $row = DB::table(self::REQUEST_TABLE)->where("public_id", $publicId)->first();
        return $row ? $this->requestRow($row) : null;
    }

    private function synthesizeUnrealSpeech(string $text, array $runtime): array
    {
        $entry = $this->activeProviderKeyEntry("unrealspeech");
        if (!$entry) {
            return ["success" => false, "message" => "No active UnrealSpeech API key configured."];
        }
        $apiKey = (string) $this->credentials->get(self::PROVIDER_CREDENTIAL_SLUG, $entry["credential_key"]);
        if ($apiKey === "") {
            return ["success" => false, "message" => "Active UnrealSpeech key is empty."];
        }

        $voice = trim((string) ($runtime["voice"] ?? "af")) ?: "af";
        $bitrate = trim((string) ($runtime["bitrate"] ?? "192k")) ?: "192k";
        $speed = trim((string) ($runtime["speed"] ?? "0"));
        $pitch = trim((string) ($runtime["pitch"] ?? "1"));
        $codec = trim((string) ($runtime["codec"] ?? "libmp3lame")) ?: "libmp3lame";
        $bytes = "";
        $chunks = $this->chunkText($text, 900);

        foreach ($chunks as $index => $chunk) {
            $response = Http::withToken($apiKey)
                ->accept("audio/mpeg")
                ->timeout(90)
                ->post(self::UNREAL_STREAM_ENDPOINT, [
                    "Text" => $chunk,
                    "VoiceId" => $voice,
                    "Bitrate" => $bitrate,
                    "Speed" => $speed === "" ? "0" : $speed,
                    "Pitch" => $pitch === "" ? "1" : $pitch,
                    "Codec" => $codec,
                ]);

            if (!$response->successful()) {
                return ["success" => false, "message" => "UnrealSpeech chunk " . ($index + 1) . " returned HTTP " . $response->status() . ": " . substr($response->body(), 0, 500)];
            }

            $body = $response->body();
            if (strlen($body) < 100) {
                return ["success" => false, "message" => "UnrealSpeech chunk " . ($index + 1) . " returned an empty audio payload."];
            }
            $bytes .= $body;
        }

        return [
            "success" => true,
            "bytes" => $bytes,
            "chunks" => count($chunks),
            "provider_key_id" => $entry["id"],
            "provider_key_last4" => $entry["last4"] ?? "",
        ];
    }

    private function testUnrealSpeechKey(string $apiKey): array
    {
        try {
            $response = Http::withToken($apiKey)
                ->accept("audio/mpeg")
                ->timeout(30)
                ->post(self::UNREAL_STREAM_ENDPOINT, [
                    "Text" => "Publish Scale connection test.",
                    "VoiceId" => "af",
                    "Bitrate" => "128k",
                    "Speed" => "0",
                    "Pitch" => "1",
                    "Codec" => "libmp3lame",
                ]);

            if (!$response->successful()) {
                return ["success" => false, "message" => "UnrealSpeech returned HTTP " . $response->status(), "detail" => substr($response->body(), 0, 500)];
            }

            $length = strlen($response->body());
            if ($length < 100) {
                return ["success" => false, "message" => "UnrealSpeech returned no usable audio bytes."];
            }

            return ["success" => true, "message" => "UnrealSpeech API key is valid.", "detail" => $length . " audio bytes returned from /stream."];
        } catch (Throwable $e) {
            return ["success" => false, "message" => "UnrealSpeech test failed: " . $e->getMessage()];
        }
    }

    private function archiveAudio(string $publicId, string $bytes, string $extension): array
    {
        $relative = "smp-wordpress-tts/" . now("America/New_York")->format("Y/m/d") . "/" . $publicId . "." . $extension;
        Storage::disk("public")->put($relative, $bytes);
        return ["path" => "public/" . $relative, "url" => url("/storage/" . $relative)];
    }

    private function chunkText(string $text, int $limit): array
    {
        $text = preg_replace("/\s+/u", " ", trim($text)) ?: trim($text);
        if ($text === "") {
            return [];
        }

        $chunks = [];
        $current = "";
        $sentences = preg_split("/(?<=[.!?])\s+/u", $text) ?: [$text];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === "") {
                continue;
            }
            while ((function_exists("mb_strlen") ? mb_strlen($sentence) : strlen($sentence)) > $limit) {
                $piece = function_exists("mb_substr") ? mb_substr($sentence, 0, $limit) : substr($sentence, 0, $limit);
                $cut = strrpos($piece, " ");
                if ($cut !== false && $cut > 200) {
                    $piece = substr($piece, 0, $cut);
                }
                $chunks[] = trim($piece);
                $sentence = trim(substr($sentence, strlen($piece)));
            }
            $candidate = trim($current . " " . $sentence);
            $length = function_exists("mb_strlen") ? mb_strlen($candidate) : strlen($candidate);
            if ($current !== "" && $length > $limit) {
                $chunks[] = $current;
                $current = $sentence;
            } else {
                $current = $candidate;
            }
        }
        if (trim($current) !== "") {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function activeProviderKeyEntry(string $providerId): ?array
    {
        $state = $this->providerKeyState($providerId);
        return $this->providerKeyEntry($providerId, (string) ($state["active_key_id"] ?? ""));
    }

    private function providerKeyEntry(string $providerId, string $keyId): ?array
    {
        if ($keyId === "") {
            return null;
        }
        foreach ($this->providerKeyState($providerId)["keys"] as $entry) {
            if (($entry["id"] ?? "") === $keyId) {
                $raw = $this->credentials->get(self::PROVIDER_CREDENTIAL_SLUG, $entry["credential_key"]);
                $entry["last4"] = is_string($raw) && $raw !== "" ? substr($raw, -4) : "";
                return $entry;
            }
        }
        return null;
    }

    private function providerKeyState(string $providerId): array
    {
        $stored = Setting::getValue(self::PROVIDER_META_PREFIX . $providerId, "");
        $decoded = is_string($stored) && $stored !== "" ? json_decode($stored, true) : [];
        $state = is_array($decoded) ? $decoded : [];
        $keys = [];
        foreach ((array) ($state["keys"] ?? []) as $entry) {
            if (!is_array($entry) || empty($entry["id"]) || empty($entry["credential_key"])) {
                continue;
            }
            $raw = $this->credentials->get(self::PROVIDER_CREDENTIAL_SLUG, $entry["credential_key"]);
            if (!is_string($raw) || $raw === "") {
                continue;
            }
            $keys[] = [
                "id" => (string) $entry["id"],
                "name" => trim((string) ($entry["name"] ?? "")) ?: ucfirst($providerId) . " API Key",
                "credential_key" => (string) $entry["credential_key"],
                "created_at" => $entry["created_at"] ?? null,
                "updated_at" => $entry["updated_at"] ?? null,
            ];
        }
        $active = is_string($state["active_key_id"] ?? null) ? $state["active_key_id"] : null;
        $ids = array_column($keys, "id");
        if (!$active || !in_array($active, $ids, true)) {
            $active = $ids[0] ?? null;
        }
        return ["active_key_id" => $active, "keys" => $keys];
    }

    private function persistProviderKeyState(string $providerId, array $state): void
    {
        Setting::setValue(self::PROVIDER_META_PREFIX . $providerId, json_encode([
            "active_key_id" => $state["active_key_id"] ?? null,
            "keys" => array_values($state["keys"] ?? []),
        ], JSON_UNESCAPED_SLASHES), "smp_wordpress_tts");
    }

    private function normalizeProvider(string $providerId): string
    {
        $providerId = Str::slug($providerId, "_");
        return isset($this->providerDefinitions()[$providerId]) ? $providerId : "unrealspeech";
    }

    private function domainFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $host = strtolower(preg_replace("/^www\./", "", trim((string) $host)));
        return preg_replace("/[^a-z0-9.\-]/", "", $host) ?: "";
    }

    private function requestRow(object $row): array
    {
        return [
            "id" => $row->public_id ?? null,
            "status" => $row->status ?? null,
            "message" => $row->status_message ?? null,
            "provider" => $row->provider ?? null,
            "provider_key_last4" => $row->provider_key_last4 ?? null,
            "site" => $row->site_domain ?? ($row->site_url ?? null),
            "site_name" => $row->site_name ?? null,
            "article_url" => $row->article_url ?? null,
            "post_id" => $row->post_id ?? null,
            "user" => $row->wordpress_user_login ?? null,
            "characters" => (int) ($row->character_count ?? 0),
            "words" => (int) ($row->word_count ?? 0),
            "audio_bytes" => (int) ($row->audio_bytes ?? 0),
            "audio_url" => $row->audio_archive_url ?? null,
            "cost_usd" => isset($row->cost_usd) ? (float) $row->cost_usd : null,
            "created_at" => $row->created_at ?? null,
            "updated_at" => $row->updated_at ?? null,
            "created_at_est" => !empty($row->created_at) ? \Carbon\Carbon::parse($row->created_at)->timezone("America/New_York")->toDateTimeString() : null,
        ];
    }
}
