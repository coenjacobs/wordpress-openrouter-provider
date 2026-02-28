<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

class OpenRouterModelMetadataDirectory implements ModelMetadataDirectoryInterface
{
    /** @var array<string, ModelMetadata>|null Cached model metadata map. */
    private ?array $modelMetadataMap = null;

    /**
     * @return list<ModelMetadata>
     */
    public function listModelMetadata(): array
    {
        return array_values($this->getModelMetadataMap());
    }

    public function hasModelMetadata(string $modelId): bool
    {
        return isset($this->getModelMetadataMap()[$modelId]);
    }

    public function getModelMetadata(string $modelId): ModelMetadata
    {
        $map = $this->getModelMetadataMap();

        if (!isset($map[$modelId])) {
            throw new InvalidArgumentException(
                sprintf('Model metadata not found for model ID "%s".', $modelId)
            );
        }

        return $map[$modelId];
    }

    /**
     * @return array<string, ModelMetadata>
     */
    private function getModelMetadataMap(): array
    {
        if ($this->modelMetadataMap !== null) {
            return $this->modelMetadataMap;
        }

        $enabledModels = get_option('openrouter_enabled_models', []);
        if (!is_array($enabledModels) || empty($enabledModels)) {
            $this->modelMetadataMap = [];
            return $this->modelMetadataMap;
        }

        $allModels = $this->fetchAllModels();
        $this->modelMetadataMap = [];

        foreach ($allModels as $model) {
            $modelId = $model['id'];

            if (!in_array($modelId, $enabledModels, true)) {
                continue;
            }

            $this->modelMetadataMap[$modelId] = new ModelMetadata(
                $modelId,
                $model['name'],
                [
                    CapabilityEnum::textGeneration(),
                    CapabilityEnum::chatHistory(),
                ],
                $this->buildSupportedOptions(),
            );
        }

        return $this->modelMetadataMap;
    }

    /**
     * Fetch all models from the API (with transient cache).
     *
     * @return list<array{id: string, name: string, provider: string, free: bool}>
     */
    public function fetchAllModels(): array
    {
        $cached = get_transient('openrouter_models_raw');
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            set_transient('openrouter_models_fetch_error', $response->get_error_message(), HOUR_IN_SECONDS);
            return [];
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            set_transient(
                'openrouter_models_fetch_error',
                sprintf('API returned HTTP %d', $status),
                HOUR_IN_SECONDS
            );
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
            if (!isset($model['id']) || !is_string($model['id'])) {
                continue;
            }

            $modelId = substr($model['id'], 0, 200);
            $pricing = $model['pricing'] ?? [];
            $isFree = (($pricing['prompt'] ?? null) === '0' && ($pricing['completion'] ?? null) === '0');

            $models[] = [
                'id' => $modelId,
                'name' => $model['name'] ?? $modelId,
                'provider' => self::extractProviderFromId($modelId),
                'free' => $isFree,
            ];
        }

        delete_transient('openrouter_models_fetch_error');
        set_transient('openrouter_models_raw', $models, 10 * MINUTE_IN_SECONDS);

        return $models;
    }

    /**
     * Extract the provider prefix from an OpenRouter model ID.
     *
     * OpenRouter model IDs follow the format "provider/model-name".
     */
    public static function extractProviderFromId(string $modelId): string
    {
        $slashPos = strpos($modelId, '/');
        if ($slashPos === false) {
            return 'Other';
        }

        return substr($modelId, 0, $slashPos);
    }

    /**
     * Build supported options for OpenAI-compatible models.
     *
     * @return list<SupportedOption>
     */
    private function buildSupportedOptions(): array
    {
        return [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::frequencyPenalty()),
            new SupportedOption(OptionEnum::presencePenalty()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];
    }
}
