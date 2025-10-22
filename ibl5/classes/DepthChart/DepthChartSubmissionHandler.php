<?php

namespace DepthChart;

/**
 * Handles depth chart form submissions
 */
class DepthChartSubmissionHandler
{
    private $db;
    private $repository;
    private $processor;
    private $validator;
    private $view;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->repository = new DepthChartRepository($db);
        $this->processor = new DepthChartProcessor();
        $this->validator = new DepthChartValidator();
        $this->view = new DepthChartView($this->processor);
    }
    
    /**
     * Handles the depth chart submission
     * 
     * @param array $postData POST data
     * @return void Renders response
     */
    public function handleSubmission(array $postData): void
    {
        $season = new \Season($this->db);
        
        $teamName = $postData['Team_Name'];
        $setName = $postData['Set_Name'];
        
        // Process the submission data
        $processedData = $this->processor->processSubmission($postData);
        
        // Validate the submission
        $isValid = $this->validator->validate($processedData, $season->phase);
        
        if (!$isValid) {
            // Display errors
            $errorHtml = $this->validator->getErrorMessagesHtml();
            $this->view->renderSubmissionResult($teamName, $processedData['playerData'], false, $errorHtml);
            return;
        }
        
        // Update database
        $this->saveDepthChart($processedData['playerData'], $teamName);
        
        // Save to file and send email
        $this->saveDepthChartFile($teamName, $setName, $processedData['playerData']);
        
        // Display success
        $this->view->renderSubmissionResult($teamName, $processedData['playerData'], true);
    }
    
    /**
     * Saves depth chart data to database
     * 
     * @param array $playerData Array of player data
     * @param string $teamName Team name
     * @return void
     */
    private function saveDepthChart(array $playerData, string $teamName): void
    {
        foreach ($playerData as $player) {
            $this->repository->updatePlayerDepthChart($player['name'], $player);
        }
        
        $this->repository->updateTeamHistory($teamName);
    }
    
    /**
     * Saves depth chart to file and sends email
     * 
     * @param string $teamName Team name
     * @param string $setName Offensive set name
     * @param array $playerData Player data
     * @return void
     */
    private function saveDepthChartFile(string $teamName, string $setName, array $playerData): void
    {
        $csvContent = $this->processor->generateCsvContent($playerData);
        $filename = 'depthcharts/' . $teamName . '.txt';
        
        if (file_put_contents($filename, $csvContent)) {
            // Send email if not on localhost
            if ($_SERVER['SERVER_NAME'] != "localhost") {
                $emailSubject = $teamName . " Depth Chart - $setName Offensive Set";
                $recipient = 'ibldepthcharts@gmail.com';
                mail($recipient, $emailSubject, $csvContent, "From: ibldepthcharts@gmail.com");
            }
        } else {
            echo "<font color=red>Depth chart failed to save properly; please contact the commissioner with the following details:</font></center><p>";
            var_dump($filename);
            var_dump($csvContent);
        }
    }
}
