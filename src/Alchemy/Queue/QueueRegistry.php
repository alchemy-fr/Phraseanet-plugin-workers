<?php

namespace Alchemy\WorkerPlugin\Queue;

class QueueRegistry
{
    /**
     * @var array
     */
    private $configurations = [];

    public function __construct(array $configs)
    {
        foreach ($configs as $name => $configuration) {

            $this->bindConfiguration($name, $configuration);
        }
    }

    /**
     * @param string $queueName
     * @param array $configuration
     */
    public function bindConfiguration($queueName, array $configuration)
    {
        $this->configurations[$queueName] = $configuration;
    }

    public function getConfigurations()
    {
        return $this->configurations;
    }
}
