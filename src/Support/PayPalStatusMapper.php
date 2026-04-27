<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Support;

use DalPraS\Payment\Enum\PaymentStatus;

final class PayPalStatusMapper
{
    public static function fromOrderStatus(?string $status): PaymentStatus
    {
        return match ($status) {
            'CREATED', 'SAVED' => PaymentStatus::PendingCustomerAction,
            'PAYER_ACTION_REQUIRED', 'APPROVED' => PaymentStatus::PendingCustomerAction,
            'COMPLETED' => PaymentStatus::Captured,
            'VOIDED' => PaymentStatus::Cancelled,
            default => PaymentStatus::Unknown,
        };
    }

    public static function fromWebhookEventType(?string $eventType): PaymentStatus
    {
        return match ($eventType) {
            'CHECKOUT.ORDER.APPROVED' => PaymentStatus::PendingCustomerAction,
            'PAYMENT.CAPTURE.COMPLETED' => PaymentStatus::Captured,
            'PAYMENT.CAPTURE.REFUNDED' => PaymentStatus::Refunded,
            'PAYMENT.AUTHORIZATION.CREATED' => PaymentStatus::Authorized,
            'PAYMENT.AUTHORIZATION.VOIDED' => PaymentStatus::Cancelled,
            default => PaymentStatus::Unknown,
        };
    }
}
