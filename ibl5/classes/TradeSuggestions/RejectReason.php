<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Why a candidate deal was dropped. The values are the plain labels the
 * diagnostics print.
 */
enum RejectReason: string
{
    case Untradeable = 'a player in it cannot be traded';
    case RosterFull = 'a roster would go over 15';
    case OverHardCap = 'a team would go over the hard cap';
    case NotMeaningful = 'no rotation player moves';
    case UserGivesTooMuch = 'your side gives up clearly more value';
    case PartnerGivesTooMuch = 'their side gives up clearly more value';
    case NoUserGain = 'does not help your team';
    case NoPartnerGain = 'does not help their team';
    case CapStillOver = 'a team would still be over the hard cap';
    case OneSided = 'only one side gains';
    case PaymentShort = 'the sweetener is too small for the salary taken on';
    case ImportantWorsened = 'a side gives up something it cannot lose';
}
