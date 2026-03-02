<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider;

use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\AbstractModelMetadataDirectory;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\ModalityDetector;

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
