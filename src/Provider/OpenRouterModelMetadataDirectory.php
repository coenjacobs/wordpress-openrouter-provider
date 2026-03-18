<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider;

use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\AbstractModelMetadataDirectory;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\ModalityDetector;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

class OpenRouterModelMetadataDirectory extends AbstractModelMetadataDirectory
{
    protected function getModelsApiUrl(): string
    {
        return OpenRouterProvider::apiBaseUrl() . '/models';
    }

    /**
     * @param array<string, mixed> $rawModel
     * @return array<string, mixed>|null
     */
    protected function parseModelEntry(array $rawModel): ?array
    {
        $modelId = substr($rawModel['id'], 0, 200);
        $pricing = $rawModel['pricing'] ?? [];
        $isFree = (($pricing['prompt'] ?? null) === '0' && ($pricing['completion'] ?? null) === '0');

        $architecture = $rawModel['architecture'] ?? [];

        return [
            'id' => $modelId,
            'name' => $rawModel['name'] ?? $modelId,
            'provider' => self::extractProviderFromId($modelId),
            'free' => $isFree,
            'input_modalities' => $architecture['input_modalities'] ?? ['text'],
            'output_modalities' => $architecture['output_modalities'] ?? ['text'],
        ];
    }

    protected function detectInputModalities(array $modelData): array
    {
        return ModalityDetector::toModalityEnums($modelData['input_modalities'] ?? ['text']);
    }

    protected function detectOutputModalities(array $modelData): array
    {
        return ModalityDetector::toModalityEnums($modelData['output_modalities'] ?? ['text']);
    }

    /**
     * Detect capabilities based on output modalities.
     *
     * - Output includes only 'image' → [imageGeneration]
     * - Output includes both 'text' and 'image' → [textGeneration, chatHistory, imageGeneration]
     * - Output is text only → [textGeneration, chatHistory]
     *
     * @param array<string, mixed> $modelData
     * @return list<CapabilityEnum>
     */
    protected function detectCapabilities(array $modelData): array
    {
        $outputModalities = $modelData['output_modalities'] ?? ['text'];
        $hasText = in_array('text', $outputModalities, true);
        $hasImage = in_array('image', $outputModalities, true);

        if ($hasText && $hasImage) {
            return [
                CapabilityEnum::textGeneration(),
                CapabilityEnum::chatHistory(),
                CapabilityEnum::imageGeneration(),
            ];
        }

        if ($hasImage) {
            return [
                CapabilityEnum::imageGeneration(),
            ];
        }

        return [
            CapabilityEnum::textGeneration(),
            CapabilityEnum::chatHistory(),
        ];
    }

    /**
     * Build supported options based on model capabilities.
     *
     * @param array<string, mixed> $modelData
     * @return list<SupportedOption>
     */
    protected function buildSupportedOptions(array $modelData): array
    {
        $outputModalities = $modelData['output_modalities'] ?? ['text'];
        $hasText = in_array('text', $outputModalities, true);
        $hasImage = in_array('image', $outputModalities, true);

        if ($hasImage && !$hasText) {
            return $this->buildImageOnlyOptions($modelData);
        }

        if ($hasImage) {
            return $this->buildMixedModalityOptions($modelData);
        }

        return parent::buildSupportedOptions($modelData);
    }

    /**
     * Build options for pure image generation models.
     *
     * @param array<string, mixed> $modelData
     * @return list<SupportedOption>
     */
    private function buildImageOnlyOptions(array $modelData): array
    {
        return [
            new SupportedOption(
                OptionEnum::inputModalities(),
                [$this->detectInputModalities($modelData)]
            ),
            new SupportedOption(
                OptionEnum::outputModalities(),
                [[ModalityEnum::image()]]
            ),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::outputMediaAspectRatio()),
            new SupportedOption(OptionEnum::customOptions()),
        ];
    }

    /**
     * Build options for mixed text+image models.
     *
     * @param array<string, mixed> $modelData
     * @return list<SupportedOption>
     */
    private function buildMixedModalityOptions(array $modelData): array
    {
        $options = parent::buildSupportedOptions($modelData);

        $options[] = new SupportedOption(OptionEnum::candidateCount());
        $options[] = new SupportedOption(OptionEnum::outputMediaAspectRatio());
        $options[] = new SupportedOption(OptionEnum::customOptions());

        return $options;
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
}
