<?php

namespace RookieOption;

/**
 * Handles database operations for rookie option transactions
 */
class RookieOptionRepository
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Updates a player's rookie option contract year
     * 
     * @param int $playerID Player ID
     * @param int $draftRound Draft round (1 or 2)
     * @param int $extensionAmount Contract extension amount
     * @return bool Success status
     */
    public function updatePlayerRookieOption(int $playerID, int $draftRound, int $extensionAmount): bool
    {
        $playerID = (int) $playerID;
        $extensionAmount = (int) $extensionAmount;
        
        // First round picks get year 4, second round picks get year 3
        $contractYear = ($draftRound == 1) ? 'cy4' : 'cy3';
        
        $query = "UPDATE ibl_plr 
                  SET `$contractYear` = $extensionAmount 
                  WHERE pid = $playerID";
        
        return $this->db->sql_query($query) !== false;
    }
    
    /**
     * Gets topic ID for a team by team name
     * 
     * @param string $teamName Team name
     * @return int|null Topic ID or null if not found
     */
    public function getTopicIDByTeamName(string $teamName): ?int
    {
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT topicid FROM nuke_topics WHERE topicname = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return (int) $this->db->sql_result($result, 0, 'topicid');
    }
    
    /**
     * Gets category ID for rookie extensions
     * 
     * @return int|null Category ID or null if not found
     */
    public function getRookieExtensionCategoryID(): ?int
    {
        $query = "SELECT catid FROM nuke_stories_cat WHERE title = 'Rookie Extension'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return (int) $this->db->sql_result($result, 0, 'catid');
    }
    
    /**
     * Increments the rookie extension counter
     * 
     * @return bool Success status
     */
    public function incrementRookieExtensionCounter(): bool
    {
        $query = "UPDATE nuke_stories_cat 
                  SET counter = counter + 1 
                  WHERE title = 'Rookie Extension'";
        
        return $this->db->sql_query($query) !== false;
    }
    
    /**
     * Creates a news story for a rookie option exercise
     * 
     * @param int $categoryID Category ID
     * @param int $topicID Topic ID
     * @param string $title Story title
     * @param string $hometext Story content
     * @return bool Success status
     */
    public function createNewsStory(int $categoryID, int $topicID, string $title, string $hometext): bool
    {
        $categoryID = (int) $categoryID;
        $topicID = (int) $topicID;
        $titleEscaped = \Services\DatabaseService::escapeString($this->db, $title);
        $hometextEscaped = \Services\DatabaseService::escapeString($this->db, $hometext);
        $timestamp = date('Y-m-d H:i:s', time());
        
        $query = "INSERT INTO nuke_stories
                  (catid,
                   aid,
                   title,
                   time,
                   hometext,
                   topic,
                   informant,
                   counter,
                   alanguage)
                  VALUES
                  ($categoryID,
                   'Associated Press',
                   '$titleEscaped',
                   '$timestamp',
                   '$hometextEscaped',
                   $topicID,
                   'Associated Press',
                   0,
                   'english')";
        
        return $this->db->sql_query($query) !== false;
    }
}
