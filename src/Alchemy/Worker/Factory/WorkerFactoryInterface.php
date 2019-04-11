<?php

namespace Alchemy\WorkerPlugin\Worker\Factory;

use Alchemy\WorkerPlugin\Worker\WorkerInterface;

interface WorkerFactoryInterface
{
    /**
     * @return WorkerInterface
     */
    public function createWorker();
}
