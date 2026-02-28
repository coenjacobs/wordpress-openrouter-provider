<?php

declare(strict_types=1);

namespace CoenJacobs\OpenRouterProvider\Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

class OpenRouterProviderAvailability implements ProviderAvailabilityInterface
{
    public function isConfigured(): bool
    {
        return OpenRouterSettings::getActiveApiKey() !== '';
    }
}
