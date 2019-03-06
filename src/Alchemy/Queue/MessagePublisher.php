<?php

namespace Alchemy\WorkerPlugin\Queue;

use Alchemy\Queue\Message;
use Alchemy\Queue\MessageQueueRegistry;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MessagePublisher
{
    const SUBDEF_CREATION_TYPE = "subdefCreation";

    /**
     * @var MessageQueueRegistry
     */
    private $queueRegistry;

    /**
     * @var string
     */
    private $queueName;

    public function __construct(MessageQueueRegistry $queueRegistry, $queueName)
    {
        $this->queueRegistry = $queueRegistry;
        $this->queueName = $queueName;
    }

    public function publishMessage(array $payload)
    {
        $queue = $this->queueRegistry->getQueue($this->queueName);

        $queue->publish(new Message(json_encode($payload)));
    }
}