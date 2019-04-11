<?php

namespace Alchemy\WorkerPlugin\Worker\Factory;

use Alchemy\WorkerPlugin\Worker\WorkerInterface;

class CallableWorkerFactory implements WorkerFactoryInterface
{
    /**
     * @var callable
     */
    private $factory;

    public function __construct(callable $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @return WorkerInterface
     */
    public function createWorker()
    {
        $factory = $this->factory;
        $worker = $factory();

        if (! $worker instanceof WorkerInterface) {
            throw new \RuntimeException('Invalid worker created, expected an instance of \Alchemy\WorkerPlugin\Worker\WorkerInterface');
        }

        return $worker;
    }
}