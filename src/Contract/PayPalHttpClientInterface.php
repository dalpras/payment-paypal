<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Contract;

interface PayPalHttpClientInterface
{
    public function createOrder(array $payload, ?string $requestId = null): array;
    public function captureOrder(string $orderId, ?string $requestId = null): array;
    public function authorizeOrder(string $orderId, ?string $requestId = null): array;
    public function getOrder(string $orderId): array;
    public function refundCapture(string $captureId, array $payload = [], ?string $requestId = null): array;
    public function verifyWebhookSignature(array $payload): array;
}
