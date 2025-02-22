<?php

class DraftPick
{
    public $pickID;
    public $owner;
    public $originalTeam;
    public $year;
    public $round;
    public $notes;

    public function __construct($draftPickRow)
    {
        $this->pickID = $draftPickRow['pickid'];
        $this->owner = $draftPickRow['ownerofpick'];
        $this->originalTeam = $draftPickRow['teampick'];
        $this->year = $draftPickRow['year'];
        $this->round = $draftPickRow['round'];
        $this->notes = $draftPickRow['notes'];
    }
}