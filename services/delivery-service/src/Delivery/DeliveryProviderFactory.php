<?php

declare(strict_types=1);

namespace App\Delivery;

/**
 * Selects the active delivery provider from the DELIVERY_PROVIDER env var and
 * exposes it as the DeliveryProviderInterface service (wired in services.yaml).
 * Defaults to the deterministic FakeProvider for any unknown/empty value.
 */
final class DeliveryProviderFactory
{
    public function __construct(
        private readonly FakeProvider $fake,
        private readonly NovaPoshtaClient $novaPoshta,
    ) {
    }

    public function create(string $provider): DeliveryProviderInterface
    {
        return match ($provider) {
            NovaPoshtaClient::NAME => $this->novaPoshta,
            default => $this->fake,
        };
    }
}
