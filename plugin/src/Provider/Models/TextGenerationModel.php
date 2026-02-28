<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider\Models;

use CoenJacobs\OpenRouterProvider\Provider\OpenRouterProvider;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\Models\OpenAiCompatible\TextGenerationModel as BaseTextGenerationModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Text generation model for OpenRouter.
 *
 * All OpenRouter models use the OpenAI-compatible /chat/completions endpoint.
 */
class TextGenerationModel extends BaseTextGenerationModel
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
