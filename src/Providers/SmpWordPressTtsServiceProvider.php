<?php

namespace hexa_package_smp_wordpress_tts\Providers;

use hexa_core\Services\PackageRegistryService;
use hexa_package_smp_wordpress_tts\Services\SmpWordPressTtsService;
use Illuminate\Support\ServiceProvider;

class SmpWordPressTtsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/smp-wordpress-tts.php', 'smp-wordpress-tts');
        $this->app->singleton(SmpWordPressTtsService::class);
    }

    public function boot(): void
    {
        if (!config('smp-wordpress-tts.enabled', true)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../../routes/smp-wordpress-tts.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'smp-wordpress-tts');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->publishes([
            __DIR__ . '/../../config/smp-wordpress-tts.php' => config_path('smp-wordpress-tts.php'),
        ], 'smp-wordpress-tts-config');

        if (class_exists(PackageRegistryService::class)) {
            $registry = app(PackageRegistryService::class);
            if (method_exists($registry, 'registerPackage')) {
                $registry->registerPackage('smp-wordpress-tts', 'hexawebsystems/laravel-hexa-package-smp-wordpress-text-to-speech', [
                    'title' => 'SMP WordPress TTS',
                    'color' => 'sky',
                    'icon' => 'M12 18.5a4.5 4.5 0 004.5-4.5V6a4.5 4.5 0 00-9 0v8a4.5 4.5 0 004.5 4.5zm7-4.5a7 7 0 01-14 0m7 7v-3m-4 3h8',
                    'description' => 'Central credential setup, WP Toolkit detection, GitHub integrity checks, stats, and update controls for the SMP WordPress text-to-speech plugin.',
                    'settingsRoute' => 'smp-wordpress-tts.settings',
                    'settingsShellClass' => 'max-w-6xl',
                    'docsSlug' => 'smp-wordpress-tts',
                    'instructions' => [
                        'Store every TTS provider credential through Hexa Core CredentialService.',
                        'Select WHM cPanel accounts, discover WordPress installs with WP Toolkit, then scan for the text-to-speech plugin.',
                        'Use the GitHub integrity check before pushing credentials or running plugin updates.',
                    ],
                    'apiLinks' => [
                        ['label' => 'WordPress plugin GitHub', 'url' => 'https://github.com/mikeyperes/smp-wordpress-text-to-speech'],
                    ],
                ]);
            }

            if (method_exists($registry, 'registerSidebarLink')) {
                $registry->registerSidebarLink(
                    'smp-wordpress-tts.dashboard',
                    'WordPress TTS',
                    'M12 18.5a4.5 4.5 0 004.5-4.5V6a4.5 4.5 0 00-9 0v8a4.5 4.5 0 004.5 4.5zm7-4.5a7 7 0 01-14 0m7 7v-3m-4 3h8',
                    'Publishing',
                    'publishing',
                    88
                );
            }
        }

        if (class_exists(\hexa_core\Services\DocumentationService::class)) {
            app(\hexa_core\Services\DocumentationService::class)->register('smp-wordpress-tts', 'SMP WordPress TTS', 'hexawebsystems/laravel-hexa-package-smp-wordpress-text-to-speech', [
                ['title' => 'Overview', 'content' => '<p>Central management dashboard for the Scale My Publication WordPress text-to-speech plugin. Uses WHM, WP Toolkit, WordPress plugin integrity checks, and Hexa Core credentials.</p>'],
            ]);
        }
    }
}
