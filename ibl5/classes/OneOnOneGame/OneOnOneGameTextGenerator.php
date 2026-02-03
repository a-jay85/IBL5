<?php

declare(strict_types=1);

namespace OneOnOneGame;

use Utilities\HtmlSanitizer;

/**
 * OneOnOneGameTextGenerator - Generates play-by-play text for One-on-One games
 * 
 * Provides randomized, entertaining text descriptions for various game events
 * including shots, blocks, steals, and rebounds.
 */
class OneOnOneGameTextGenerator
{
    private const THREE_POINT_DESCRIPTIONS = [
        "launches a three",
        "fires from downtown",
        "shoots from beyond the arc",
        "tosses up a trey",
        "attempts a trifecta",
        "guns up a three-pointer",
        "chucks a long-range bomb",
        "takes a shot from outside the arc",
    ];

    private const OUTSIDE_TWO_DESCRIPTIONS = [
        "takes a perimeter shot",
        "from just inside the arc",
        "gets off a long two",
        "sets, fires, ",
        "throws up a long jump shot",
        "fires a shot from near the top of the key",
        "pulls up along the baseline",
        "elevates for a J",
    ];

    private const DRIVE_DESCRIPTIONS = [
        "slashes into the lane",
        "drives to the basket",
        "lifts up a teardrop on the drive",
        "gets free for a drive with a nifty crossover",
        "fakes left and drives right",
        "floats a hanging jumper on the move",
        "squeezes off a leaping leaner",
        "spins into the paint on a drive",
    ];

    private const POST_DESCRIPTIONS = [
        "with a jump hook from the low block",
        "takes a sweeping skyhook as he powers into the lane",
        "backs down and takes a little turnaround jumper",
        "uses a drop-step to try a layup",
        "elevates inside for a dunk",
        "goes to the up-and-under move",
        "lofts up a soft fadeaway from the paint",
        "flips up a finger roll",
    ];

    private const MADE_SHOT_DESCRIPTIONS = [
        "and connects!",
        "and hits!",
        "and scores!",
        "and knocks it down!",
        "and knocks down the shot!",
        "that rattles around an in!",
        "that hits nothing but net!",
        "that tickles the twine as it goes in!",
        "that swishes cleanly through the net!",
        "and it drops through the bucket!",
        "and gets it to drop!",
        "and practically wills it home!",
        "and hits the shot!",
        "that bounces off the front of the rim, off the back of the rim, then drops!",
        "that hangs on the lip of the rim, but drops!",
        "that drops through the hoop!",
        "and makes the basket!",
        "and gets the bucket!",
    ];

    private const MISSED_SHOT_DESCRIPTIONS = [
        "and misses.",
        "and clanks it off the front of the iron.",
        "but the shot is off-line.",
        "and it's an airball!",
        "but comes up empty.",
        "and the shot is a bit long.",
        "and it rattles around and out.",
        "that hangs on the lip of the rim before falling out.",
        "that caroms off the rim.",
        "but can't connect.",
        "and comes up dry.",
        "but can't get it to fall.",
        "and the shot comes up short.",
        "but it's no good.",
        "and bounces it off the glass and out.",
        "that spins out.",
        "and the ball just won't stay down.",
        "and somehow the ball stays out.",
    ];

    private const BLOCK_DESCRIPTIONS = [
        "knocks it away.",
        "deflects the shot.",
        "swats it away.",
        "gets a hand on the shot.",
        "slaps the ball away.",
        "tips the shot attempt away.",
        "comes up with the block.",
        "recovers in time to get a piece of the shot.",
    ];

    private const STEAL_DESCRIPTIONS = [
        "swipes the ball from",
        "gets a clean pick of",
        "grabs the ball right out of the hands of",
        "steals the ball from",
        "comes up with a steal from",
        "strips the ball away from",
        "pokes the ball away and gets the steal from",
        "pilfers the ball from",
    ];

    /**
     * Get a random three-point shot description
     */
    public function getThreePointText(): string
    {
        return self::THREE_POINT_DESCRIPTIONS[array_rand(self::THREE_POINT_DESCRIPTIONS)];
    }

