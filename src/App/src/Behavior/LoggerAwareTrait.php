<?php

namespace App\Behavior;

use Psr\Log\LoggerInterface;

trait LoggerAwareTrait
{
    private ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    private function log(string $message, string $level = 'info') : void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, sprintf("[%d]\t%s:\t%s", getmypid(), date('Y-m-d H:i:s'), $message));
        }
    }
}
