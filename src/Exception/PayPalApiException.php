<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Exception;

use DalPraS\Payment\Exception\PaymentException;

final class PayPalApiException extends PaymentException
{
    public static function fromResponse(int $statusCode, array $payload): self
    {
        $message = $payload['message']
            ?? $payload['error_description']
            ?? $payload['name']
            ?? 'PayPal API request failed.';

        return new self(sprintf('PayPal API error (%d): %s', $statusCode, $message));
    }
}
