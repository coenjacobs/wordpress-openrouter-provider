<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider;

use CoenJacobs\OpenRouterProvider\Admin\SettingsPage;
use CoenJacobs\OpenRouterProvider\Provider\OpenRouterProvider;
use CoenJacobs\OpenRouterProvider\Provider\OpenRouterSettings;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\Provider\AbstractProviderPlugin;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\Provider\ProviderConfig;

class Plugin extends AbstractProviderPlugin
{
    /** @var static|null */
    protected static $instance = null;

    private static ProviderConfig $providerConfig;

    public static function providerConfig(): ProviderConfig
    {
        if (!isset(self::$providerConfig)) {
            self::$providerConfig = new ProviderConfig([
                'providerId' => 'openrouter',
                'providerName' => 'OpenRouter',
                'enabledModelsOption' => 'openrouter_enabled_models',
                'modelsTransientKey' => 'openrouter_models_raw',
                'errorTransientKey' => 'openrouter_models_fetch_error',
                'refreshQueryParam' => 'openrouter_refresh_models',
                'refreshNonceAction' => 'openrouter_refresh_models',
                'pageSlug' => 'openrouter-provider',
                'optionGroup' => 'openrouter-provider',
                'sectionId' => 'openrouter',
                'sectionTitle' => 'OpenRouter',
                'sectionDescriptionHtml' => '<p>Configure your API key on the '
                    . '<a href="' . esc_url(admin_url('options-general.php?page=wpconnectors')) . '">'
                    . 'Connectors settings page</a>.</p>',
                'pageTitle' => 'OpenRouter',
                'menuTitle' => 'OpenRouter',
                'infoCardTitle' => 'About OpenRouter',
                'infoCardDescription' => 'OpenRouter: unified API gateway with hundreds of AI models'
                    . ' across multiple providers.',
                'websiteUrl' => 'https://openrouter.ai',
                'websiteLinkText' => 'OpenRouter Website',
            ]);
        }

        return self::$providerConfig;
    }

    protected function getConfig(): ProviderConfig
    {
        return self::providerConfig();
    }

    protected function getProviderClass(): string
    {
        return OpenRouterProvider::class;
    }

    protected function createSettingsPage()
    {
        return new SettingsPage(self::providerConfig());
    }

    protected function createSettings()
    {
        return new OpenRouterSettings(self::providerConfig());
    }
}
