<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Support;

use DalPraS\Payment\Enum\PaymentStatus;

final class PayPalStatusMapper
{
    public static function fromOrderStatus(?string $status): PaymentStatus
    {
        return match ($status) {
            'CREATED', 'SAVED' => PaymentStatus::PENDING_CUSTOMER_ACTION,
            'PAYER_ACTION_REQUIRED', 'APPROVED' => PaymentStatus::PENDING_CUSTOMER_ACTION,
            'COMPLETED' => PaymentStatus::CAPTURED,
            'VOIDED' => PaymentStatus::CANCELLED,
            default => PaymentStatus::UNKNOWN,
        };
    }

    public static function fromWebhookEventType(?string $eventType): PaymentStatus
    {
        return match ($eventType) {
            'CHECKOUT.ORDER.APPROVED' => PaymentStatus::PENDING_CUSTOMER_ACTION,
            'PAYMENT.CAPTURE.COMPLETED' => PaymentStatus::CAPTURED,
            'PAYMENT.CAPTURE.REFUNDED' => PaymentStatus::REFUNDED,
            'PAYMENT.AUTHORIZATION.CREATED' => PaymentStatus::AUTHORIZED,
            'PAYMENT.AUTHORIZATION.VOIDED' => PaymentStatus::CANCELLED,
            default => PaymentStatus::UNKNOWN,
        };
    }
}
