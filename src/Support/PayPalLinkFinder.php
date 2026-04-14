<?php declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Support;

final class PayPalLinkFinder
{
    public static function findHref(array $links, string $rel): ?string
    {
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            if (($link['rel'] ?? null) === $rel && isset($link['href']) && is_string($link['href'])) {
                return $link['href'];
            }
        }

        return null;
    }
}
