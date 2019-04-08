<?php

namespace Alchemy\WorkerPlugin\Command;


use Alchemy\Phrasea\Command\Command;
use Alchemy\WorkerPlugin\Queue\QueueRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class WorkerShowConfigCommand extends Command
{
    /** @var  QueueRegistry */
    private $queueRegistry;

    public function __construct()
    {
        parent::__construct('worker:show-configuration');

        $this->setDescription('Show queues configuration');
    }

    public function doExecute(InputInterface $input, OutputInterface $output)
    {
        $this->queueRegistry = $this->container['alchemy_service.queue_registry'];

        $output->writeln([ '', 'Configured queues: ' ]);

        foreach ($this->queueRegistry->getConfigurations() as $name => $configuration) {
            $output->writeln([ '  ' . $name . ': ' . Yaml::dump($configuration, 0), '' ]);
        }
    }
}
