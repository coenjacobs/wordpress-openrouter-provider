<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Multi-modal text generation model for OpenRouter.
 *
 * For models with output_modalities: ['text', 'image'] (e.g. Gemini image-preview).
 * Inherits text generation from TextGenerationModel and adds image generation
 * via the same chat/completions endpoint with modalities: ["image", "text"].
 */
class MultiModalTextGenerationModel extends TextGenerationModel implements ImageGenerationModelInterface
{
    /**
     * {@inheritDoc}
     */
    public function generateImageResult(array $prompt): GenerativeAiResult
    {
        $httpTransporter = $this->getHttpTransporter();
        $params = $this->prepareGenerateImageParams($prompt);
        $request = $this->createRequest(
            HttpMethodEnum::POST(),
            'chat/completions',
            ['Content-Type' => 'application/json'],
            $params
        );

        $request = $this->getRequestAuthentication()->authenticateRequest($request);
        $response = $httpTransporter->send($request);
        $this->throwIfNotSuccessful($response);

        return $this->parseImageResponseToGenerativeAiResult($response);
    }

    /**
     * Prepares the parameters for image generation via chat/completions.
     *
     * @param list<Message> $prompt The prompt messages.
     * @return array<string, mixed> The request parameters.
     */
    protected function prepareGenerateImageParams(array $prompt): array
    {
        $config = $this->getConfig();

        $params = [
            'model' => $this->metadata()->getId(),
            'messages' => $this->prepareImageMessagesParam($prompt),
            'modalities' => ['image', 'text'],
        ];

        $candidateCount = $config->getCandidateCount();
        if ($candidateCount !== null) {
            $params['n'] = $candidateCount;
        }

        $aspectRatio = $config->getOutputMediaAspectRatio();
        if ($aspectRatio !== null) {
            $params['image_config'] = ['aspect_ratio' => $aspectRatio];
        }

        $customOptions = $config->getCustomOptions();
        foreach ($customOptions as $key => $value) {
            if (isset($params[$key])) {
                throw new InvalidArgumentException(
                    sprintf('The custom option "%s" conflicts with an existing parameter.', $key)
                );
            }
            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * Prepares messages for image generation requests.
     *
     * @param list<Message> $prompt The prompt messages.
     * @return list<array<string, mixed>> The prepared messages.
     */
    protected function prepareImageMessagesParam(array $prompt): array
    {
        $messages = [];

        foreach ($prompt as $message) {
            $role = $message->getRole()->isUser() ? 'user' : 'assistant';
            $content = [];

            foreach ($message->getParts() as $part) {
                $text = $part->getText();
                if ($text !== null) {
                    $content[] = ['type' => 'text', 'text' => $text];
                }
            }

            $messages[] = ['role' => $role, 'content' => $content];
        }

        return $messages;
    }

    /**
     * Parses the chat/completions response containing images into a GenerativeAiResult.
     *
     * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response.
     * @return GenerativeAiResult The parsed result.
     */
    protected function parseImageResponseToGenerativeAiResult($response): GenerativeAiResult
    {
        /** @var array<string, mixed> $responseData */
        $responseData = $response->getData();

        if (!isset($responseData['choices']) || !is_array($responseData['choices'])) {
            throw ResponseException::fromMissingData($this->providerMetadata()->getName(), 'choices');
        }

        $candidates = [];
        foreach ($responseData['choices'] as $index => $choiceData) {
            if (!is_array($choiceData) || $choiceData === array_values($choiceData)) {
                throw ResponseException::fromInvalidData(
                    $this->providerMetadata()->getName(),
                    "choices[{$index}]",
                    'The value must be an associative array.'
                );
            }
            $candidates[] = $this->parseImageResponseChoiceToCandidate($choiceData, $index);
        }

        $id = isset($responseData['id']) && is_string($responseData['id']) ? $responseData['id'] : '';

        $tokenUsage = new TokenUsage(0, 0, 0);
        if (isset($responseData['usage']) && is_array($responseData['usage'])) {
            $usage = $responseData['usage'];
            $tokenUsage = new TokenUsage(
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0,
                $usage['total_tokens'] ?? 0
            );
        }

        $providerMetadata = $responseData;
        unset($providerMetadata['id'], $providerMetadata['choices'], $providerMetadata['usage']);

        return new GenerativeAiResult(
            $id,
            $candidates,
            $tokenUsage,
            $this->providerMetadata(),
            $this->metadata(),
            $providerMetadata
        );
    }

    /**
     * Parses a single choice containing images from the response.
     *
     * @param array<string, mixed> $choiceData The choice data.
     * @param int $index The choice index.
     * @return Candidate The parsed candidate.
     */
    protected function parseImageResponseChoiceToCandidate(array $choiceData, int $index): Candidate
    {
        $finishReason = $this->parseFinishReason($choiceData);

        $messageData = $choiceData['message'] ?? [];
        if (!is_array($messageData)) {
            throw ResponseException::fromMissingData(
                $this->providerMetadata()->getName(),
                "choices[{$index}].message"
            );
        }

        $parts = $this->parseImageMessageParts($messageData, $index);

        $message = new Message(MessageRoleEnum::model(), $parts);
        return new Candidate($message, $finishReason);
    }

    /**
     * Parses the finish reason from choice data.
     *
     * @param array<string, mixed> $choiceData The choice data.
     * @return FinishReasonEnum The parsed finish reason.
     */
    private function parseFinishReason(array $choiceData): FinishReasonEnum
    {
        if (!isset($choiceData['finish_reason']) || !is_string($choiceData['finish_reason'])) {
            return FinishReasonEnum::stop();
        }

        switch ($choiceData['finish_reason']) {
            case 'length':
                return FinishReasonEnum::length();
            case 'content_filter':
                return FinishReasonEnum::contentFilter();
            default:
                return FinishReasonEnum::stop();
        }
    }

    /**
     * Parses text and image parts from the message data.
     *
     * @param array<string, mixed> $messageData The message data.
     * @param int $choiceIndex The choice index for error messages.
     * @return list<MessagePart> The parsed message parts.
     */
    private function parseImageMessageParts(array $messageData, int $choiceIndex): array
    {
        $parts = [];

        if (isset($messageData['content']) && is_string($messageData['content']) && $messageData['content'] !== '') {
            $parts[] = new MessagePart($messageData['content']);
        }

        if (isset($messageData['images']) && is_array($messageData['images'])) {
            foreach ($messageData['images'] as $imageIndex => $imageData) {
                if (
                    !is_array($imageData)
                    || !isset($imageData['image_url']['url'])
                    || !is_string($imageData['image_url']['url'])
                ) {
                    throw ResponseException::fromInvalidData(
                        $this->providerMetadata()->getName(),
                        "choices[{$choiceIndex}].message.images[{$imageIndex}]",
                        'Expected image_url.url to be a string.'
                    );
                }

                $file = new File($imageData['image_url']['url']);
                $parts[] = new MessagePart($file);
            }
        }

        if (empty($parts)) {
            throw ResponseException::fromMissingData(
                $this->providerMetadata()->getName(),
                "choices[{$choiceIndex}].message content or images"
            );
        }

        return $parts;
    }
}
