<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Mapper;

use DalPraS\Payment\Dto\CheckoutRequest;
use DalPraS\Payment\Dto\RefundRequest;
use DalPraS\Payment\Enum\PaymentIntent;
use DalPraS\Payment\ValueObject\LineItem;

final class PayPalOrderMapper
{
    public function mapCreateOrderPayload(CheckoutRequest $request): array
    {
        $currency = $request->amounts->grandTotal->currency()->value;

        return [
            'intent' => $this->mapIntent($request->intent),
            'purchase_units' => [[
                'reference_id' => $request->merchantReference,
                'description' => $request->metadata['description'] ?? $request->merchantReference,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $request->amounts->grandTotal->decimal(),
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => $currency,
                            'value' => $request->amounts->subtotal->decimal(),
                        ],
                        'tax_total' => [
                            'currency_code' => $currency,
                            'value' => $request->amounts->taxTotal->decimal(),
                        ],
                        'shipping' => [
                            'currency_code' => $currency,
                            'value' => $request->amounts->shippingTotal->decimal(),
                        ],
                        'discount' => [
                            'currency_code' => $currency,
                            'value' => $request->amounts->discountTotal->decimal(),
                        ],
                    ],
                ],
                'items' => array_map(fn(LineItem $item) => $this->mapItem($item, $currency), $request->items),
                'custom_id' => $request->paymentReference,
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => array_filter([
                        'return_url' => $request->returnUrl,
                        'cancel_url' => $request->cancelUrl,
                        'locale' => $request->locale,
                        'brand_name' => $request->providerOptions['brand_name'] ?? null,
                        'shipping_preference' => $request->providerOptions['shipping_preference'] ?? 'NO_SHIPPING',
                        'user_action' => ($request->intent === PaymentIntent::SALE ? 'PAY_NOW' : 'CONTINUE'),
                    ], static fn($value) => $value !== null && $value !== ''),
                    'email_address' => $request->customer->email,
                ],
            ],
        ];
    }

    public function mapRefundPayload(RefundRequest $request): array
    {
        return array_filter([
            'note_to_payer' => $request->metadata['note_to_payer'] ?? null,
            'invoice_id' => $request->metadata['invoice_id'] ?? null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    private function mapIntent(PaymentIntent $intent): string
    {
        return match ($intent) {
            PaymentIntent::SALE => 'CAPTURE',
            PaymentIntent::AUTHORIZE, PaymentIntent::CAPTURE_LATER => 'AUTHORIZE',
        };
    }

    private function mapItem(LineItem $item, string $currency): array
    {
        return [
            'name' => mb_substr($item->name, 0, 127),
            'description' => $item->description,
            'sku' => $item->sku,
            'quantity' => (string) $item->quantity,
            'unit_amount' => [
                'currency_code' => $currency,
                'value' => $item->unitPrice->decimal(),
            ],
            'tax' => [
                'currency_code' => $currency,
                'value' => $item->taxAmount?->decimal() ?? '0.00',
            ],
            'category' => 'DIGITAL_GOODS',
        ];
    }
}
