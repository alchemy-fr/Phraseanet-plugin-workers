<?php

namespace Alchemy\WorkerPlugin\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Alchemy\Queue\MessageQueueRegistry;

class AMQPConnection
{
    /** @var  AMQPStreamConnection */
    private $connection;
    /** @var  AMQPChannel */
    private $channel;

    public function __construct(MessageQueueRegistry $queueRegistry)
    {
        $this->getChannel();
        $this->declareExchange();

        foreach ($queueRegistry->getConfigurations() as $queue => $config) {
            $this->channel->queue_declare($queue, false, true, false, false);

            $this->channel->queue_bind($queue, 'alchemy-exchange', $queue);
        }
    }

    public function getConnection()
    {
        if (!isset($this->connection)) {
            $this->connection =  new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        }

        return $this->connection;
    }

    public function getChannel()
    {
        if (!isset($this->channel)) {
            $this->channel = $this->getConnection()->channel();
        }

        return $this->channel;
    }

    public function declareExchange()
    {
        $this->channel->exchange_declare('alchemy-exchange', 'direct', false, true, false);
    }

    public function connectionClose()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
