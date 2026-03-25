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
     * Adds top_k parameter support for OpenRouter models.
     *
     * The WordPress SDK base class reads topK from ModelConfig but does not
     * include it in the API request parameters. OpenRouter supports top_k
     * for many models, so we add it here.
     *
     * @param list<Message> $prompt The prompt messages.
     * @return array<string, mixed> The request parameters.
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $params = parent::prepareGenerateTextParams($prompt);

        $topK = $this->getConfig()->getTopK();
        if ($topK !== null) {
            $params['top_k'] = $topK;
        }

        return $params;
    }
}
