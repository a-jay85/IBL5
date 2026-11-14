<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Why a candidate deal was dropped. The values are the plain labels the
 * diagnostics print.
 */
enum RejectReason: string
{
    case CapStillOver = 'a team would still be over the hard cap';
    case OneSided = 'only one side gains';
    case PaymentShort = 'the sweetener is too small for the salary taken on';
    case ImportantWorsened = 'a side gives up something it cannot lose';
    case NoMotive = 'a side has no reason to say why it takes the deal';
}
