<?php

namespace hexa_package_smp_wordpress_tts\Http\Controllers;

use hexa_core\Models\ActivityLog;
use hexa_package_smp_wordpress_tts\Services\SmpWordPressTtsService;
use hexa_package_whm\Models\WhmServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SmpWordPressTtsController extends Controller
{
    public function __construct(
        protected SmpWordPressTtsService $tts
    ) {
    }

    public function dashboard(): View
    {
        return view('smp-wordpress-tts::dashboard.index', [
            'servers' => WhmServer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'hostname']),
            'plugin' => [
                'slug' => (string) config('smp-wordpress-tts.plugin_slug'),
                'repo' => (string) config('smp-wordpress-tts.github_repo'),
                'ref' => (string) config('smp-wordpress-tts.github_ref'),
            ],
        ]);
    }

    public function settings(): View
    {
        return view('smp-wordpress-tts::settings.index', [
            'providers' => $this->tts->providerDefinitions(),
            'settings' => $this->tts->settingsSnapshot(),
            'credentialSlug' => SmpWordPressTtsService::CREDENTIAL_SLUG,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->tts->saveSettings($request->all());
        ActivityLog::log('smp-wordpress-tts', 'settings_saved', 'SMP WordPress TTS settings updated.');

        return redirect()
            ->route('smp-wordpress-tts.settings')
            ->with('status', 'SMP WordPress TTS settings saved.');
    }

    public function testProvider(string $provider): JsonResponse
    {
        return response()->json($this->tts->testProvider($provider));
    }

    public function accounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'success' => true,
            'accounts' => $this->tts->accountsForServer((int) $validated['server_id']),
        ]);
    }

    public function installs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'integer', 'min:1'],
            'account_usernames' => ['required', 'array', 'min:1'],
            'account_usernames.*' => ['string', 'max:255'],
        ]);

        return response()->json([
            'success' => true,
            'installs' => $this->tts->installsForAccounts((int) $validated['server_id'], (array) $validated['account_usernames']),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.server_id' => ['required', 'integer', 'min:1'],
            'targets.*.install_id' => ['required', 'integer', 'min:1'],
            'targets.*.account' => ['nullable', 'string', 'max:255'],
            'targets.*.url' => ['nullable', 'string', 'max:2048'],
            'targets.*.path' => ['nullable', 'string', 'max:2048'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $results = [];
        foreach ((array) $validated['targets'] as $target) {
            $results[] = $this->tts->scanInstall((array) $target, $request->boolean('force'));
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    public function pushCredentials(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target.server_id' => ['required', 'integer', 'min:1'],
            'target.install_id' => ['required', 'integer', 'min:1'],
            'target.account' => ['nullable', 'string', 'max:255'],
            'target.url' => ['nullable', 'string', 'max:2048'],
            'target.path' => ['nullable', 'string', 'max:2048'],
        ]);

        $result = $this->tts->pushCredentialsToInstall((array) $validated['target']);
        ActivityLog::log('smp-wordpress-tts', 'credentials_pushed', 'SMP WordPress TTS credentials pushed to a WordPress install.', [
            'server_id' => (int) $validated['target']['server_id'],
            'install_id' => (int) $validated['target']['install_id'],
            'success' => (bool) ($result['success'] ?? false),
        ]);

        return response()->json($result);
    }

    public function updatePlugin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target.server_id' => ['required', 'integer', 'min:1'],
            'target.install_id' => ['required', 'integer', 'min:1'],
            'target.account' => ['nullable', 'string', 'max:255'],
            'target.url' => ['nullable', 'string', 'max:2048'],
            'target.path' => ['nullable', 'string', 'max:2048'],
        ]);

        $result = $this->tts->updatePlugin((array) $validated['target']);
        ActivityLog::log('smp-wordpress-tts', 'plugin_update', 'SMP WordPress TTS plugin update attempted from GitHub.', [
            'server_id' => (int) $validated['target']['server_id'],
            'install_id' => (int) $validated['target']['install_id'],
            'success' => (bool) ($result['success'] ?? false),
        ]);

        return response()->json($result);
    }
}
