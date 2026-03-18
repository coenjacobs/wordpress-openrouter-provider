<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider\Models;

use CoenJacobs\OpenRouterProvider\Provider\OpenRouterProvider;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\Models\OpenAiCompatible\ImageGenerationModel as BaseImageGenerationModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Image generation model for OpenRouter.
 *
 * For pure image models (output_modalities: ['image']) that generate images
 * via the chat/completions endpoint with modalities: ["image"].
 */
class ImageGenerationModel extends BaseImageGenerationModel
{
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        return new Request($method, OpenRouterProvider::url($path), $headers, $data, $this->getRequestOptions());
    }
}
