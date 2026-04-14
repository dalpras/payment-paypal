<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Provider;

use DalPraS\Payment\Contract\PaymentProviderInterface;
use DalPraS\Payment\Dto\AuthorizeRequest;
use DalPraS\Payment\Dto\AuthorizationResult;
use DalPraS\Payment\Dto\CancelRequest;
use DalPraS\Payment\Dto\CancelResult;
use DalPraS\Payment\Dto\CaptureRequest;
use DalPraS\Payment\Dto\CaptureResult;
use DalPraS\Payment\Dto\CheckoutRequest;
use DalPraS\Payment\Dto\CheckoutResponse;
use DalPraS\Payment\Dto\CompletionRequest;
use DalPraS\Payment\Dto\CompletionResult;
use DalPraS\Payment\Dto\RefundRequest;
use DalPraS\Payment\Dto\RefundResult;
use DalPraS\Payment\Dto\SyncRequest;
use DalPraS\Payment\Dto\SyncResult;
use DalPraS\Payment\Dto\VerificationResult;
use DalPraS\Payment\Dto\WebhookEvent;
use DalPraS\Payment\Enum\PaymentIntent;
use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\PayPal\Config\PayPalConfig;
use DalPraS\Payment\PayPal\Contract\PayPalHttpClientInterface;
use DalPraS\Payment\PayPal\Exception\PayPalConfigurationException;
use DalPraS\Payment\PayPal\Mapper\PayPalOrderMapper;
use DalPraS\Payment\PayPal\Support\PayPalLinkFinder;
use DalPraS\Payment\PayPal\Support\PayPalStatusMapper;
use Psr\Http\Message\ServerRequestInterface;

