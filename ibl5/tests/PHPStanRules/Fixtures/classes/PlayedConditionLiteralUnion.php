<?php

declare(strict_types=1);

$repository = new class (new \mysqli()) extends \Database\BaseMysqliRepository {
    public function bareAlias(): string
    {
        return $this->playedCondition('');
    }

    public function boxScoreAlias(): string
    {
        return $this->playedCondition('bs');
    }

    public function unknownAlias(): string
    {
        return $this->playedCondition('xx');
    }
};
