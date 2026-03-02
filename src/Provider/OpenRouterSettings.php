<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider;

use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\AbstractModelMetadataDirectory;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\Provider\AbstractProviderSettings;

class OpenRouterSettings extends AbstractProviderSettings
{
    /**
     * Renders the model selection field for the settings page.
     */
    public function renderModelField(): void
    {
        $models = $this->fetchModels();
        $config = $this->getConfig();
        $enabled = get_option($config->getEnabledModelsOption(), []);
        if (!is_array($enabled)) {
            $enabled = [];
        }

        $fetchError = get_transient($config->getErrorTransientKey());
        if (is_string($fetchError) && $fetchError !== '') {
            echo '<div class="notice notice-error inline"><p>'
                . 'Failed to fetch models: ' . esc_html($fetchError)
                . '</p></div>';
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
        $this->enqueueModelSelectorAssets($pluginFile);

        echo '<div class="model-selector" data-default-collapsed="true" data-grouped="true"'
            . ' data-stale-models="' . esc_attr((string) wp_json_encode($staleModels)) . '">';
        echo '<select class="model-selector__filter">';
        echo '<option value="all">All models</option>';
        echo '<option value="free">Free only</option>';
        echo '<option value="paid">Paid only</option>';
        echo '</select>';
        echo '<input type="text" class="model-selector__search" placeholder="Search models..." />';
        echo '<div class="model-selector__chips"></div>';

        $enabledModelsOption = $config->getEnabledModelsOption();

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
                echo '<input type="checkbox" name="' . esc_attr($enabledModelsOption) . '[]"'
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

    protected function createModelMetadataDirectory(): AbstractModelMetadataDirectory
    {
        return new OpenRouterModelMetadataDirectory($this->getConfig());
    }
}
