<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Tests;

use DalPraS\Payment\PayPal\Contract\PayPalHttpClientInterface;

final class FakePayPalHttpClient implements PayPalHttpClientInterface
{
    public array $lastCreatePayload = [];
    public ?string $lastCreateRequestId = null;

    public function __construct(
        public array $createOrderResponse = [],
        public array $captureOrderResponse = [],
        public array $authorizeOrderResponse = [],
        public array $getOrderResponse = [],
        public array $refundCaptureResponse = [],
        public array $verifyWebhookResponse = [],
    ) {
    }

    public function createOrder(array $payload, ?string $requestId = null): array
    {
        $this->lastCreatePayload = $payload;
        $this->lastCreateRequestId = $requestId;
        return $this->createOrderResponse;
    }

    public function captureOrder(string $orderId, ?string $requestId = null): array
    {
        return $this->captureOrderResponse;
    }

    public function authorizeOrder(string $orderId, ?string $requestId = null): array
    {
        return $this->authorizeOrderResponse;
    }

    public function getOrder(string $orderId): array
    {
        return $this->getOrderResponse;
    }

    public function refundCapture(string $captureId, array $payload = [], ?string $requestId = null): array
    {
        return $this->refundCaptureResponse;
    }

    public function verifyWebhookSignature(array $payload): array
    {
        return $this->verifyWebhookResponse;
    }
}
