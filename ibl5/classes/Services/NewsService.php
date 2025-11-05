<?php

namespace Services;

/**
 * NewsService - Centralized service for news story operations
 * 
 * This service consolidates news story creation, category management, and topic
 * operations that were duplicated across multiple repository classes.
 * 
 * Responsibilities:
 * - News story creation with proper escaping
 * - Topic ID lookups by team name
 * - Category ID lookups by title
 * - Category counter increments
 */
class NewsService
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Creates a news story
     * 
     * @param int $categoryID Category ID
     * @param int $topicID Topic ID
     * @param string $title Story title
     * @param string $hometext Story content
     * @param string $aid Author ID (defaults to 'Associated Press')
     * @return bool Success status
     */
    public function createNewsStory(
        int $categoryID,
        int $topicID,
        string $title,
        string $hometext,
        string $aid = 'Associated Press'
    ): bool {
        $categoryID = (int) $categoryID;
        $topicID = (int) $topicID;
        $titleEscaped = DatabaseService::escapeString($this->db, $title);
        $hometextEscaped = DatabaseService::escapeString($this->db, $hometext);
        $aidEscaped = DatabaseService::escapeString($this->db, $aid);
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
                   '$aidEscaped',
                   '$titleEscaped',
                   '$timestamp',
                   '$hometextEscaped',
                   $topicID,
                   '$aidEscaped',
                   0,
                   'english')";
        
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
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT topicid FROM nuke_topics WHERE topicname = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return (int) $this->db->sql_result($result, 0, 'topicid');
    }
    
    /**
     * Gets category ID by category title
     * 
     * @param string $categoryTitle Category title
     * @return int|null Category ID or null if not found
     */
    public function getCategoryIDByTitle(string $categoryTitle): ?int
    {
        $categoryTitleEscaped = DatabaseService::escapeString($this->db, $categoryTitle);
        $query = "SELECT catid FROM nuke_stories_cat WHERE title = '$categoryTitleEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return (int) $this->db->sql_result($result, 0, 'catid');
    }
    
    /**
     * Increments the counter for a category
     * 
     * @param string $categoryTitle Category title
     * @return bool Success status
     */
    public function incrementCategoryCounter(string $categoryTitle): bool
    {
        $categoryTitleEscaped = DatabaseService::escapeString($this->db, $categoryTitle);
        $query = "UPDATE nuke_stories_cat 
                  SET counter = counter + 1 
                  WHERE title = '$categoryTitleEscaped'";
        
        return $this->db->sql_query($query) !== false;
    }
}
