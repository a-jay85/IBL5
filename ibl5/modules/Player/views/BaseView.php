<?php

abstract class BaseView {
    protected $db;
    protected $player;
    protected $playerStats;

    public function __construct($db, Player $player, PlayerStats $playerStats) {
        $this->db = $db;
        $this->player = $player;
        $this->playerStats = $playerStats;
    }
    
    abstract public function render();
}
