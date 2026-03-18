<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider;

use CoenJacobs\OpenRouterProvider\Plugin;
use CoenJacobs\OpenRouterProvider\Provider\Models\ImageGenerationModel;
use CoenJacobs\OpenRouterProvider\Provider\Models\MultiModalTextGenerationModel;
use CoenJacobs\OpenRouterProvider\Provider\Models\TextGenerationModel;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\Provider\ApiKeyProviderAvailability;
use RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

class OpenRouterProvider extends AbstractApiProvider
{
    protected static function baseUrl(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    public static function apiBaseUrl(): string
    {
        return static::baseUrl();
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'openrouter',
            'OpenRouter',
            ProviderTypeEnum::cloud(),
            'https://openrouter.ai/settings/keys',
            RequestAuthenticationMethod::apiKey(),
        );
    }

    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ApiKeyProviderAvailability(Plugin::providerConfig());
    }

    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new OpenRouterModelMetadataDirectory(Plugin::providerConfig());
    }

    /**
     * Creates the appropriate model instance based on supported capabilities.
     *
     * Routes to MultiModalTextGenerationModel for mixed text+image,
     * ImageGenerationModel for pure image, or TextGenerationModel for text-only.
     *
     * @param ModelMetadata $modelMetadata The model metadata.
     * @param ProviderMetadata $providerMetadata The provider metadata.
     * @return ModelInterface The created model instance.
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $hasTextGeneration = false;
        $hasImageGeneration = false;

        foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
            if ($capability->isTextGeneration()) {
                $hasTextGeneration = true;
            }
            if ($capability->isImageGeneration()) {
                $hasImageGeneration = true;
            }
        }

        if ($hasTextGeneration && $hasImageGeneration) {
            return new MultiModalTextGenerationModel($modelMetadata, $providerMetadata);
        }

        if ($hasImageGeneration) {
            return new ImageGenerationModel($modelMetadata, $providerMetadata);
        }

        if ($hasTextGeneration) {
            return new TextGenerationModel($modelMetadata, $providerMetadata);
        }

        throw new RuntimeException(
            sprintf('No supported capabilities found for model "%s".', $modelMetadata->getId())
        );
    }
}
