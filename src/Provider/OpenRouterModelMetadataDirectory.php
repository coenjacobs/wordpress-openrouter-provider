<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider;

use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\AbstractModelMetadataDirectory;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\ModalityDetector;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
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
            'supported_parameters' => $rawModel['supported_parameters'] ?? [],
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
     * Build supported options based on model capabilities and supported parameters.
     *
     * Uses the `supported_parameters` field from the OpenRouter API to declare
     * per-model options, rather than a blanket set for all models.
     *
     * @param array<string, mixed> $modelData
     * @return list<SupportedOption>
     */
    protected function buildSupportedOptions(array $modelData): array
    {
        $options = $this->buildBaseOptions($modelData);
        $options = array_merge($options, $this->buildParameterDependentOptions($modelData));

        if ($this->hasImageOutput($modelData)) {
            $options[] = new SupportedOption(OptionEnum::outputMediaAspectRatio());
            $options[] = new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::inline()]);
        }

        return $options;
    }

    /**
     * Build options that are always declared regardless of supported_parameters.
     *
     * @param array<string, mixed> $modelData
     * @return list<SupportedOption>
     */
    private function buildBaseOptions(array $modelData): array
    {
        $options = [
            new SupportedOption(
                OptionEnum::inputModalities(),
                self::modalitySubsets($this->detectInputModalities($modelData))
            ),
            new SupportedOption(
                OptionEnum::outputModalities(),
                self::modalitySubsets($this->detectOutputModalities($modelData))
            ),
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::customOptions()),
        ];

        return $options;
    }

    /**
     * Build options conditionally based on the model's supported_parameters.
     *
     * @param array<string, mixed> $modelData
     * @return list<SupportedOption>
     */
    private function buildParameterDependentOptions(array $modelData): array
    {
        /** @var array<string, callable(): SupportedOption> $parameterMap */
        $parameterMap = [
            'temperature'       => static function () {
                return new SupportedOption(OptionEnum::temperature());
            },
            'top_p'             => static function () {
                return new SupportedOption(OptionEnum::topP());
            },
            'top_k'             => static function () {
                return new SupportedOption(OptionEnum::topK());
            },
            'max_tokens'        => static function () {
                return new SupportedOption(OptionEnum::maxTokens());
            },
            'stop'              => static function () {
                return new SupportedOption(OptionEnum::stopSequences());
            },
            'frequency_penalty' => static function () {
                return new SupportedOption(OptionEnum::frequencyPenalty());
            },
            'presence_penalty'  => static function () {
                return new SupportedOption(OptionEnum::presencePenalty());
            },
            'logprobs'          => static function () {
                return new SupportedOption(OptionEnum::logprobs());
            },
            'top_logprobs'      => static function () {
                return new SupportedOption(OptionEnum::topLogprobs());
            },
            'tools'             => static function () {
                return new SupportedOption(OptionEnum::functionDeclarations());
            },
            'response_format'   => static function () {
                return new SupportedOption(OptionEnum::outputMimeType());
            },
            'structured_outputs' => static function () {
                return new SupportedOption(OptionEnum::outputSchema());
            },
        ];

        $options = [];
        $supportedParameters = $modelData['supported_parameters'] ?? [];

        foreach ($parameterMap as $parameter => $factory) {
            if (in_array($parameter, $supportedParameters, true)) {
                $options[] = $factory();
            }
        }

        return $options;
    }

    /**
     * Check if the model has image output modalities.
     *
     * @param array<string, mixed> $modelData
     * @return bool
     */
    private function hasImageOutput(array $modelData): bool
    {
        $outputModalities = $modelData['output_modalities'] ?? ['text'];
        return in_array('image', $outputModalities, true);
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