    /**
     * Get a random outside two-point shot description
     */
    public function getOutsideTwoText(): string
    {
        return self::OUTSIDE_TWO_DESCRIPTIONS[array_rand(self::OUTSIDE_TWO_DESCRIPTIONS)];
    }

    /**
     * Get a random drive description
     */
    public function getDriveText(): string
    {
        return self::DRIVE_DESCRIPTIONS[array_rand(self::DRIVE_DESCRIPTIONS)];
    }

    /**
     * Get a random post move description
     */
    public function getPostText(): string
    {
        return self::POST_DESCRIPTIONS[array_rand(self::POST_DESCRIPTIONS)];
    }

    /**
     * Get a random made shot description
     */
    public function getMadeShotText(): string
    {
        return self::MADE_SHOT_DESCRIPTIONS[array_rand(self::MADE_SHOT_DESCRIPTIONS)];
    }

    /**
     * Get a random missed shot description
     */
    public function getMissedShotText(): string
    {
        return self::MISSED_SHOT_DESCRIPTIONS[array_rand(self::MISSED_SHOT_DESCRIPTIONS)];
    }

    /**
     * Get a random block description
     */
    public function getBlockText(): string
    {
        return self::BLOCK_DESCRIPTIONS[array_rand(self::BLOCK_DESCRIPTIONS)];
    }

    /**
     * Get a random steal description
     */
    public function getStealText(): string
    {
        return self::STEAL_DESCRIPTIONS[array_rand(self::STEAL_DESCRIPTIONS)];
    }

    /**
     * Generate coin flip text
     * 
     * @param bool $isHeads True if heads, false if tails
     * @param string $player1Name Name of player 1
     * @param string $player2Name Name of player 2
     * @return string The coin flip announcement text
     */
    public function getCoinFlipText(bool $isHeads, string $player1Name, string $player2Name): string
    {
        $player1Name = HtmlSanitizer::safeHtmlOutput($player1Name);
        $player2Name = HtmlSanitizer::safeHtmlOutput($player2Name);
        
        if ($isHeads) {
            return "The opening coin flip is heads, so $player1Name gets the ball to start.<br>";
        }
        return "The opening coin flip is tails, so $player2Name gets the ball to start.<br>";
    }

    /**
     * Generate score update text
     * 
     * @param string $player1Name Name of player 1
     * @param int $player1Score Player 1's current score
     * @param string $player2Name Name of player 2
     * @param int $player2Score Player 2's current score
     * @return string Formatted score update
     */
    public function getScoreText(string $player1Name, int $player1Score, string $player2Name, int $player2Score): string
    {
        $player1Name = HtmlSanitizer::safeHtmlOutput($player1Name);
        $player2Name = HtmlSanitizer::safeHtmlOutput($player2Name);
        
        return "<strong style=\"font-weight: bold;\">SCORE: $player1Name $player1Score, $player2Name $player2Score</strong><p>";
    }

    /**
     * Generate foul text
     */
    public function getFoulText(string $defender, string $attacker): string
    {
        $defender = HtmlSanitizer::safeHtmlOutput($defender);
        $attacker = HtmlSanitizer::safeHtmlOutput($attacker);
        
        return "$defender fouls $attacker.<br>";
    }

    /**
     * Generate steal play text
     */
    public function getStealPlayText(string $defender, string $attacker): string
    {
        $defender = HtmlSanitizer::safeHtmlOutput($defender);
        $attacker = HtmlSanitizer::safeHtmlOutput($attacker);
        
        return "$defender " . $this->getStealText() . " $attacker.<br>";
    }

    /**
     * Generate rebound text
     */
    public function getReboundText(string $playerName, bool $isOffensive): string
    {
        $playerName = HtmlSanitizer::safeHtmlOutput($playerName);
        
        if ($isOffensive) {
            return "$playerName gets the (offensive) rebound.<br>";
        }
        return "$playerName gets the rebound.<br>";
    }
}
