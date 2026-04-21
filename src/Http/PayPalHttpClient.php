<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Http;

use DalPraS\Payment\PayPal\Config\PayPalConfig;
use DalPraS\Payment\PayPal\Contract\PayPalHttpClientInterface;
use DalPraS\Payment\PayPal\Exception\PayPalApiException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class PayPalHttpClient implements PayPalHttpClientInterface
{
    private ?string $accessToken = null;
    private ?\DateTimeImmutable $accessTokenExpiresAt = null;
    public function __construct(
        private readonly PayPalConfig $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
    }

    public function createOrder(array $payload, ?string $requestId = null): array
    {
        return $this->requestJson('POST', '/v2/checkout/orders', $payload, $requestId);
    }

    public function captureOrder(string $orderId, ?string $requestId = null): array
    {
        return $this->requestJson(
            'POST',
            sprintf('/v2/checkout/orders/%s/capture', rawurlencode($orderId)),
            null,
            $requestId
        );
    }

    public function authorizeOrder(string $orderId, ?string $requestId = null): array
    {
        return $this->requestJson(
            'POST',
            sprintf('/v2/checkout/orders/%s/authorize', rawurlencode($orderId)),
            null,
            $requestId
        );
    }

    public function getOrder(string $orderId): array
    {
        return $this->requestJson('GET', sprintf('/v2/checkout/orders/%s', rawurlencode($orderId)));
    }

    public function refundCapture(string $captureId, array $payload = [], ?string $requestId = null): array
    {
        return $this->requestJson('POST', sprintf('/v2/payments/captures/%s/refund', rawurlencode($captureId)), $payload, $requestId);
    }

    public function verifyWebhookSignature(array $payload): array
    {
        return $this->requestJson('POST', '/v1/notifications/verify-webhook-signature', $payload);
    }

    private function requestJson(
        string $method,
        string $path,
        array|object|null $payload = null,
        ?string $requestId = null
    ): array {
        $request = $this->requestFactory
            ->createRequest($method, $this->config->baseUri() . $path)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken())
            ->withHeader('Content-Type', 'application/json');

        if ($this->config->partnerAttributionId !== null) {
            $request = $request->withHeader(
                'PayPal-Partner-Attribution-Id',
                $this->config->partnerAttributionId
            );
        }

        if ($requestId !== null && $requestId !== '') {
            $request = $request->withHeader('PayPal-Request-Id', $requestId);
        }

        if ($method !== 'GET' && $payload !== null) {
            $request = $request->withBody(
                $this->streamFactory->createStream(
                    json_encode($payload, JSON_THROW_ON_ERROR)
                )
            );
        }

        $response = $this->httpClient->sendRequest($request);

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = $body !== '' ? json_decode($body, true, 512, JSON_THROW_ON_ERROR) : [];

        if ($status < 200 || $status >= 300) {
            throw PayPalApiException::fromResponse($status, is_array($decoded) ? $decoded : []);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt !== null) {
            if ($this->accessTokenExpiresAt > new \DateTimeImmutable('+30 seconds')) {
                return $this->accessToken;
            }
        }
        $request = $this->requestFactory->createRequest('POST', $this->config->oauthUri())
            ->withHeader('Accept', 'application/json')
            ->withHeader('Accept-Language', 'en_US')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Authorization', 'Basic ' . base64_encode($this->config->clientId . ':' . $this->config->clientSecret));

        $request = $request->withBody($this->streamFactory->createStream('grant_type=client_credentials'));
        $response = $this->httpClient->sendRequest($request);

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = $body !== '' ? json_decode($body, true, 512, JSON_THROW_ON_ERROR) : [];
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw PayPalApiException::fromResponse($status, is_array($decoded) ? $decoded : []);
        }
        $this->accessToken = (string) ($decoded['access_token'] ?? '');
        $expiresIn = (int) ($decoded['expires_in'] ?? 300);
        $this->accessTokenExpiresAt = new \DateTimeImmutable(sprintf('+%d seconds', max(60, $expiresIn)));
        if ($this->accessToken === '') {
            throw PayPalApiException::fromResponse($status, $decoded);
        }
        return $this->accessToken;
    }
}