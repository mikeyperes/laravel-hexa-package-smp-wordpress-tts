<?php

namespace hexa_package_smp_wordpress_tts\Http\Controllers;

use hexa_package_smp_wordpress_tts\Services\SmpWordPressTtsApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SmpWordPressTtsApiController extends Controller
{
    public function __construct(private SmpWordPressTtsApiService $api)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $site = $this->api->authenticate($request);
        if (!$site) {
            return response()->json([
                "success" => false,
                "message" => "Invalid or missing Publish Scale TTS API key.",
            ], 401);
        }

        return response()->json($this->api->statusForSite($site));
    }

    public function synthesize(Request $request): JsonResponse
    {
        $site = $this->api->authenticate($request);
        if (!$site) {
            return response()->json([
                "success" => false,
                "message" => "Invalid or missing Publish Scale TTS API key.",
            ], 401);
        }

        $validated = $request->validate([
            "content" => ["required", "string", "max:500000"],
            "article_url" => ["nullable", "string", "max:2048"],
            "post_id" => ["nullable", "integer", "min:1"],
            "wordpress_user_id" => ["nullable", "integer", "min:1"],
            "wordpress_user_login" => ["nullable", "string", "max:255"],
            "provider" => ["nullable", "string", "max:80"],
            "profile" => ["nullable", "string", "max:80"],
            "runtime" => ["nullable", "array"],
            "runtime.voice" => ["nullable", "string", "max:120"],
            "runtime.speed" => ["nullable", "string", "max:20"],
            "runtime.pitch" => ["nullable", "string", "max:20"],
            "runtime.bitrate" => ["nullable", "string", "max:20"],
        ]);

        $result = $this->api->synthesizeForSite($site, $validated, $request);
        return response()->json($result, ($result["success"] ?? false) ? 200 : 422);
    }

    public function showRequest(string $publicId): JsonResponse
    {
        $row = $this->api->requestByPublicId($publicId);
        if (!$row) {
            return response()->json(["success" => false, "message" => "Request not found."], 404);
        }

        return response()->json(["success" => true, "request" => $row]);
    }

    public function adminHistory(Request $request): JsonResponse
    {
        return response()->json([
            "success" => true,
            "active" => $this->api->activeRequests(),
            "requests" => $this->api->recentRequests((int) $request->input("limit", 50)),
        ]);
    }

    public function providerKeys(string $provider): JsonResponse
    {
        return response()->json([
            "success" => true,
            "provider" => $provider,
            "keys" => $this->api->listProviderKeys($provider),
        ]);
    }

    public function addProviderKey(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            "name" => ["nullable", "string", "max:255"],
            "api_key" => ["required", "string", "min:10"],
            "make_active" => ["nullable", "boolean"],
        ]);

        return response()->json($this->api->addProviderKey(
            $provider,
            (string) ($validated["name"] ?? ""),
            (string) $validated["api_key"],
            (bool) ($validated["make_active"] ?? true)
        ));
    }

    public function setActiveProviderKey(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            "key_id" => ["required", "string", "max:120"],
        ]);

        return response()->json($this->api->setActiveProviderKey($provider, (string) $validated["key_id"]));
    }

    public function testProviderKey(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            "key_id" => ["nullable", "string", "max:120"],
            "api_key" => ["nullable", "string", "min:10"],
        ]);

        return response()->json($this->api->testProviderKey(
            $provider,
            $validated["key_id"] ?? null,
            $validated["api_key"] ?? null
        ));
    }

    public function generateSiteKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "site_url" => ["required", "string", "max:2048"],
            "name" => ["nullable", "string", "max:255"],
            "account" => ["nullable", "string", "max:255"],
            "server_id" => ["nullable", "integer", "min:1"],
            "install_id" => ["nullable", "integer", "min:1"],
        ]);

        return response()->json($this->api->generateSiteApiKey($validated));
    }
}
