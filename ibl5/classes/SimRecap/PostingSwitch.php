<?php

declare(strict_types=1);

namespace SimRecap;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;

/** Commissioner on/off switch for sim-recap Discord posting. Anything but exactly 'On' is off. */
final class PostingSwitch
{
    public const SETTING_KEY = 'Sim Recap Discord Posting';

    public function __construct(private readonly LeagueControlPanelRepositoryInterface $settings)
    {
    }

    public function isOn(): bool
    {
        return $this->settings->getSetting(self::SETTING_KEY) === 'On';
    }
}
