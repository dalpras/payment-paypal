# dalpras/payment-paypal

PayPal Orders v2 connector skeleton for `dalpras/payment-core`.

This package is a **starting point** for a production PayPal connector built on top of the current PayPal Orders v2 flow:

- create order
- redirect buyer to the PayPal approval link
- complete checkout by capture or authorize
- capture previously authorized funds
- refund captures
- sync order state
- parse and verify webhooks

PayPal’s current recommended server-side flow uses the Orders v2 API for create, authorize, capture, and retrieve operations, with approval links returned from order creation. PayPal also supports idempotency through the `PayPal-Request-Id` header. citeturn704885search0turn704885search1turn704885search2turn704885search3

## Status

Skeleton package only.

Included:
- provider implementation against `DalPraS\Payment\Contract\PaymentProviderInterface`
- config object
- OAuth token client
- Orders API client
- mapper from core DTOs to PayPal payloads
- webhook parser stub
- tests for payload mapping and approval link extraction

Not yet included:
- full webhook signature verification
- partner / marketplace headers
- shipping tracking APIs
- advanced payment sources and cards
- partial capture / partial refund details
- production-grade retry policy and observability

## Installation

```bash
composer require dalpras/payment-paypal
```

## Dependencies

This package depends on:
- `dalpras/payment-core`
- `psr/http-client`
- `psr/http-factory`
- `psr/http-message`

Bring your own PSR-18 client and PSR-17 factories.

## Basic usage

```php
use DalPraS\Payment\PayPal\Config\PayPalConfig;
use DalPraS\Payment\PayPal\Http\PayPalHttpClient;
use DalPraS\Payment\PayPal\Mapper\PayPalOrderMapper;
use DalPraS\Payment\PayPal\Provider\PayPalProvider;

$config = new PayPalConfig(
    clientId: 'sandbox-client-id',
    clientSecret: 'sandbox-client-secret',
    sandbox: true,
    webhookId: null,
    brandName: 'My Store'
);

$httpClient = new PayPalHttpClient(
    config: $config,
    httpClient: $psr18Client,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory
);

$provider = new PayPalProvider(
    config: $config,
    httpClient: $httpClient,
    mapper: new PayPalOrderMapper()
);

$response = $provider->createCheckout($checkoutRequest);

if ($response->redirectRequired) {
    header('Location: ' . $response->redirectUrl);
    exit;
}
```

## How it maps to the core package

### `CheckoutRequest`
Mapped to PayPal `POST /v2/checkout/orders`.

- `intent = sale` -> `CAPTURE`
- `intent = authorize` or `capture_later` -> `AUTHORIZE`
- line items -> `purchase_units[0].items`
- totals -> `purchase_units[0].amount.breakdown`
- return/cancel URLs -> `payment_source.paypal.experience_context`
- locale -> `payment_source.paypal.experience_context.locale`
- idempotency key -> `PayPal-Request-Id`

### `CompletionRequest`
Uses `token` from browser return when present, otherwise falls back to the stored provider payment id.

- if internal payment intent resolves to immediate capture: `POST /v2/checkout/orders/{id}/capture`
- if internal payment intent resolves to authorization: `POST /v2/checkout/orders/{id}/authorize`

### `SyncRequest`
Uses `GET /v2/checkout/orders/{id}`.

### `RefundRequest`
Uses `POST /v2/payments/captures/{capture_id}/refund` when a capture id is available.

## Recommended production additions

- persist PayPal order id and approval token on the `Payment` entity after checkout creation
- persist capture ids and authorization ids as operation records
- use browser return only for UX; reconcile with webhook events and sync calls
- keep idempotency keys stable across retries
- log request ids returned by PayPal for support and reconciliation

## Package layout

- `src/Config/PayPalConfig.php`
- `src/Http/PayPalHttpClient.php`
- `src/Mapper/PayPalOrderMapper.php`
- `src/Provider/PayPalProvider.php`
- `src/Support/PayPalStatusMapper.php`
- `src/Support/PayPalLinkFinder.php`
- `src/Exception/*`

## Next steps

A practical next step is to connect this package to your `dalpras/payment-core` `PaymentManager`, then add:
- operation persistence for capture / refund ids
- webhook verification
- PHPUnit fixtures for PayPal sandbox responses
