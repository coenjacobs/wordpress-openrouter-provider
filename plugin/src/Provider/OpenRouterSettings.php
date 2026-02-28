<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider;

use CoenJacobs\OpenRouterProvider\Admin\SettingsPage;

class OpenRouterSettings
{
    public const PROVIDER_ID = 'openrouter';
    public const CREDENTIALS_OPTION = 'wp_ai_client_provider_credentials';

    /**
     * Check if the API key is configured via environment variable or PHP constant.
     */
    public static function hasEnvApiKey(): bool
    {
        $env = getenv('OPENROUTER_API_KEY');
        if (is_string($env) && $env !== '') {
            return true;
        }

        if (defined('OPENROUTER_API_KEY')) {
            $constant = constant('OPENROUTER_API_KEY');
            return is_string($constant) && $constant !== '';
        }

        return false;
    }

    /**
     * Get the active API key (ENV takes precedence over constant, constant over wp_options).
     */
    public static function getActiveApiKey(): string
    {
        $env = getenv('OPENROUTER_API_KEY');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        if (defined('OPENROUTER_API_KEY')) {
            $constant = constant('OPENROUTER_API_KEY');
            if (is_string($constant) && $constant !== '') {
                return $constant;
            }
        }

        $credentials = get_option(self::CREDENTIALS_OPTION, []);
        if (is_array($credentials) && isset($credentials[self::PROVIDER_ID])) {
            $key = $credentials[self::PROVIDER_ID];
            if (is_string($key)) {
                return $key;
            }
        }

        return '';
    }

    public function registerSettings(): void
    {
        $this->handleRefreshModels();

        register_setting(SettingsPage::OPTION_GROUP, self::CREDENTIALS_OPTION, [
            'type' => 'object',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitizeCredentials'],
        ]);

        register_setting(SettingsPage::OPTION_GROUP, 'openrouter_enabled_models', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitizeEnabledModels'],
        ]);

        add_settings_section(
            'openrouter',
            'OpenRouter',
            [$this, 'renderSectionDescription'],
            SettingsPage::PAGE_SLUG
        );

        add_settings_field(
            'openrouter_api_key',
            'API Key',
            [$this, 'renderApiKeyField'],
            SettingsPage::PAGE_SLUG,
            'openrouter'
        );

