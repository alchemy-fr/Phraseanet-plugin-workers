<?php

namespace Alchemy\WorkerPlugin\Command;

use Alchemy\Phrasea\Command\Command;
use Alchemy\WorkerPlugin\Worker\Resolver\WorkerResolverInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerRunServiceCommand extends Command
{
    /**
     * @var WorkerResolverInterface
     */
    private $workerResolver;

    public function __construct()
    {
        parent::__construct('worker:run-service');

        $this->setDescription('Execute a service')
            ->addArgument('type')
            ->addArgument('body')
            ->addOption('preserve-payload', 'p', InputOption::VALUE_NONE, 'Preserve temporary payload file');

        return $this;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $this->workerResolver = $this->container['alchemy_service.type_based_worker_resolver'];

        $type = $input->getArgument('type');
        $body = file_get_contents($input->getArgument('body'));

        if ($body === false) {
            $output->writeln('Unable to read payload file');

            return;
        }

        $body = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('<error>Invalid message body</error>');

            return;
        }

        $worker = $this->workerResolver->getWorker($type, $body);

        $worker->process($body);

        if (! $input->getOption('preserve-payload')) {
            unlink($input->getArgument('body'));
        }

    }
}
