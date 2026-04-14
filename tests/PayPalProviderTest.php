<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Tests;

use DalPraS\Payment\Dto\CheckoutRequest;
use DalPraS\Payment\Enum\Currency;
use DalPraS\Payment\Enum\PaymentIntent;
use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\PayPal\Config\PayPalConfig;
use DalPraS\Payment\PayPal\Mapper\PayPalOrderMapper;
use DalPraS\Payment\PayPal\Provider\PayPalProvider;
use DalPraS\Payment\ValueObject\Address;
use DalPraS\Payment\ValueObject\AmountBreakdown;
use DalPraS\Payment\ValueObject\Customer;
use DalPraS\Payment\ValueObject\LineItem;
use DalPraS\Payment\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PayPalProviderTest extends TestCase
{
    public function testCreateCheckoutReturnsApprovalLinkAndMapsPayload(): void
    {
        $client = new FakePayPalHttpClient(
            createOrderResponse: [
                'id' => 'ORDER-123',
                'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123'],
                ],
            ],
        );

        $provider = new PayPalProvider(
            new PayPalConfig('client', 'secret', true, null, 'DalPraS Store'),
            $client,
            new PayPalOrderMapper(),
        );

        $response = $provider->createCheckout($this->checkoutRequest());

        self::assertTrue($response->redirectRequired);
        self::assertSame('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123', $response->redirectUrl);
        self::assertSame('ORDER-123', $response->providerPaymentId);
        self::assertSame(PaymentStatus::PENDING_CUSTOMER_ACTION, $response->status);
        self::assertSame('checkout-1', $client->lastCreateRequestId);
        self::assertSame('CAPTURE', $client->lastCreatePayload['intent']);
        self::assertSame('merchant-1', $client->lastCreatePayload['purchase_units'][0]['reference_id']);
        self::assertSame('25.00', $client->lastCreatePayload['purchase_units'][0]['amount']['value']);
        self::assertSame('Widget', $client->lastCreatePayload['purchase_units'][0]['items'][0]['name']);
    }

    public function testMapperUsesAuthorizeForDeferredCaptureIntents(): void
    {
        $mapper = new PayPalOrderMapper();
        $request = $this->checkoutRequest(PaymentIntent::CAPTURE_LATER);
        $payload = $mapper->mapCreateOrderPayload($request);

        self::assertSame('AUTHORIZE', $payload['intent']);
        self::assertSame('CONTINUE', $payload['payment_source']['paypal']['experience_context']['user_action']);
    }

    private function checkoutRequest(PaymentIntent $intent = PaymentIntent::SALE): CheckoutRequest
    {
        $currency = Currency::EUR;

        return new CheckoutRequest(
            providerCode: 'paypal',
            paymentReference: 'payment-1',
            merchantReference: 'merchant-1',
            customer: new Customer(
                email: 'buyer@example.com',
                fullName: 'Example Buyer',
                billingAddress: new Address(line1: 'Via Example 1', city: 'Rome', postalCode: '00100', countryCode: 'IT'),
            ),
            items: [
                new LineItem(
                    sku: 'SKU-1',
                    name: 'Widget',
                    quantity: 1,
                    unitPrice: Money::fromDecimal('20.00', $currency),
                    taxAmount: Money::fromDecimal('5.00', $currency),
                    description: 'Test widget',
                ),
            ],
            amounts: new AmountBreakdown(
                subtotal: Money::fromDecimal('20.00', $currency),
                taxTotal: Money::fromDecimal('5.00', $currency),
                discountTotal: Money::fromDecimal('0.00', $currency),
                shippingTotal: Money::fromDecimal('0.00', $currency),
                grandTotal: Money::fromDecimal('25.00', $currency),
            ),
            returnUrl: 'https://example.com/pay/return',
            cancelUrl: 'https://example.com/pay/cancel',
            webhookUrl: 'https://example.com/pay/webhook',
            intent: $intent,
            locale: 'it-IT',
            idempotencyKey: 'checkout-1',
            correlationId: 'corr-1',
            metadata: ['description' => 'Test order'],
            providerOptions: ['brand_name' => 'DalPraS Store'],
        );
    }
}
