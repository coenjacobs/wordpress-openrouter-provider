<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider\Models;

use CoenJacobs\OpenRouterProvider\Provider\OpenRouterProvider;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use Generator;
use RuntimeException;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Text generation model using the OpenAI Chat Completions API format.
 *
 * All OpenRouter models use the same OpenAI-compatible /chat/completions endpoint.
 */
class TextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface
{
    /**
     * @param Message[] $prompt
     */
    public function streamGenerateTextResult(array $prompt): Generator
    {
        throw new RuntimeException('Streaming is not yet implemented.');
    }

    public function generateTextResult(array $prompt): GenerativeAiResult
    {
        $params = $this->prepareGenerateTextParams($prompt);

        $request = new Request(
            HttpMethodEnum::POST(),
            OpenRouterProvider::url('chat/completions'),
            ['Content-Type' => 'application/json'],
            $params,
            $this->getRequestOptions(),
        );

        $request = $this->getRequestAuthentication()->authenticateRequest($request);
        $response = $this->getHttpTransporter()->send($request);
        ResponseUtil::throwIfNotSuccessful($response);

        return $this->parseResponseToGenerativeAiResult($response);
    }

    /**
     * @param Message[] $prompt
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $config = $this->getConfig();
        $messages = [];

        $systemInstruction = $config->getSystemInstruction();
        if ($systemInstruction !== null) {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }

        foreach ($prompt as $message) {
            $role = $message->getRole()->isUser() ? 'user' : 'assistant';
            $text = $this->extractText($message);
            $messages[] = ['role' => $role, 'content' => $text];
        }

        $params = [
            'model' => $this->metadata()->getId(),
            'messages' => $messages,
        ];

        $maxTokens = $config->getMaxTokens();
        if ($maxTokens !== null) {
            $params['max_tokens'] = $maxTokens;
        }

        $temperature = $config->getTemperature();
        if ($temperature !== null) {
            $params['temperature'] = $temperature;
        }

        $topP = $config->getTopP();
        if ($topP !== null) {
            $params['top_p'] = $topP;
        }

        $frequencyPenalty = $config->getFrequencyPenalty();
        if ($frequencyPenalty !== null) {
            $params['frequency_penalty'] = $frequencyPenalty;
        }

        $presencePenalty = $config->getPresencePenalty();
        if ($presencePenalty !== null) {
            $params['presence_penalty'] = $presencePenalty;
        }

        $stopSequences = $config->getStopSequences();
        if ($stopSequences !== null) {
            $params['stop'] = $stopSequences;
        }

        return $params;
    }

    /**
     * Parse the OpenAI Chat Completions API response into a GenerativeAiResult.
     */
    protected function parseResponseToGenerativeAiResult(Response $response): GenerativeAiResult
    {
        $data = $response->getData();

        $candidates = [];
        foreach ($data['choices'] ?? [] as $choice) {
            $content = $choice['message']['content'] ?? '';
            $finishReason = $this->mapFinishReason($choice['finish_reason'] ?? 'stop');

            $candidates[] = new Candidate(
                new ModelMessage([new MessagePart($content)]),
                $finishReason,
            );
        }

        $usage = $data['usage'] ?? [];

        return new GenerativeAiResult(
            $data['id'] ?? '',
            $candidates,
            new TokenUsage(
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0,
                $usage['total_tokens'] ?? ($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0),
            ),
            $this->providerMetadata(),
            $this->metadata(),
        );
    }

    private function extractText(Message $message): string
    {
        $text = '';

        foreach ($message->getParts() as $part) {
            if ($part->getType()->isText()) {
                $text .= $part->getText();
            }
        }

        return $text;
    }

    private function mapFinishReason(string $reason): FinishReasonEnum
    {
        switch ($reason) {
            case 'stop':
                return FinishReasonEnum::stop();
            case 'length':
                return FinishReasonEnum::length();
            case 'content_filter':
                return FinishReasonEnum::contentFilter();
            case 'tool_calls':
                return FinishReasonEnum::toolCalls();
            default:
                return FinishReasonEnum::stop();
        }
    }
}
