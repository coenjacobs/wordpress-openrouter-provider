<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Admin;

class SettingsPage
{
    public const PAGE_SLUG = 'openrouter-provider';
    public const OPTION_GROUP = 'openrouter-provider';

    public function registerMenu(): void
    {
        add_options_page(
            'OpenRouter',
            'OpenRouter',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors(self::PAGE_SLUG);

        echo '<div class="wrap">';
        echo '<h1>OpenRouter</h1>';
        echo '<form method="post" action="options.php">';

        settings_fields(self::OPTION_GROUP);
        do_settings_sections(self::PAGE_SLUG);
        submit_button('Save Settings');

        echo '</form>';

        $this->renderInfoCard();

        echo '</div>';
    }

    private function renderInfoCard(): void
    {
        $refresh_url = wp_nonce_url(
            add_query_arg(
                ['page' => self::PAGE_SLUG, 'openrouter_refresh_models' => '1'],
                admin_url('options-general.php')
            ),
            'openrouter_refresh_models'
        );

        echo '<div class="card" style="max-width: 600px; margin-top: 20px;">';
        echo '<h2>About OpenRouter</h2>';
        echo '<p>OpenRouter: unified API gateway with hundreds of AI models across multiple providers.</p>';
        echo '<p>';
        echo '<a href="' . esc_url($refresh_url) . '" class="button">Refresh Model List</a> ';
        echo '<a href="https://openrouter.ai" class="button" target="_blank"'
            . ' rel="noopener noreferrer">OpenRouter Website</a>';
        echo '</p>';
        echo '</div>';
    }
}
