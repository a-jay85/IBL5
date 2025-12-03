<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartSubmissionHandlerInterface;

/**
 * @see DepthChartSubmissionHandlerInterface
 */
class DepthChartSubmissionHandler implements DepthChartSubmissionHandlerInterface
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
     * @see DepthChartSubmissionHandlerInterface::handleSubmission()
     */
    public function handleSubmission(array $postData): void
    {
        $season = new \Season($this->db);
        
        $teamName = $this->sanitizeInput($postData['Team_Name'] ?? '');
        
        if (empty($teamName)) {
            echo "<font color=red><b>Error: Missing required team information.</b></font>";
            return;
        }
        
        $processedData = $this->processor->processSubmission($postData);
        
        $isValid = $this->validator->validate($processedData, $season->phase);
        
        if (!$isValid) {
            $errorHtml = $this->validator->getErrorMessagesHtml();
            $this->view->renderSubmissionResult($teamName, $processedData['playerData'], false, $errorHtml);
            return;
        }
        
        $this->saveDepthChart($processedData['playerData'], $teamName);
        
        $this->saveDepthChartFile($teamName, $processedData['playerData']);
        
        $this->view->renderSubmissionResult($teamName, $processedData['playerData'], true);
    }
    
    private function sanitizeInput(string $input): string
    {
        return trim(strip_tags($input));
    }
    
    private function saveDepthChart(array $playerData, string $teamName): void
    {
        foreach ($playerData as $player) {
            $this->repository->updatePlayerDepthChart($player['name'], $player);
        }
        
        $this->repository->updateTeamHistory($teamName);
    }
    
    private function saveDepthChartFile(string $teamName, array $playerData): void
    {
        $safeTeamName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $teamName);
        $safeTeamName = str_replace(['..', '/', '\\'], '', $safeTeamName);
        
        if (empty($safeTeamName)) {
            echo "<font color=red>Invalid team name for file creation.</font>";
            return;
        }
        
        $csvContent = $this->processor->generateCsvContent($playerData);
        $filename = 'depthcharts/' . $safeTeamName . '.txt';
        
        $csvContent = iconv('UTF-8', 'WINDOWS-1252//TRANSLIT', $csvContent);
        
        $realPath = realpath(dirname($filename));
        $expectedPath = realpath('depthcharts');
        
        if ($realPath !== false && $expectedPath !== false && strpos($realPath, $expectedPath) === 0) {
            if (file_put_contents($filename, $csvContent)) {
                if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] != "localhost") {
                    $rawSubject = $teamName . " Depth Chart";
                    $emailSubject = filter_var($rawSubject, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
                    $emailSubject = strip_tags($emailSubject);
                    $recipient = 'ibldepthcharts@gmail.com';
                    
                    $headers = "From: ibldepthcharts@gmail.com\r\n";
                    $headers .= "Reply-To: ibldepthcharts@gmail.com\r\n";
                    $headers .= "X-Mailer: PHP/" . phpversion();
                    
                    mail($recipient, $emailSubject, $csvContent, $headers);
                }
            } else {
                echo "<font color=red>Depth chart failed to save properly; please contact the commissioner.</font>";
            }
        } else {
            echo "<font color=red>Invalid file path detected. Please contact the commissioner.</font>";
        }
    }
}
