<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Config;

final class PayPalConfig
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly bool $sandbox = false,
        public readonly ?string $webhookId = null,
        public readonly ?string $brandName = null,
        public readonly ?string $partnerAttributionId = null,
        public readonly int $timeoutSeconds = 30,
    ) {
    }

    public function baseUri(): string
    {
        return $this->sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function oauthUri(): string
    {
        return $this->baseUri() . '/v1/oauth2/token';
    }
}
