<?php

return [
    'enabled' => env('SMP_WORDPRESS_TTS_ENABLED', true),
    'plugin_slug' => env('SMP_WORDPRESS_TTS_PLUGIN_SLUG', 'smp-wordpress-text-to-speech'),
    'plugin_main_file' => env('SMP_WORDPRESS_TTS_PLUGIN_MAIN_FILE', 'smp-wordpress-text-to-speech.php'),
    'github_repo' => env('SMP_WORDPRESS_TTS_GITHUB_REPO', 'mikeyperes/smp-wordpress-text-to-speech'),
    'github_ref' => env('SMP_WORDPRESS_TTS_GITHUB_REF', 'main'),
    'wordpress_option' => env('SMP_WORDPRESS_TTS_OPTION', 'hexa_tts_settings'),
    'usage_meta_keys' => [
        '_hexa_tts_audio_url',
        '_hexa_tts_status',
        '_hexa_tts_text_hash',
    ],
    'scan_cache_minutes' => env('SMP_WORDPRESS_TTS_SCAN_CACHE_MINUTES', 15),
];
