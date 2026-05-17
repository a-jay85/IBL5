<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 6: Reset contract extension attempts.
 *
 * Sets used_extension_this_chunk to 0 for all teams at the start of a sim chunk.
 */
class ResetExtensionAttemptsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly \mysqli $db,
    ) {
    }

    public function getLabel(): string
    {
        return 'Extension attempts reset';
    }

    public function execute(): StepResult
    {
        try {
            $stmt = $this->db->prepare("UPDATE `ibl_team_info` SET used_extension_this_chunk = 0");
            if ($stmt === false) {
                throw new \RuntimeException('Prepare failed: ' . $this->db->error);
            }
            if ($stmt->execute() === false) {
                throw new \RuntimeException('Execute failed: ' . $stmt->error, 1003);
            }
            $stmt->close();
        } catch (\Exception $e) {
            $errorMessage = 'Failed to reset sim contract extension attempts: ' . $e->getMessage();
            \Logging\LoggerFactory::getChannel('db')->error('ResetExtensionAttemptsStep database error', ['error' => $errorMessage]);
            throw new \RuntimeException($errorMessage, 1002);
        }

        return StepResult::success($this->getLabel());
    }
}
