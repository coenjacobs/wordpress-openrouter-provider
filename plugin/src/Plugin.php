<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider;

use CoenJacobs\OpenRouterProvider\Admin\SettingsPage;
use CoenJacobs\OpenRouterProvider\Http\WpHttpClient;
use CoenJacobs\OpenRouterProvider\Provider\OpenRouterProvider;
use CoenJacobs\OpenRouterProvider\Provider\OpenRouterSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\HttpTransporter;

class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function setup(): void
    {
        add_action('init', [$this, 'registerProvider'], 5);

        if (is_admin()) {
            $settings_page = new SettingsPage();
            add_action('admin_menu', [$settings_page, 'registerMenu']);

            $settings = new OpenRouterSettings();
            add_action('admin_init', [$settings, 'registerSettings']);
        }
    }

    /**
     * Register the OpenRouter provider with the WordPress AI Client registry.
     */
    public function registerProvider(): void
    {
        if (!class_exists(AiClient::class)) {
            return;
        }

        $registry = AiClient::defaultRegistry();

        if ($registry->hasProvider(OpenRouterProvider::class)) {
            return;
        }

        $registry->registerProvider(OpenRouterProvider::class);

        $api_key = OpenRouterSettings::getActiveApiKey();
        if (!empty($api_key)) {
            $auth = new ApiKeyRequestAuthentication($api_key);
            $registry->setProviderRequestAuthentication('openrouter', $auth);
        }

        // Set up the HTTP transporter if not already configured.
        // This is needed for actual model execution during AI Experiments.
        // Only works when AI Experiments plugin is installed (provides unscoped PSR interfaces).
        try {
            $registry->getHttpTransporter();
        } catch (\Throwable $e) {
            if (class_exists('Nyholm\\Psr7\\Factory\\Psr17Factory')) {
                $factory     = new \Nyholm\Psr7\Factory\Psr17Factory();
                $client      = new WpHttpClient();
                $transporter = new HttpTransporter($client, $factory, $factory);
                $registry->setHttpTransporter($transporter);
            }
        }
    }
}
