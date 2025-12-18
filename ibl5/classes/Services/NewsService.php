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
    private object $db;
    
    /**
     * @param object $db mysqli connection or duck-typed mock for testing
     */
    public function __construct(object $db)
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
                  VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'english')";
        
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            error_log("NewsService: Failed to prepare createNewsStory: " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param('issssss', $categoryID, $aid, $title, $timestamp, $hometext, $topicID, $aid);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Gets topic ID for a team by team name
     * 
     * @param string $teamName Team name
     * @return int|null Topic ID or null if not found
     */
    public function getTopicIDByTeamName(string $teamName): ?int
    {
        $query = "SELECT topicid FROM nuke_topics WHERE topicname = ?";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            error_log("NewsService: Failed to prepare getTopicIDByTeamName: " . $this->db->error);
            return null;
        }
        
        $stmt->bind_param('s', $teamName);
        if (!$stmt->execute()) {
            error_log("NewsService: Failed to execute getTopicIDByTeamName: " . $stmt->error);
            $stmt->close();
            return null;
        }
        
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return isset($row['topicid']) ? (int) $row['topicid'] : null;
    }
    
    /**
     * Gets category ID by category title
     * 
     * @param string $categoryTitle Category title
     * @return int|null Category ID or null if not found
     */
    public function getCategoryIDByTitle(string $categoryTitle): ?int
    {
        $query = "SELECT catid FROM nuke_stories_cat WHERE title = ?";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            error_log("NewsService: Failed to prepare getCategoryIDByTitle: " . $this->db->error);
            return null;
        }
        
        $stmt->bind_param('s', $categoryTitle);
        if (!$stmt->execute()) {
            error_log("NewsService: Failed to execute getCategoryIDByTitle: " . $stmt->error);
            $stmt->close();
            return null;
        }
        
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return isset($row['catid']) ? (int) $row['catid'] : null;
    }
    
    /**
     * Increments the counter for a category
     * 
     * @param string $categoryTitle Category title
     * @return bool Success status
     */
    public function incrementCategoryCounter(string $categoryTitle): bool
    {
        $query = "UPDATE nuke_stories_cat 
                  SET counter = counter + 1 
                  WHERE title = ?";
        
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            error_log("NewsService: Failed to prepare incrementCategoryCounter: " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param('s', $categoryTitle);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
}
