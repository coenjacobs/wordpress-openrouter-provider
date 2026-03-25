<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider\Models;

use CoenJacobs\OpenRouterProvider\Provider\OpenRouterProvider;
use CoenJacobs\OpenRouterProvider\Dependencies\CoenJacobs\WordPressAiProvider\Models\OpenAiCompatible\TextGenerationModel as BaseTextGenerationModel;
use WordPress\AiClient\Messages\DTO\Message;
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

    /**
     * Extends the base request parameters with OpenRouter-specific options.
     *
     * Adds top_k and web_search_options, which the WordPress SDK base class
     * does not include in the API request parameters.
     *
     * @param list<Message> $prompt The prompt messages.
     * @return array<string, mixed> The request parameters.
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $params = parent::prepareGenerateTextParams($prompt);

        $config = $this->getConfig();

        $topK = $config->getTopK();
        if ($topK !== null) {
            $params['top_k'] = $topK;
        }

        $webSearch = $config->getWebSearch();
        if ($webSearch !== null) {
            $params['web_search_options'] = [
                'search_context_size' => 'high',
            ];
        }

        return $params;
    }
}
