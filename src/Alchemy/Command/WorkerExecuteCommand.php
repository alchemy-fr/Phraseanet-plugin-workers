<?php

namespace Alchemy\WorkerPlugin\Command;

use Alchemy\Phrasea\Command\Command;
use Alchemy\WorkerPlugin\Queue\MessageHandler;
use Alchemy\WorkerPlugin\Worker\WorkerInvoker;
use PhpAmqpLib\Channel\AMQPChannel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerExecuteCommand extends Command
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('worker:execute');

        $this->setDescription('Listen queues define on configuration, launch corresponding service for execution')
            ->addOption('preserve-payload', 'p', InputOption::VALUE_NONE, 'Preserve temporary payload file')
            ->addOption('queue-name', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The name of queues to be consuming')
            ->addOption('max-processes', 'm', InputOption::VALUE_REQUIRED, 'The max number of process allow to run (default 4) ')
            ->setHelp('');

        return $this;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $argQueueName = $input->getOption('queue-name');
        $maxProcesses = intval($input->getOption('max-processes'));

        $serverConnection = $this->container['alchemy_service.amqp.connection'];

        /** @var AMQPChannel $channel */
        $channel = $serverConnection->getChannel();

        /** @var WorkerInvoker $workerInvoker */
        $workerInvoker = $this->container['alchemy_service.worker_invoker'];

        if ($input->getOption('max-processes') && $maxProcesses == 0) {
            $output->writeln('<error>Invalid max-processes option.Need an integer</error>');

            return;
        } elseif($maxProcesses) {
            $workerInvoker->setMaxProcessPoolValue($maxProcesses);
        }

        if ($input->getOption('preserve-payload')) {
            $workerInvoker->preservePayloads();
        }

        /** @var MessageHandler $messageHandler */
        $messageHandler = $this->container['alchemy_service.message.handler'];
        $messageHandler->consume($channel, $workerInvoker, $argQueueName);

        while (count($channel->callbacks)) {
            $output->writeln("[*] Waiting for messages. To exit press CTRL+C");
            $channel->wait();
        }

        $serverConnection->connectionClose();
    }

}