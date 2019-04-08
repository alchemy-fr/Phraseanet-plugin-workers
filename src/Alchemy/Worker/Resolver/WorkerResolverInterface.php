<?php

namespace Alchemy\WorkerPlugin\Worker\Resolver;

use Alchemy\WorkerPlugin\Worker\WorkerInterface;

interface WorkerResolverInterface
{
    /**
     * @param string $messageType
     * @param array $message
     * @return WorkerInterface
     */
    public function getWorker($messageType, array $message);
}
