<?php

namespace Alchemy\WorkerPlugin\Worker;

use Psr\Log\LoggerInterface;

class WriteLogsWorker implements WorkerInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(array $payload)
    {
        $message = $payload['message'];
        $this->logger->info($message);
    }
}