        add_settings_field(
            'openrouter_enabled_models',
            'Enabled Models',
            [$this, 'renderModelField'],
            SettingsPage::PAGE_SLUG,
            'openrouter'
        );
    }

    public function renderSectionDescription(): void
    {
        echo '<p>Get your API key from <a href="https://openrouter.ai/settings/keys" target="_blank"'
            . ' rel="noopener noreferrer">openrouter.ai/settings/keys</a>.</p>';
    }

    /**
     * Render the API key settings field, showing env-configured key or an input.
     */
    public function renderApiKeyField(): void
    {
        if (self::hasEnvApiKey()) {
            $key = self::getActiveApiKey();
            $masked = strlen($key) > 8
                ? substr($key, 0, 3) . str_repeat('*', strlen($key) - 7) . substr($key, -4)
                : str_repeat('*', strlen($key));

            $source = getenv('OPENROUTER_API_KEY') !== false && getenv('OPENROUTER_API_KEY') !== ''
                ? 'OPENROUTER_API_KEY environment variable'
                : 'OPENROUTER_API_KEY constant';

            echo '<p>';
            echo '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ';
            echo 'Configured via ' . esc_html($source);
            echo ' (<code>' . esc_html($masked) . '</code>)';
            echo '</p>';

            return;
        }

        $credentials = get_option(self::CREDENTIALS_OPTION, []);
        $value = $credentials[self::PROVIDER_ID] ?? '';
        echo '<input type="password" id="openrouter_api_key"'
            . ' name="' . esc_attr(self::CREDENTIALS_OPTION) . '[' . esc_attr(self::PROVIDER_ID) . ']"'
            . ' value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    }

    /**
     * Render the model selection checkboxes, grouped by provider prefix.
     */
    public function renderModelField(): void
    {
        $models = $this->fetchModels();
        $enabled = get_option('openrouter_enabled_models', []);
        if (!is_array($enabled)) {
            $enabled = [];
        }

        if (empty($models)) {
            echo '<p class="description">No models found. Try <strong>Refresh Model List</strong> below.</p>';
            return;
        }

        $modelIds = array_column($models, 'id');
        $staleModels = array_values(array_diff($enabled, $modelIds));

        $grouped = [];
        foreach ($models as $model) {
            $grouped[$model['provider']][] = $model;
        }
        ksort($grouped);

        $pluginFile = dirname(__DIR__, 2) . '/openrouter-provider.php';

        wp_enqueue_script(
            'openrouter-model-selector',
            plugins_url('assets/model-selector.js', $pluginFile),
            [],
            '0.1.0',
            true
        );

        wp_enqueue_style(
            'openrouter-model-selector',
            plugins_url('assets/model-selector.css', $pluginFile),
            [],
            '0.1.0'
        );

        echo '<div class="model-selector" data-default-collapsed="true" data-grouped="true"'
            . ' data-stale-models="' . esc_attr((string) wp_json_encode($staleModels)) . '">';
        echo '<select class="model-selector__filter">';
        echo '<option value="all">All models</option>';
        echo '<option value="free">Free only</option>';
        echo '<option value="paid">Paid only</option>';
        echo '</select>';
        echo '<input type="text" class="model-selector__search" placeholder="Search models..." />';
        echo '<div class="model-selector__chips"></div>';

        echo '<div class="model-selector__panel">';
        foreach ($grouped as $provider => $providerModels) {
            echo '<div class="model-selector__group" data-group="' . esc_attr($provider) . '">';
            echo '<button type="button" class="model-selector__group-header">';
            echo '<span class="model-selector__group-arrow">&#9656;</span>';
            echo '<span class="model-selector__group-name">' . esc_html($provider) . '</span>';
            echo '<span class="model-selector__group-count"></span>';
            echo '</button>';
            echo '<div class="model-selector__group-body">';
            foreach ($providerModels as $model) {
                $checked = in_array($model['id'], $enabled, true) ? ' checked' : '';
                echo '<label class="model-selector__item"'
                    . ' data-model-id="' . esc_attr($model['id']) . '"'
                    . ' data-model-name="' . esc_attr($model['name']) . '"'
                    . ' data-free="' . ($model['free'] ? '1' : '0') . '">';
                echo '<input type="checkbox" name="openrouter_enabled_models[]"'
                    . ' value="' . esc_attr($model['id']) . '"' . $checked . '>';
                echo '<span class="model-selector__item-label">' . esc_html($model['id']) . '</span>';
                if ($model['name'] !== $model['id']) {
                    echo '<span class="model-selector__item-name">(' . esc_html($model['name']) . ')</span>';
                }
                echo '</label>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '<p class="model-selector__no-results">No models match your search.</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Fetch available models from the API, with transient caching.
     *
     * @return list<array{id: string, name: string, provider: string, free: bool}>
     */
    public function fetchModels(): array
    {
        $cached = get_transient('openrouter_models');
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }

        $modelList = $data['data'] ?? $data;
        if (!is_array($modelList)) {
            return [];
        }

        $models = [];
        foreach ($modelList as $model) {
            if (!isset($model['id'])) {
                continue;
            }

            $provider = $this->extractProviderFromId($model['id']);
            $pricing = $model['pricing'] ?? [];
            $isFree = (($pricing['prompt'] ?? null) === '0' && ($pricing['completion'] ?? null) === '0');

            $models[] = [
                'id' => $model['id'],
                'name' => $model['name'] ?? $model['id'],
                'provider' => $provider,
                'free' => $isFree,
            ];
        }

        usort($models, function ($a, $b) {
            return strcmp($a['id'], $b['id']);
        });

        set_transient('openrouter_models', $models, 10 * MINUTE_IN_SECONDS);

        return $models;
    }

    /**
     * Extract the provider prefix from an OpenRouter model ID.
     *
     * OpenRouter model IDs follow the format "provider/model-name".
     */
    private function extractProviderFromId(string $modelId): string
    {
        $slashPos = strpos($modelId, '/');
        if ($slashPos === false) {
            return 'Other';
        }

        return substr($modelId, 0, $slashPos);
    }

    /**
     * @param mixed $input
     * @return list<string>
     */
    public function sanitizeEnabledModels($input): array
    {
        if (!is_array($input)) {
            return [];
        }

        return array_values(array_map('sanitize_text_field', $input));
    }

    /**
     * Sanitize the credentials option, merging our key into the shared array.
     *
     * @param array|mixed $input
     * @return array
     */
    public function sanitizeCredentials($input): array
    {
        $existing = get_option(self::CREDENTIALS_OPTION, []);
        if (!is_array($existing)) {
            $existing = [];
        }

        if (!is_array($input)) {
            return $existing;
        }

        $new_key = isset($input[self::PROVIDER_ID])
            ? trim($input[self::PROVIDER_ID])
            : ($existing[self::PROVIDER_ID] ?? '');

        $old_key = $existing[self::PROVIDER_ID] ?? '';
        if ($new_key !== $old_key) {
            delete_transient('openrouter_models');
            delete_transient('openrouter_models_raw');
        }

        $existing[self::PROVIDER_ID] = $new_key;

        return $existing;
    }

    private function handleRefreshModels(): void
    {
        if (!isset($_GET['openrouter_refresh_models'])) {
            return;
        }

        if (!check_admin_referer('openrouter_refresh_models')) {
            return;
        }

        delete_transient('openrouter_models');
        delete_transient('openrouter_models_raw');

        wp_safe_redirect(admin_url('options-general.php?page=' . SettingsPage::PAGE_SLUG));
        exit;
    }
}
