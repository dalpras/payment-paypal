<?php

declare(strict_types=1);

namespace DalPraS\Payment\PayPal\Enum;

enum PayPalItemCategory: string
{
    case DIGITAL_GOODS = 'DIGITAL_GOODS';
    case PHYSICAL_GOODS = 'PHYSICAL_GOODS';
}