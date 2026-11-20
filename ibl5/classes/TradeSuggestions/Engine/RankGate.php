<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

/**
 * What the ranker's callback decided about one deal. `Validator` means the
 * Trading rules refused it; `NoMotive` means one side had no reason to say yes.
 */
enum RankGate
{
    case Pass;
    case Validator;
    case NoMotive;
}
