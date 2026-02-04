<?php

declare(strict_types=1);

class DraftPick
{
    public int $pickID;
    public string $owner;
    public string $originalTeam;
    public int|string $year;
    public int|string $round;
    public ?string $notes;

    /**
     * @param array{pickid: int, ownerofpick: string, teampick: string, year: int|string, round: int|string, notes: string|null} $draftPickRow
     */
    public function __construct(array $draftPickRow)
    {
        $this->pickID = $draftPickRow['pickid'];
        $this->owner = $draftPickRow['ownerofpick'];
        $this->originalTeam = $draftPickRow['teampick'];
        $this->year = $draftPickRow['year'];
        $this->round = $draftPickRow['round'];
        $this->notes = $draftPickRow['notes'];
    }
}