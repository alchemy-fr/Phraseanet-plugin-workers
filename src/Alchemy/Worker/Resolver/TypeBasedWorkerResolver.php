<?php

namespace Alchemy\WorkerPlugin\Worker\Resolver;


use Alchemy\WorkerPlugin\Worker\Factory\WorkerFactoryInterface;
use Alchemy\WorkerPlugin\Worker\WorkerInterface;

class TypeBasedWorkerResolver implements WorkerResolverInterface
{
    /**
     * @var WorkerInterface[]
     */
    private $workers = [];

    /**
     * @var WorkerFactoryInterface[]
     */
    private $factories = [];

    public function addFactory($messageType, WorkerFactoryInterface $workerFactory)
    {
        $this->factories[$messageType] = $workerFactory;
    }

    /**
     * @return WorkerFactoryInterface[]
     */
    public function getFactories()
    {
        return $this->factories;
    }

    public function getWorker($messageType, array $message)
    {
        if (isset($this->workers[$messageType])) {
            return $this->workers[$messageType];
        }

        if (isset($this->factories[$messageType])) {
            return $this->workers[$messageType] = $this->factories[$messageType]->createWorker();
        }

        throw new \RuntimeException('Invalid worker type requested: ' . $messageType);
    }
}
