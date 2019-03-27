<?php

namespace Alchemy\WorkerPlugin\Queue;

use Alchemy\Queue\MessageQueueRegistry;
use PhpAmqpLib\Message\AMQPMessage;
use Silex\Application;

class MessagePublisher
{
    const SUBDEF_CREATION_TYPE = "subdefCreation";
    const WRITE_METADATAs_TYPE = "writeMetadatas";
    const LOGS_TYPE            = 'logs';

    /**
     * @var MessageQueueRegistry
     */
    private $queueRegistry;

    private $app;

    /**
     * @var string
     */
    private $queueName;

    public function __construct(MessageQueueRegistry $queueRegistry, Application $app)
    {
        $this->queueRegistry = $queueRegistry;
        $this->app = $app;
        $this->queueName = $app['alchemy_worker.queue_name'];
    }

    public function setQueueName($newQueueName)
    {
        $this->queueName = $newQueueName;
    }

    public function publishMessage(array $payload, $queueName = null)
    {
        /** @var AMQPConnection $serverConnection */
        $serverConnection = $this->app['worker.amqp.connection'];

        $msg = new AMQPMessage(json_encode($payload));

        $channel = $serverConnection->getChannel();
        $channel->basic_publish($msg, 'alchemy-exchange', $queueName?:$this->queueName);
    }

    public function connectionClose()
    {
        $this->app['worker.amqp.connection']->connectionClose();
    }
}
