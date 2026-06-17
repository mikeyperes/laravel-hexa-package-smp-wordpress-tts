<?php

namespace hexa_package_smp_wordpress_tts\Services;

use hexa_core\Models\Setting;
use hexa_core\Services\CredentialService;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wordpress\Services\WordPressPluginIntegrityService;
use hexa_package_wptoolkit\Services\WpToolkitService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SmpWordPressTtsService
{
    public const CREDENTIAL_SLUG = 'smp-wordpress-tts';

    public function __construct(
        protected CredentialService $credentials,
        protected WpToolkitService $wpToolkit,
        protected WordPressPluginIntegrityService $integrity
    ) {
    }

    public function providerDefinitions(): array
    {
        return [
            'kokoro' => [
                'label' => 'Kokoro-82M Local Service',
                'summary' => 'Primary low-cost in-house engine served behind a private HTTP wrapper.',
                'testable' => true,
                'docs' => [
                    ['label' => 'Kokoro model card', 'url' => 'https://huggingface.co/hexgrad/Kokoro-82M'],
                    ['label' => 'Kokoro GitHub', 'url' => 'https://github.com/hexgrad/kokoro'],
                    ['label' => 'Kokoro voices', 'url' => 'https://huggingface.co/hexgrad/Kokoro-82M/blob/main/VOICES.md'],
                    ['label' => 'Kokoro samples', 'url' => 'https://huggingface.co/hexgrad/Kokoro-82M/blob/main/SAMPLES.md'],
                ],
                'instructions' => [
                    'Kokoro itself is an open-weight TTS model, not an account-based API. The API key field here is only for the private HTTP wrapper you deploy around Kokoro. If your wrapper does not require Authorization, leave the bearer token empty.',
                    'Open the Kokoro model card and confirm the model, license, voices, and sample output fit the target site. The linked model card states Kokoro-82M is open-weight and Apache licensed, so this is appropriate for self-hosted article narration.',
                    'Deploy the Kokoro runtime behind an internal service URL, preferably on 127.0.0.1, a private network address, or a firewall-restricted host. Do not expose an unauthenticated synthesis endpoint to the public internet.',
                    'The wrapper should expose GET /health or GET /voices for the Test button, and POST /synthesize for the WordPress plugin. The plugin sends text, voice, model, language, speed, and format data to that service.',
                    'Set Service URL to the full synthesize endpoint if your wrapper expects /synthesize, or to the service base URL if the wrapper appends /synthesize internally. The Test button trims /synthesize and tries /health, /voices, then the base URL.',
                    'Use the Kokoro voices link to pick the voice value. Keep the default af_heart only if that voice exists in your deployed runtime.',
                    'If the wrapper requires a token, create a token named for the site, for example smp-publish-mashviral-kokoro. Save that token in Bearer token. The system sends it as Authorization: Bearer.',
                    'Run Test after saving the URL and optional token. A passing test only proves the wrapper responds; a failed test means the URL, firewall, route, or bearer token is wrong.',
                ],
                'fields' => [
                    'service_url' => ['label' => 'Service URL', 'type' => 'url', 'default' => 'http://127.0.0.1:8880/synthesize', 'secret' => false, 'help' => 'Full synthesize endpoint or base URL.'],
                    'api_key' => ['label' => 'Bearer token', 'type' => 'password', 'default' => '', 'secret' => true, 'help' => 'Optional Authorization: Bearer token for the local wrapper.'],
                    'voice' => ['label' => 'Default voice', 'type' => 'text', 'default' => 'af_heart', 'secret' => false, 'help' => 'Example Kokoro voice.'],
                    'model' => ['label' => 'Model', 'type' => 'text', 'default' => 'kokoro-82m', 'secret' => false, 'help' => 'Internal model label sent to the wrapper.'],
                ],
            ],
            'piper' => [
                'label' => 'Piper Local Service',
                'summary' => 'Local fallback engine for fast/offline narration.',
                'testable' => true,
                'docs' => [
                    ['label' => 'Piper GitHub', 'url' => 'https://github.com/rhasspy/piper'],
                    ['label' => 'Piper samples', 'url' => 'https://rhasspy.github.io/piper-samples/'],
                ],
                'instructions' => [
                    'Piper is a local neural TTS engine, not a hosted account API. The bearer token field is only for your private wrapper service. If the wrapper is private and unauthenticated, leave the token empty.',
                    'Open the Piper GitHub repository to confirm the runtime and packaging path, then use the Piper samples link to choose a voice/model that matches the publication language and quality target.',
                    'Run Piper as a separate process or container. Do not run the model inside WordPress/PHP. WordPress should call a small HTTP wrapper that handles the model process, errors, and audio output.',
                    'Expose GET /health or GET /voices for the Test button and POST /synthesize for actual generation. The wrapper should accept text, voice, model, language, speed, and format, then return audio bytes or JSON containing audio_base64.',
                    'Set Service URL to the wrapper synthesize endpoint or base URL. The Test button trims /synthesize and checks /health, /voices, then the base URL.',
                    'Use the exact installed Piper voice/model name. The default en_US-lessac-medium only works if that voice is installed on the wrapper host.',
                    'If the wrapper uses a bearer token, name it per site, for example smp-publish-mashviral-piper, and save it in Bearer token. The system sends it as Authorization: Bearer.',
                    'Run Test after saving. A passing test means the wrapper is reachable; generation can still fail later if the chosen voice/model is not installed.',
                ],
                'fields' => [
                    'service_url' => ['label' => 'Service URL', 'type' => 'url', 'default' => 'http://127.0.0.1:5002/synthesize', 'secret' => false, 'help' => 'Full synthesize endpoint or base URL.'],
                    'api_key' => ['label' => 'Bearer token', 'type' => 'password', 'default' => '', 'secret' => true, 'help' => 'Optional bearer token for the Piper wrapper.'],
                    'voice' => ['label' => 'Default voice', 'type' => 'text', 'default' => 'en_US-lessac-medium', 'secret' => false, 'help' => 'Installed Piper voice/model name.'],
                    'model' => ['label' => 'Model', 'type' => 'text', 'default' => 'piper', 'secret' => false, 'help' => 'Internal model label sent to the wrapper.'],
                ],
            ],
            'amazon_polly' => [
                'label' => 'Amazon Polly',
                'summary' => 'Cloud fallback with Standard, Neural, Generative, and Long-Form voices.',
                'testable' => true,
                'docs' => [
                    ['label' => 'AWS IAM users console', 'url' => 'https://console.aws.amazon.com/iam/home#/users'],
                    ['label' => 'AWS IAM access keys', 'url' => 'https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_access-keys.html'],
                    ['label' => 'Amazon Polly DescribeVoices', 'url' => 'https://docs.aws.amazon.com/polly/latest/dg/API_DescribeVoices.html'],
                    ['label' => 'Amazon Polly SynthesizeSpeech', 'url' => 'https://docs.aws.amazon.com/polly/latest/dg/API_SynthesizeSpeech.html'],
                    ['label' => 'Amazon Polly pricing', 'url' => 'https://aws.amazon.com/polly/pricing/'],
                ],
                'instructions' => [
                    'Open the IAM users console and create a dedicated IAM user for this WordPress TTS workflow. Do not use the AWS root account and do not reuse a broad admin key.',
                    'Name the IAM user with a tracking marker that identifies the site and purpose, for example smp-publish-mashviral-polly. This is the practical AWS-side marker for usage reviews and key rotation.',
                    'Attach a least-privilege policy that allows polly:DescribeVoices for validation and polly:SynthesizeSpeech for generation. Amazon documents DescribeVoices as the voice listing action and SynthesizeSpeech as the audio generation action.',
                    'Create an access key for that IAM user. AWS only shows the secret access key at creation time, so copy both Access key ID and Secret access key before leaving the confirmation screen.',
                    'Save Access key ID in the Access key ID credential field and Secret access key in the Secret access key credential field. These are stored through Hexa Core CredentialService, not in the WordPress plugin source.',
                    'Set AWS region to the region where you intend to synthesize speech, for example us-east-1. The selected voice and engine must exist in that region.',
                    'Use the DescribeVoices documentation to confirm the Voice ID and SupportedEngines. If you choose neural/generative/long-form for a voice that does not support it, Polly will reject generation.',
                    'Run Test. The test signs a request to the Polly /v1/voices endpoint for the selected region. If it fails, check the key pair, region, IAM policy, account billing status, and whether the key is active.',
                    'Rotate keys per AWS policy. If you rotate, save the new pair here first, test it, then push credentials to selected WordPress installs.',
                ],
                'fields' => [
                    'access_key_id' => ['label' => 'Access key ID', 'type' => 'password', 'default' => '', 'secret' => true, 'help' => 'IAM access key ID.'],
                    'secret_access_key' => ['label' => 'Secret access key', 'type' => 'password', 'default' => '', 'secret' => true, 'help' => 'IAM secret access key.'],
                    'region' => ['label' => 'AWS region', 'type' => 'text', 'default' => 'us-east-1', 'secret' => false, 'help' => 'Example: us-east-1.'],
                    'voice' => ['label' => 'Voice ID', 'type' => 'text', 'default' => 'Joanna', 'secret' => false, 'help' => 'Example: Joanna, Matthew, Ruth, Stephen.'],
                    'engine' => ['label' => 'Engine', 'type' => 'select', 'default' => 'neural', 'secret' => false, 'options' => ['standard' => 'standard', 'neural' => 'neural', 'generative' => 'generative', 'long-form' => 'long-form'], 'help' => 'Voice support varies by region.'],
                ],
            ],
            'google_tts' => [
                'label' => 'Google Cloud Text-to-Speech',
                'summary' => 'Cloud fallback with Standard, WaveNet, Neural2, and Chirp voices.',
                'testable' => true,
                'docs' => [
                    ['label' => 'Enable Text-to-Speech API', 'url' => 'https://console.cloud.google.com/apis/library/texttospeech.googleapis.com'],
                    ['label' => 'Google API credentials', 'url' => 'https://console.cloud.google.com/apis/credentials'],
                    ['label' => 'Manage API keys', 'url' => 'https://cloud.google.com/docs/authentication/api-keys'],
                    ['label' => 'API key best practices', 'url' => 'https://cloud.google.com/docs/authentication/api-keys-best-practices'],
                    ['label' => 'voices.list reference', 'url' => 'https://cloud.google.com/text-to-speech/docs/reference/rest/v1/voices/list'],
                ],
                'instructions' => [
                    'Open the Google Cloud Text-to-Speech API link and select the exact Google Cloud project that should pay for this site. Enable Cloud Text-to-Speech API for that project.',
                    'Open the Credentials page and create an API key. Name it with a site marker such as smp-publish-mashviral-google-tts so usage and rotation are traceable from Google Cloud.',
                    'Apply API restrictions to the key and restrict it to Cloud Text-to-Speech API. Google explicitly warns that unrestricted API keys are insecure and recommends API restrictions.',
                    'Apply application restrictions where possible. For server-side use, restrict by the outbound server IP addresses that will call Google. Do not use browser/referrer restrictions for this server-side WordPress workflow.',
                    'Copy the API key into the API key credential field here and save it. Keep the key out of local files, plugin files, and screenshots.',
                    'Set Language code to a BCP-47 value such as en-US. Google voices.list accepts languageCode and returns only voices compatible with that language when provided.',
                    'Set Voice name to the exact Google voice name you want, for example en-US-Neural2-J. Voice names must match Google output exactly.',
                    'Set Speaking rate as a number, usually 1.0 for normal speed. Use small increments such as 0.9 or 1.1 when adjusting editorial tone.',
                    'Run Test. The test calls Google voices.list with the saved API key and selected language. If it fails, confirm the API is enabled, billing is active, restrictions include Text-to-Speech, and the server IP is allowed.',
                ],
                'fields' => [
                    'api_key' => ['label' => 'API key', 'type' => 'password', 'default' => '', 'secret' => true, 'help' => 'Google API key with Text-to-Speech enabled.'],
                    'language' => ['label' => 'Language code', 'type' => 'text', 'default' => 'en-US', 'secret' => false, 'help' => 'BCP-47 language code.'],
                    'voice' => ['label' => 'Voice name', 'type' => 'text', 'default' => 'en-US-Neural2-J', 'secret' => false, 'help' => 'Exact Google voice name.'],
                    'speaking_rate' => ['label' => 'Speaking rate', 'type' => 'number', 'default' => '1.0', 'secret' => false, 'help' => 'Example: 0.9, 1.0, 1.1.'],
                ],
            ],
            'elevenlabs' => [
                'label' => 'ElevenLabs',
                'summary' => 'Premium/manual voice option for high-value narration.',
                'testable' => true,
                'docs' => [
                    ['label' => 'ElevenLabs API keys', 'url' => 'https://elevenlabs.io/app/settings/api-keys'],
                    ['label' => 'Authentication docs', 'url' => 'https://elevenlabs.io/docs/api-reference/authentication'],
                    ['label' => 'List models endpoint', 'url' => 'https://elevenlabs.io/docs/api-reference/models/list'],
                    ['label' => 'Text to Speech endpoint', 'url' => 'https://elevenlabs.io/docs/api-reference/text-to-speech/convert'],
                    ['label' => 'Pricing', 'url' => 'https://elevenlabs.io/pricing'],
                ],
                'instructions' => [
                    'Open the ElevenLabs API keys page. If it redirects, log in and return to the API keys page from the developer settings area.',
                    'Create a dedicated API key for this site. Name it with a tracking marker such as smp-publish-mashviral-elevenlabs so usage can be tied back to the WordPress account.',
                    'Use ElevenLabs key restrictions where available. Their authentication docs list scope restrictions, credit quota, and IP whitelisting preview, so set at least Text to Speech scope and an appropriate credit quota for the site.',
                    'Copy the key into the API key credential field. ElevenLabs authenticates requests with the xi-api-key HTTP header, and this system sends the saved key that way.',
                    'Choose the Voice ID from the ElevenLabs voice library. The default 21m00Tcm4TlvDq8ikWAM is only a placeholder/default and should be replaced when the publication has an approved voice.',
                    'Choose the Model ID deliberately. Use eleven_multilingual_v2 when quality/language coverage matters; use a lower-cost Flash/Turbo model only if the account plan and editorial requirements allow it.',
                    'Set Stability and Similarity boost between 0 and 1. These are voice settings sent during generation and should be adjusted only after listening tests.',
                    'Run Test. The test calls the ElevenLabs models endpoint with the saved key. If it fails, check whether the key is active, scoped correctly, over quota, IP restricted, or copied with extra whitespace.',
                    'For cost control, keep one key per site or per billing group. Do not share a single unlimited key across all WordPress installs unless usage attribution is intentionally centralized.',
                ],
                'fields' => [
                    'api_key' => ['label' => 'API key', 'type' => 'password', 'default' => '', 'secret' => true, 'help' => 'Sent as xi-api-key.'],
                    'voice' => ['label' => 'Voice ID', 'type' => 'text', 'default' => '21m00Tcm4TlvDq8ikWAM', 'secret' => false, 'help' => 'Voice ID from the voice library.'],
                    'model' => ['label' => 'Model ID', 'type' => 'text', 'default' => 'eleven_multilingual_v2', 'secret' => false, 'help' => 'Use Flash/Turbo models for lower cost when approved.'],
                    'stability' => ['label' => 'Stability', 'type' => 'number', 'default' => '0.45', 'secret' => false, 'help' => 'Voice setting from 0 to 1.'],
                    'similarity_boost' => ['label' => 'Similarity boost', 'type' => 'number', 'default' => '0.75', 'secret' => false, 'help' => 'Voice setting from 0 to 1.'],
                ],
            ],
            'deepgram' => [
                'label' => 'Deepgram Aura',
                'summary' => 'Low-latency voice API that can also produce article audio.',
                'testable' => true,
                'docs' => [
                    ['label' => 'Deepgram console', 'url' => 'https://console.deepgram.com/'],
                    ['label' => 'Create API keys', 'url' => 'https://developers.deepgram.com/docs/create-additional-api-keys'],
                    ['label' => 'Authentication docs', 'url' => 'https://developers.deepgram.com/docs/authenticating'],
                    ['label' => 'Text-to-speech API', 'url' => 'https://developers.deepgram.com/reference/text-to-speech-api'],
                ],
                'instructions' => [
                    'Open the Deepgram Console and select the project that should own this publication usage. Deepgram API keys are project-scoped.',
                    'Go to Settings, then API Keys, then create a new API key. Deepgram documents role, expiration, and tag fields during key creation.',
                    'Use the Friendly Name or Comment as a site marker, for example smp-publish-mashviral-deepgram. If using Deepgram tags, set the tag during key creation because Deepgram documents that tags cannot be changed once set.',
                    'Assign the narrowest role/permissions that can call Text-to-Speech. Avoid administrator keys for routine WordPress article generation.',
                    'Copy the key secret immediately and save it in the API key credential field here. Deepgram shows the secret once at creation, so store it before leaving the page.',
                    'Set Model to the exact Aura TTS model, for example aura-2-thalia-en. Deepgram Text-to-Speech calls use the model query parameter.',
                    'Set Speed only if the selected model supports it. Deepgram documents speed as a 0.7 to 1.5 multiplier, but model support varies.',
                    'Run Test. The test calls /v1/auth/token with Authorization: Token. Deepgram documents this endpoint as a quick key validation request.',
                    'When generation is expanded to request-level tracking, use Deepgram tag query parameters to label usage by site. For now, key-level project/name/tag markers are the required tracking path.',
                ],
                'fields' => [
                    'api_key' => ['label' => 'API key', 'type' => 'password', 'default' => '', 'secret' => true, 'help' => 'Sent as Authorization: Token.'],
                    'model' => ['label' => 'Model', 'type' => 'text', 'default' => 'aura-2-thalia-en', 'secret' => false, 'help' => 'Example: aura-2-thalia-en.'],
                    'voice' => ['label' => 'Voice/profile note', 'type' => 'text', 'default' => 'thalia', 'secret' => false, 'help' => 'Deepgram voice is normally embedded in the model name.'],
                    'speed' => ['label' => 'Speed', 'type' => 'number', 'default' => '1.0', 'secret' => false, 'help' => 'Supported range depends on model.'],
                ],
            ],
            'cartesia' => [
                'label' => 'Cartesia Sonic',
                'summary' => 'Modern voice API for premium/realtime tests and article generation.',
                'testable' => true,
                'docs' => [
                    ['label' => 'Cartesia API keys', 'url' => 'https://play.cartesia.ai/keys'],
                    ['label' => 'List voices', 'url' => 'https://docs.cartesia.ai/api-reference/voices/list'],
                    ['label' => 'TTS bytes', 'url' => 'https://docs.cartesia.ai/api-reference/tts/bytes'],
                ],
                'instructions' => [
                    'Open the Cartesia API keys page and create a dedicated key for this site or billing group. Name it with a tracking marker such as smp-publish-mashviral-cartesia.',
                    'Save the key in the API key credential field. Cartesia List Voices documents the key as sk_car_... and requires it in the Authorization header.',
                    'Keep Cartesia-Version set to the documented API version. The current Cartesia docs list 2026-03-01 as the available version header for List Voices and TTS bytes.',
                    'Open List Voices and choose the exact voice UUID. Save that UUID in Voice ID. Do not paste a voice display name into this field; the API expects an ID.',
                    'Set Model ID to a documented model such as sonic-3.5, sonic-3, or sonic-latest. Use the TTS bytes docs to confirm supported models before changing the default.',
                    'Set Speed as a generation_config speed value when using sonic-3 or newer. Keep 1.0 unless there is an approved listening test for the publication.',
                    'Run Test. The test calls Cartesia List Voices with Authorization: Bearer and the Cartesia-Version header. If it fails, check key validity, version header, account status, and whether the key was copied correctly.',
                    'Use separate Cartesia keys for separate high-volume sites when attribution matters. If using one shared key, usage must be tracked outside Cartesia by the dashboard target selection.',
                ],
                'fields' => [
                    'api_key' => ['label' => 'API key', 'type' => 'password', 'default' => '', 'secret' => true, 'help' => 'Sent as Authorization: Bearer.'],
                    'version' => ['label' => 'Cartesia-Version', 'type' => 'text', 'default' => '2026-03-01', 'secret' => false, 'help' => 'Required version header.'],
                    'model' => ['label' => 'Model ID', 'type' => 'text', 'default' => 'sonic-3.5', 'secret' => false, 'help' => 'Example: sonic-3.5.'],
                    'voice' => ['label' => 'Voice ID', 'type' => 'text', 'default' => '', 'secret' => false, 'help' => 'Required voice UUID from Cartesia voices.'],
                    'speed' => ['label' => 'Speed', 'type' => 'number', 'default' => '1.0', 'secret' => false, 'help' => 'Generation config speed.'],
                ],
            ],
        ];
    }

    public function settingsSnapshot(): array
    {
        $providers = [];
        foreach ($this->providerDefinitions() as $providerId => $provider) {
            foreach ($provider['fields'] as $fieldId => $field) {
                $providers[$providerId][$fieldId] = $this->fieldValue($providerId, $fieldId, $field);
            }
        }

        return [
            'default_provider' => Setting::getValue('smp_wordpress_tts_default_provider', 'kokoro'),
            'default_profile' => Setting::getValue('smp_wordpress_tts_default_profile', 'default'),
            'auto_insert_player' => (int) Setting::getValue('smp_wordpress_tts_auto_insert_player', '1'),
            'include_title' => (int) Setting::getValue('smp_wordpress_tts_include_title', '1'),
            'max_characters' => (int) Setting::getValue('smp_wordpress_tts_max_characters', '20000'),
            'profiles' => [
                'default' => ['label' => 'Default Article Narration', 'provider' => 'kokoro', 'voice' => 'af_heart', 'model' => 'kokoro-82m', 'language' => 'en-US', 'speed' => '1.0'],
                'local' => ['label' => 'Local Low-Cost', 'provider' => 'kokoro', 'voice' => 'af_heart', 'model' => 'kokoro-82m', 'language' => 'en-US', 'speed' => '1.0'],
                'premium' => ['label' => 'Premium Manual', 'provider' => 'elevenlabs', 'voice' => '21m00Tcm4TlvDq8ikWAM', 'model' => 'eleven_multilingual_v2', 'language' => 'en-US', 'speed' => '1.0'],
            ],
            'providers' => $providers,
        ];
    }

    public function saveSettings(array $input): void
    {
        $providers = $this->providerDefinitions();
        $defaultProvider = (string) ($input['default_provider'] ?? 'kokoro');
        if (!isset($providers[$defaultProvider])) {
            $defaultProvider = 'kokoro';
        }

        Setting::setValue('smp_wordpress_tts_default_provider', $defaultProvider, 'smp_wordpress_tts');
        Setting::setValue('smp_wordpress_tts_default_profile', in_array(($input['default_profile'] ?? 'default'), ['default', 'local', 'premium'], true) ? (string) $input['default_profile'] : 'default', 'smp_wordpress_tts');
        Setting::setValue('smp_wordpress_tts_auto_insert_player', empty($input['auto_insert_player']) ? '0' : '1', 'smp_wordpress_tts');
        Setting::setValue('smp_wordpress_tts_include_title', empty($input['include_title']) ? '0' : '1', 'smp_wordpress_tts');
        Setting::setValue('smp_wordpress_tts_max_characters', (string) max(500, (int) ($input['max_characters'] ?? 20000)), 'smp_wordpress_tts');

        $postedProviders = is_array($input['providers'] ?? null) ? $input['providers'] : [];
        foreach ($providers as $providerId => $provider) {
            foreach ($provider['fields'] as $fieldId => $field) {
                if (!empty($field['secret'])) {
                    continue;
                }

                $value = (string) ($postedProviders[$providerId][$fieldId] ?? ($field['default'] ?? ''));
                if (($field['type'] ?? '') === 'url') {
                    $value = filter_var(trim($value), FILTER_SANITIZE_URL) ?: '';
                } elseif (($field['type'] ?? '') === 'number') {
                    $value = (string) (float) $value;
                } else {
                    $value = trim($value);
                }

                Setting::setValue($this->settingKey($providerId, $fieldId), $value, 'smp_wordpress_tts');
            }
        }
    }

    public function testProvider(string $providerId): array
    {
        $providerId = trim($providerId);
        $providers = $this->providerDefinitions();
        if (!isset($providers[$providerId])) {
            return ['success' => false, 'message' => 'Unknown provider.'];
        }

        $settings = $this->providerRuntime($providerId);

        return match ($providerId) {
            'kokoro', 'piper' => $this->testLocalService($settings),
            'amazon_polly' => $this->testAmazonPolly($settings),
            'google_tts' => $this->testGoogleTts($settings),
            'elevenlabs' => $this->testElevenLabs($settings),
            'deepgram' => $this->testDeepgram($settings),
            'cartesia' => $this->testCartesia($settings),
            default => ['success' => false, 'message' => 'Provider test is not implemented.'],
        };
    }

    public function accountsForServer(int $serverId): array
    {
        $query = HostingAccount::query()->where('whm_server_id', $serverId)->orderBy('username');

        return $query->get(['id', 'username', 'domain', 'status', 'package'])->map(static fn (HostingAccount $account): array => [
            'id' => $account->id,
            'username' => $account->username,
            'domain' => $account->domain,
            'status' => $account->status,
            'package' => $account->package,
        ])->all();
    }

    public function installsForAccounts(int $serverId, array $accountUsernames): array
    {
        $server = WhmServer::findOrFail($serverId);
        $rows = [];
        foreach (array_values(array_unique(array_filter(array_map('strval', $accountUsernames)))) as $username) {
            $result = $this->wpToolkit->getInstallsForAccount($server, $username);
            foreach ((array) ($result['installs'] ?? []) as $install) {
                if (!is_array($install)) {
                    continue;
                }
                $rows[] = [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'account' => $username,
                    'install_id' => (int) ($install['id'] ?? 0),
                    'path' => (string) ($install['path'] ?? ''),
                    'url' => (string) ($install['url'] ?? ''),
                    'name' => (string) ($install['name'] ?? ''),
                    'version' => (string) ($install['version'] ?? ''),
                    'admin_user' => (string) ($install['admin_user'] ?? ''),
                ];
            }
        }

        return $rows;
    }

    public function scanInstall(array $target, bool $force = false): array
    {
        $server = WhmServer::findOrFail((int) ($target['server_id'] ?? 0));
        $installId = (int) ($target['install_id'] ?? 0);
        if ($installId <= 0) {
            return ['success' => false, 'message' => 'WordPress install ID is required.', 'target' => $target];
        }

        $cacheKey = 'smp_wordpress_tts_scan_' . sha1($server->id . '|' . $installId . '|' . json_encode($target));
        if (!$force && Cache::has($cacheKey)) {
            return array_merge(Cache::get($cacheKey), ['cache_hit' => true]);
        }

        $slug = (string) config('smp-wordpress-tts.plugin_slug');
        $mainFile = (string) config('smp-wordpress-tts.plugin_main_file');
        $compare = $this->integrity->comparePluginToGithub(
            $server,
            $installId,
            $slug,
            (string) config('smp-wordpress-tts.github_repo'),
            (string) config('smp-wordpress-tts.github_ref'),
            $mainFile
        );

        $stats = ['success' => false, 'message' => 'Plugin not installed.'];
        if (($compare['installed']['plugin']['found'] ?? false) === true) {
            $stats = $this->integrity->collectPluginUsageStats(
                $server,
                $installId,
                $slug,
                (string) config('smp-wordpress-tts.wordpress_option'),
                (array) config('smp-wordpress-tts.usage_meta_keys', [])
            );
        }

        $row = [
            'success' => (bool) ($compare['success'] ?? false),
            'message' => (string) ($compare['message'] ?? 'Scan completed.'),
            'target' => $target,
            'integrity' => $compare,
            'stats' => $stats,
            'credential_status' => $this->credentialStatus(),
            'cache_hit' => false,
        ];

        Cache::put($cacheKey, $row, now()->addMinutes((int) config('smp-wordpress-tts.scan_cache_minutes', 15)));
        return $row;
    }

    public function pushCredentialsToInstall(array $target): array
    {
        $server = WhmServer::findOrFail((int) ($target['server_id'] ?? 0));
        $installId = (int) ($target['install_id'] ?? 0);
        if ($installId <= 0) {
            return ['success' => false, 'message' => 'WordPress install ID is required.'];
        }

        $payload = $this->pluginSettingsPayload();
        $php = <<<'PHP'
$slug = __SLUG__;
$mainFile = __MAIN_FILE__;
$optionName = __OPTION_NAME__;
$incoming = __INCOMING__;
$pluginPath = trailingslashit(WP_PLUGIN_DIR) . $slug . "/" . $mainFile;
if (is_readable($pluginPath) && !class_exists("HexaTextToSpeech")) {
    require_once $pluginPath;
}
$settings = get_option($optionName, []);
if (!is_array($settings)) {
    $settings = [];
}
foreach (["default_provider", "default_profile", "auto_insert_player", "include_title", "max_characters", "profiles"] as $key) {
    if (array_key_exists($key, $incoming)) {
        $settings[$key] = $incoming[$key];
    }
}
$secretCount = 0;
$fieldCount = 0;
foreach ((array) ($incoming["providers"] ?? []) as $provider => $fields) {
    if (!isset($settings["providers"][$provider]) || !is_array($settings["providers"][$provider])) {
        $settings["providers"][$provider] = [];
    }
    foreach ((array) $fields as $field => $info) {
        $fieldCount++;
        $value = (string) ($info["value"] ?? "");
        if (!empty($info["secret"])) {
            if ($value === "") {
                continue;
            }
            $stored = $value;
            if (class_exists("HexaTextToSpeech") && method_exists("HexaTextToSpeech", "encrypt_secret")) {
                try {
                    $method = new ReflectionMethod("HexaTextToSpeech", "encrypt_secret");
                    $method->setAccessible(true);
                    $stored = (string) $method->invoke(null, $value);
                } catch (Throwable $e) {
                    $stored = $value;
                }
            }
            $settings["providers"][$provider][$field] = $stored;
            $secretCount++;
        } else {
            $settings["providers"][$provider][$field] = $value;
        }
    }
}
update_option($optionName, $settings, false);
echo "HEXA_TTS_PUSH:" . wp_json_encode([
    "success" => true,
    "message" => "Text-to-speech settings pushed to WordPress.",
    "field_count" => $fieldCount,
    "secret_count" => $secretCount,
    "default_provider" => (string) ($settings["default_provider"] ?? ""),
]);
PHP;

        $php = str_replace(
            ['__SLUG__', '__MAIN_FILE__', '__OPTION_NAME__', '__INCOMING__'],
            [
                var_export((string) config('smp-wordpress-tts.plugin_slug'), true),
                var_export((string) config('smp-wordpress-tts.plugin_main_file'), true),
                var_export((string) config('smp-wordpress-tts.wordpress_option'), true),
                var_export($payload, true),
            ],
            $php
        );

        $result = $this->wpToolkit->wpCliEval($server, $installId, $php);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($result['message'] ?? 'Credential push failed.')];
        }

        $decoded = $this->decodeMarkedPayload((string) ($result['stdout'] ?? ''), 'HEXA_TTS_PUSH:');
        return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'Could not parse credential push output.'];
    }

    public function updatePlugin(array $target): array
    {
        $server = WhmServer::findOrFail((int) ($target['server_id'] ?? 0));
        $installId = (int) ($target['install_id'] ?? 0);
        if ($installId <= 0) {
            return ['success' => false, 'message' => 'WordPress install ID is required.'];
        }

        $result = $this->integrity->updatePluginFromGithub(
            $server,
            $installId,
            (string) config('smp-wordpress-tts.plugin_slug'),
            (string) config('smp-wordpress-tts.github_repo'),
            (string) config('smp-wordpress-tts.github_ref'),
            (string) config('smp-wordpress-tts.plugin_main_file'),
            true
        );

        return $result;
    }

    public function credentialStatus(): array
    {
        $status = [];
        foreach ($this->providerDefinitions() as $providerId => $provider) {
            $providerStatus = ['configured' => true, 'fields' => []];
            foreach ($provider['fields'] as $fieldId => $field) {
                if (empty($field['secret'])) {
                    continue;
                }
                $exists = $this->credentials->exists(self::CREDENTIAL_SLUG, $this->credentialKey($providerId, $fieldId));
                $providerStatus['fields'][$fieldId] = $exists;
                if (!$exists && !in_array($providerId, ['kokoro', 'piper'], true)) {
                    $providerStatus['configured'] = false;
                }
            }
            $status[$providerId] = $providerStatus;
        }
        return $status;
    }

    public function credentialKey(string $providerId, string $fieldId): string
    {
        return str_replace('-', '_', $providerId . '_' . $fieldId);
    }

    public function settingKey(string $providerId, string $fieldId): string
    {
        return 'smp_wordpress_tts_' . str_replace('-', '_', $providerId . '_' . $fieldId);
    }

    private function fieldValue(string $providerId, string $fieldId, array $field): string
    {
        if (!empty($field['secret'])) {
            return $this->credentials->getMasked(self::CREDENTIAL_SLUG, $this->credentialKey($providerId, $fieldId));
        }

        return (string) Setting::getValue($this->settingKey($providerId, $fieldId), (string) ($field['default'] ?? ''));
    }

    private function providerRuntime(string $providerId): array
    {
        $provider = $this->providerDefinitions()[$providerId] ?? null;
        if (!$provider) {
            return [];
        }

        $values = [];
        foreach ($provider['fields'] as $fieldId => $field) {
            $values[$fieldId] = !empty($field['secret'])
                ? (string) ($this->credentials->get(self::CREDENTIAL_SLUG, $this->credentialKey($providerId, $fieldId)) ?? '')
                : (string) Setting::getValue($this->settingKey($providerId, $fieldId), (string) ($field['default'] ?? ''));
        }

        return $values;
    }

    private function pluginSettingsPayload(): array
    {
        $snapshot = $this->settingsSnapshot();
        $providers = [];
        foreach ($this->providerDefinitions() as $providerId => $provider) {
            foreach ($provider['fields'] as $fieldId => $field) {
                $providers[$providerId][$fieldId] = [
                    'secret' => !empty($field['secret']),
                    'value' => !empty($field['secret'])
                        ? (string) ($this->credentials->get(self::CREDENTIAL_SLUG, $this->credentialKey($providerId, $fieldId)) ?? '')
                        : (string) ($snapshot['providers'][$providerId][$fieldId] ?? ($field['default'] ?? '')),
                ];
            }
        }

        return [
            'default_provider' => $snapshot['default_provider'],
            'default_profile' => $snapshot['default_profile'],
            'auto_insert_player' => $snapshot['auto_insert_player'],
            'include_title' => $snapshot['include_title'],
            'max_characters' => $snapshot['max_characters'],
            'profiles' => $snapshot['profiles'],
            'providers' => $providers,
        ];
    }

    private function testLocalService(array $settings): array
    {
        $base = $this->baseServiceUrl((string) ($settings['service_url'] ?? ''));
        if ($base === '') {
            return ['success' => false, 'message' => 'Service URL is required.'];
        }

        $headers = ['Accept' => 'application/json'];
        if (!empty($settings['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $settings['api_key'];
        }

        foreach (['/health', '/voices', ''] as $path) {
            try {
                $response = Http::withHeaders($headers)->timeout(10)->get(rtrim($base, '/') . $path);
                if ($response->successful()) {
                    return ['success' => true, 'message' => 'Local TTS service responded.', 'detail' => 'Endpoint: ' . ($path ?: '/')];
                }
            } catch (\Throwable $e) {
                $last = $e->getMessage();
            }
        }

        return ['success' => false, 'message' => 'Local TTS service did not respond.', 'detail' => $last ?? 'No endpoint returned HTTP 2xx.'];
    }

    private function testAmazonPolly(array $settings): array
    {
        foreach (['access_key_id', 'secret_access_key', 'region'] as $required) {
            if (empty($settings[$required])) {
                return ['success' => false, 'message' => 'Missing AWS ' . str_replace('_', ' ', $required) . '.'];
            }
        }

        return $this->awsPollyRequest('GET', (string) $settings['region'], '/v1/voices', ['LanguageCode' => 'en-US'], '', (string) $settings['access_key_id'], (string) $settings['secret_access_key']);
    }

    private function testGoogleTts(array $settings): array
    {
        if (empty($settings['api_key'])) {
            return ['success' => false, 'message' => 'Missing Google API key.'];
        }

        $response = Http::timeout(15)->get('https://texttospeech.googleapis.com/v1/voices', [
            'key' => $settings['api_key'],
            'languageCode' => $settings['language'] ?? 'en-US',
        ]);

        if ($response->successful()) {
            $count = count((array) data_get($response->json(), 'voices', []));
            return ['success' => true, 'message' => 'Google Text-to-Speech key validated.', 'detail' => $count . ' voice(s) returned.'];
        }

        return ['success' => false, 'message' => 'Google returned HTTP ' . $response->status(), 'detail' => substr($response->body(), 0, 500)];
    }

    private function testElevenLabs(array $settings): array
    {
        if (empty($settings['api_key'])) {
            return ['success' => false, 'message' => 'Missing ElevenLabs API key.'];
        }

        $response = Http::withHeaders(['xi-api-key' => $settings['api_key']])->timeout(15)->get('https://api.elevenlabs.io/v1/models');
        if ($response->successful()) {
            return ['success' => true, 'message' => 'ElevenLabs API key validated.', 'detail' => count((array) $response->json()) . ' model record(s) returned.'];
        }

        return ['success' => false, 'message' => 'ElevenLabs returned HTTP ' . $response->status(), 'detail' => substr($response->body(), 0, 500)];
    }

    private function testDeepgram(array $settings): array
    {
        if (empty($settings['api_key'])) {
            return ['success' => false, 'message' => 'Missing Deepgram API key.'];
        }

        $response = Http::withHeaders(['Authorization' => 'Token ' . $settings['api_key']])->timeout(15)->get('https://api.deepgram.com/v1/auth/token');
        if ($response->successful()) {
            return ['success' => true, 'message' => 'Deepgram API key validated.', 'detail' => 'The auth token endpoint returned successfully.'];
        }

        return ['success' => false, 'message' => 'Deepgram returned HTTP ' . $response->status(), 'detail' => substr($response->body(), 0, 500)];
    }

    private function testCartesia(array $settings): array
    {
        if (empty($settings['api_key'])) {
            return ['success' => false, 'message' => 'Missing Cartesia API key.'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $settings['api_key'],
            'Cartesia-Version' => $settings['version'] ?: '2026-03-01',
        ])->timeout(15)->get('https://api.cartesia.ai/voices', ['limit' => 1]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Cartesia API key validated.', 'detail' => 'Voices endpoint returned successfully.'];
        }

        return ['success' => false, 'message' => 'Cartesia returned HTTP ' . $response->status(), 'detail' => substr($response->body(), 0, 500)];
    }

    private function awsPollyRequest(string $method, string $region, string $path, array $query, string $body, string $accessKey, string $secretKey): array
    {
        $service = 'polly';
        $host = 'polly.' . $region . '.amazonaws.com';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $canonicalQuery = $this->awsCanonicalQuery($query);
        $payloadHash = hash('sha256', $body);
        $headers = [
            'host' => $host,
            'x-amz-date' => $amzDate,
        ];
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
        }

        $canonicalRequest = strtoupper($method) . "\n" . $path . "\n" . $canonicalQuery . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
        $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $requestHeaders = [
            'x-amz-date' => $amzDate,
            'Authorization' => 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
        ];

        $url = 'https://' . $host . $path . ($canonicalQuery ? '?' . $canonicalQuery : '');
        $response = Http::withHeaders($requestHeaders)->timeout(20)->send($method, $url, ['body' => $body]);

        if ($response->successful()) {
            $count = count((array) data_get($response->json(), 'Voices', []));
            return ['success' => true, 'message' => 'Amazon Polly credentials validated.', 'detail' => $count . ' voice(s) returned.'];
        }

        return ['success' => false, 'message' => 'Amazon Polly returned HTTP ' . $response->status(), 'detail' => substr($response->body(), 0, 500)];
    }

    private function awsCanonicalQuery(array $query): string
    {
        ksort($query);
        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }
        return implode('&', $pairs);
    }

    private function baseServiceUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (str_ends_with($url, '/synthesize')) {
            return substr($url, 0, -11);
        }

        return rtrim($url, '/');
    }

    private function decodeMarkedPayload(string $stdout, string $marker): ?array
    {
        foreach (preg_split("/\r?\n/", $stdout) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, $marker)) {
                continue;
            }
            $json = substr($line, strpos($line, $marker) + strlen($marker));
            $decoded = json_decode(trim($json), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
