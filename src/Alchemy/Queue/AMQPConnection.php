<?php

namespace Alchemy\WorkerPlugin\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AMQPConnection
{
    const ALCHEMY_EXCHANGE = 'alchemy-exchange';

    /** @var  AMQPStreamConnection */
    private $connection;
    /** @var  AMQPChannel */
    private $channel;

    private $hostConfig;

    /** @var QueueRegistry  */
    private $queueRegistry;

    public function __construct(QueueRegistry $queueRegistry)
    {
        $this->queueRegistry = $queueRegistry;

        $configurations = $this->queueRegistry->getConfigurations();
        $this->hostConfig =  reset($configurations);

        $this->getChannel();
        $this->declareExchange();

        foreach ($this->queueRegistry->getConfigurations() as $queue => $config) {
            $this->setQueue($queue);
        }
    }

    public function getConnection()
    {
        if (!isset($this->connection)) {
            $this->connection =  new AMQPStreamConnection(
                $this->hostConfig['host'],
                $this->hostConfig['port'],
                $this->hostConfig['user'],
                $this->hostConfig['password'],
                $this->hostConfig['vhost']);
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
        $this->channel->exchange_declare(self::ALCHEMY_EXCHANGE, 'direct', false, true, false);
    }

    /**
     * @param $queueName
     * @return AMQPChannel
     */
    public function setQueue($queueName)
    {
        if (!isset($this->channel)) {
            $this->getChannel();
            $this->declareExchange();
        }

        $this->channel->queue_declare($queueName, false, true, false, false);

        $this->channel->queue_bind($queueName, self::ALCHEMY_EXCHANGE, $queueName);

        return $this->channel;
    }

    public function connectionClose()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