final class PayPalProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly PayPalConfig $config,
        private readonly PayPalHttpClientInterface $httpClient,
        private readonly PayPalOrderMapper $mapper,
    ) {
    }

    public function code(): string
    {
        return 'paypal';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResponse
    {
        $payload = $this->mapper->mapCreateOrderPayload($request);
        $response = $this->httpClient->createOrder($payload, $request->idempotencyKey);

        $links = is_array($response['links'] ?? null) ? $response['links'] : [];
        $redirectUrl = PayPalLinkFinder::findHref($links, 'payer-action')
            ?? PayPalLinkFinder::findHref($links, 'approve');

        return new CheckoutResponse(
            status: PayPalStatusMapper::fromOrderStatus($response['status'] ?? null),
            redirectRequired: $redirectUrl !== null,
            redirectUrl: $redirectUrl,
            providerPaymentId: $response['id'] ?? null,
            providerToken: $response['id'] ?? null,
            raw: $response,
            message: $response['status'] ?? null,
        );
    }

    public function completeCheckout(CompletionRequest $request): CompletionResult
    {
        $orderId = $request->queryParams['token']
            ?? $request->bodyParams['token']
            ?? $request->expectedProviderPaymentId;

        if (!is_string($orderId) || $orderId === '') {
            throw new PayPalConfigurationException('Missing PayPal order id/token for checkout completion.');
        }

        $intent = $this->resolveIntent($request->bodyParams['intent'] ?? $request->queryParams['intent'] ?? null);

        $response = $intent === PaymentIntent::SALE
            ? $this->httpClient->captureOrder($orderId, $request->idempotencyKey)
            : $this->httpClient->authorizeOrder($orderId, $request->idempotencyKey);

        return new CompletionResult(
            status: $intent === PaymentIntent::SALE ? PaymentStatus::CAPTURED : PaymentStatus::AUTHORIZED,
            providerPaymentId: $orderId,
            transactionIds: $this->extractTransactionIds($response),
            message: $response['status'] ?? null,
            raw: $response,
        );
    }

    public function authorize(AuthorizeRequest $request): AuthorizationResult
    {
        if ($request->providerPaymentId === null || $request->providerPaymentId === '') {
            throw new PayPalConfigurationException('Missing PayPal order id for authorize operation.');
        }

        $response = $this->httpClient->authorizeOrder($request->providerPaymentId, $request->idempotencyKey);

        return new AuthorizationResult(
            status: PaymentStatus::AUTHORIZED,
            providerPaymentId: $request->providerPaymentId,
            transactionIds: $this->extractTransactionIds($response),
            message: $response['status'] ?? null,
            raw: $response,
        );
    }

    public function capture(CaptureRequest $request): CaptureResult
    {
        if ($request->providerPaymentId === null || $request->providerPaymentId === '') {
            throw new PayPalConfigurationException('Missing PayPal order id for capture operation.');
        }

        $response = $this->httpClient->captureOrder($request->providerPaymentId, $request->idempotencyKey);

        return new CaptureResult(
            status: PaymentStatus::CAPTURED,
            providerPaymentId: $request->providerPaymentId,
            transactionIds: $this->extractTransactionIds($response),
            message: $response['status'] ?? null,
            raw: $response,
        );
    }

    public function cancel(CancelRequest $request): CancelResult
    {
        return new CancelResult(
            status: PaymentStatus::UNKNOWN,
            providerPaymentId: $request->providerPaymentId,
            transactionIds: [],
            message: 'Generic PayPal order cancellation is not implemented in this skeleton provider.',
            raw: [],
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $captureId = $request->metadata['capture_id'] ?? $request->providerPaymentId;
        if (!is_string($captureId) || $captureId === '') {
            throw new PayPalConfigurationException('Missing PayPal capture id for refund operation.');
        }

        $payload = $this->mapper->mapRefundPayload($request);
        $response = $this->httpClient->refundCapture($captureId, $payload, $request->idempotencyKey);

        return new RefundResult(
            status: PaymentStatus::REFUNDED,
            providerPaymentId: $captureId,
            transactionIds: array_values(array_filter([$response['id'] ?? null], 'is_string')),
            message: $response['status'] ?? null,
            raw: $response,
        );
    }

    public function sync(SyncRequest $request): SyncResult
    {
        if ($request->providerPaymentId === null || $request->providerPaymentId === '') {
            throw new PayPalConfigurationException('Missing PayPal order id for sync operation.');
        }

        $response = $this->httpClient->getOrder($request->providerPaymentId);

        return new SyncResult(
            status: PayPalStatusMapper::fromOrderStatus($response['status'] ?? null),
            providerPaymentId: $request->providerPaymentId,
            transactionIds: $this->extractTransactionIds($response),
            message: $response['status'] ?? null,
            raw: $response,
        );
    }

    public function parseWebhook(ServerRequestInterface $request): WebhookEvent
    {
        $body = (string) $request->getBody();
        $payload = $body !== '' ? json_decode($body, true, 512, JSON_THROW_ON_ERROR) : [];

        return new WebhookEvent(
            providerCode: $this->code(),
            eventType: (string) ($payload['event_type'] ?? 'unknown'),
            providerPaymentId: $payload['resource']['supplementary_data']['related_ids']['order_id']
                ?? $payload['resource']['id']
                ?? null,
            payload: is_array($payload) ? $payload : [],
            headers: $request->getHeaders(),
        );
    }

    public function verifyWebhook(WebhookEvent $event): VerificationResult
    {
        if ($this->config->webhookId === null || $this->config->webhookId === '') {
            return new VerificationResult(false, 'Missing configured PayPal webhook id.');
        }

        $headers = array_change_key_case($event->headers, CASE_LOWER);
        $payload = [
            'auth_algo' => $this->firstHeader($headers, 'paypal-auth-algo'),
            'cert_url' => $this->firstHeader($headers, 'paypal-cert-url'),
            'transmission_id' => $this->firstHeader($headers, 'paypal-transmission-id'),
            'transmission_sig' => $this->firstHeader($headers, 'paypal-transmission-sig'),
            'transmission_time' => $this->firstHeader($headers, 'paypal-transmission-time'),
            'webhook_id' => $this->config->webhookId,
            'webhook_event' => $event->payload,
        ];

        if (in_array(null, $payload, true)) {
            return new VerificationResult(false, 'Missing required PayPal webhook headers.', $payload);
        }

        $response = $this->httpClient->verifyWebhookSignature($payload);
        $verified = ($response['verification_status'] ?? '') === 'SUCCESS';

        return new VerificationResult(
            verified: $verified,
            message: $response['verification_status'] ?? null,
            raw: $response,
        );
    }

    private function resolveIntent(mixed $value): PaymentIntent
    {
        return match ($value) {
            'authorize' => PaymentIntent::AUTHORIZE,
            'capture_later' => PaymentIntent::CAPTURE_LATER,
            default => PaymentIntent::SALE,
        };
    }

    private function extractTransactionIds(array $payload): array
    {
        $ids = [];

        foreach (($payload['purchase_units'] ?? []) as $unit) {
            if (!is_array($unit)) {
                continue;
            }

            $captures = $unit['payments']['captures'] ?? [];
            foreach ($captures as $capture) {
                if (is_array($capture) && isset($capture['id']) && is_string($capture['id'])) {
                    $ids[] = $capture['id'];
                }
            }

            $authorizations = $unit['payments']['authorizations'] ?? [];
            foreach ($authorizations as $authorization) {
                if (is_array($authorization) && isset($authorization['id']) && is_string($authorization['id'])) {
                    $ids[] = $authorization['id'];
                }
            }
        }

        if (isset($payload['id']) && is_string($payload['id'])) {
            array_unshift($ids, $payload['id']);
        }

        return array_values(array_unique($ids));
    }

    private function firstHeader(array $headers, string $name): ?string
    {
        $value = $headers[$name] ?? null;
        if (!is_array($value) || !isset($value[0]) || !is_string($value[0]) || $value[0] === '') {
            return null;
        }

        return $value[0];
    }
}
