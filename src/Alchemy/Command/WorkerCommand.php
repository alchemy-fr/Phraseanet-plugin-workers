<?php

namespace Alchemy\WorkerPlugin\Command;

use Alchemy\Phrasea\Command\Command;
use Alchemy\Worker\WorkerInvoker;
use Alchemy\WorkerPlugin\Queue\MessageHandler;
use PhpAmqpLib\Channel\AMQPChannel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends Command
{
    /**
     * Constructor
     */
    public function __construct($name = null)
    {
        parent::__construct('worker:execute');

        $this->setDescription('Execute phraseanet worker')
            ->addOption('preserve-payload', 'p', InputOption::VALUE_NONE)
            ->addOption('queue-name', '', InputOption::VALUE_REQUIRED, 'The name of one queue to be consuming')
            ->setHelp('');

        return $this;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $argQueueName = $input->getOption('queue-name');

        $serverConnection = $this->container['worker.amqp.connection'];

        /** @var AMQPChannel $channel */
        $channel = $serverConnection->getChannel();

        /** @var WorkerInvoker $workerInvoker */
        $workerInvoker = $this->container['alchemy_worker.worker_invoker'];

        if ($input->getOption('preserve-payload')) {
            $workerInvoker->preservePayloads();
        }

        /** @var MessageHandler $messageHandler */
        $messageHandler = $this->container['worker.message.handler'];
        $messageHandler->consume($channel, $workerInvoker, $argQueueName);

        while (count($channel->callbacks)) {
            echo " [*] Waiting for messages. To exit press CTRL+C\n";
            $channel->wait();
        }

        $serverConnection->connectionClose();
    }

}
