<?php

namespace Alchemy\WorkerPlugin\Queue;

use Alchemy\WorkerPlugin\Configuration\Config;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class AMQPConnection
{
    const ALCHEMY_EXCHANGE          = 'alchemy-exchange';
    const RETRY_ALCHEMY_EXCHANGE    = 'retry-alchemy-exchange';

    /** @var  AMQPStreamConnection */
    private $connection;
    /** @var  AMQPChannel */
    private $channel;

    private $hostConfig;

    public static $defaultQueues = [
        MessagePublisher::WRITE_METADATAS_TYPE  => MessagePublisher::METADATAS_QUEUE,
        MessagePublisher::SUBDEF_CREATION_TYPE  => MessagePublisher::SUBDEF_QUEUE,
        MessagePublisher::EXPORT_MAIL_TYPE      => MessagePublisher::EXPORT_QUEUE,
        MessagePublisher::WEBHOOK_TYPE          => MessagePublisher::WEBHOOK_QUEUE,
        MessagePublisher::ASSETS_INGEST_TYPE    => MessagePublisher::ASSETS_INGEST_QUEUE,
        MessagePublisher::CREATE_RECORD_TYPE    => MessagePublisher::CREATE_RECORD_QUEUE,
        MessagePublisher::POPULATE_INDEX_TYPE   => MessagePublisher::POPULATE_INDEX_QUEUE,
        MessagePublisher::PULL_QUEUE            => MessagePublisher::PULL_QUEUE
    ];

    //  the corresponding worker queues and retry queues, loop queue
    public static $defaultRetryQueues = [
        MessagePublisher::METADATAS_QUEUE       => MessagePublisher::RETRY_METADATAS_QUEUE,
        MessagePublisher::SUBDEF_QUEUE          => MessagePublisher::RETRY_SUBDEF_QUEUE,
        MessagePublisher::EXPORT_QUEUE          => MessagePublisher::RETRY_EXPORT_QUEUE,
        MessagePublisher::WEBHOOK_QUEUE         => MessagePublisher::RETRY_WEBHOOK_QUEUE,
        MessagePublisher::ASSETS_INGEST_QUEUE   => MessagePublisher::RETRY_ASSETS_INGEST_QUEUE,
        MessagePublisher::CREATE_RECORD_QUEUE   => MessagePublisher::RETRY_CREATE_RECORD_QUEUE,
        MessagePublisher::POPULATE_INDEX_QUEUE  => MessagePublisher::RETRY_POPULATE_INDEX_QUEUE,
        MessagePublisher::PULL_QUEUE            => MessagePublisher::LOOP_PULL_QUEUE
    ];

    // default message TTL in retry queue in millisecond
    const RETRY_DELAY =  10000;

    public function __construct(array $serverConfiguration)
    {
        $this->hostConfig =  $serverConfiguration;

        $this->getChannel();
        $this->declareExchange();
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
        $this->channel->exchange_declare(self::RETRY_ALCHEMY_EXCHANGE, 'direct', false, true, false);
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

        if (isset(self::$defaultRetryQueues[$queueName])) {
            $this->channel->queue_declare($queueName, false, true, false, false, false, new AMQPTable([
                'x-dead-letter-exchange'    => self::RETRY_ALCHEMY_EXCHANGE,            // the exchange to which republish a 'dead' message
                'x-dead-letter-routing-key' => self::$defaultRetryQueues[$queueName]    // the routing key to apply to this 'dead' message
            ]));

            $this->channel->queue_bind($queueName, self::ALCHEMY_EXCHANGE, $queueName);

            // declare also the corresponding retry queue
            // use this to delay the delivery of a message to the alchemy-exchange
            $this->channel->queue_declare(self::$defaultRetryQueues[$queueName], false, true, false, false, false, new AMQPTable([
                'x-dead-letter-exchange'    => AMQPConnection::ALCHEMY_EXCHANGE,
                'x-dead-letter-routing-key' => $queueName,
                'x-message-ttl'             => $this->getTtlPerRouting($queueName)
            ]));

            $this->channel->queue_bind(self::$defaultRetryQueues[$queueName], AMQPConnection::RETRY_ALCHEMY_EXCHANGE, self::$defaultRetryQueues[$queueName]);

        } elseif (in_array($queueName, self::$defaultRetryQueues)) {
            // if it's a retry queue
            $routing = array_search($queueName, AMQPConnection::$defaultRetryQueues);
            $this->channel->queue_declare($queueName, false, true, false, false, false, new AMQPTable([
                'x-dead-letter-exchange'    => AMQPConnection::ALCHEMY_EXCHANGE,
                'x-dead-letter-routing-key' => $routing,
                'x-message-ttl'             => $this->getTtlPerRouting($routing)
            ]));

            $this->channel->queue_bind($queueName, AMQPConnection::RETRY_ALCHEMY_EXCHANGE, $queueName);
        }

        return $this->channel;
    }

    public function reinitializeQueue(array $queuNames)
    {
        foreach ($queuNames as $queuName) {
            if (in_array($queuName, self::$defaultQueues)) {
                $this->channel->queue_purge($queuName);
            } else {
                $this->channel->queue_delete($queuName);
            }

            if (isset(self::$defaultRetryQueues[$queuName])) {
                $this->channel->queue_delete(self::$defaultRetryQueues[$queuName]);
            }

            $this->setQueue($queuName);
        }
    }

    public function connectionClose()
    {
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * @param $routing
     * @return int
     */
    private function getTtlPerRouting($routing)
    {
        $config = Config::getConfiguration();

        if ($routing == MessagePublisher::PULL_QUEUE &&
            isset($config['worker_plugin']['pull_assets']) &&
            isset($config['worker_plugin']['pull_assets']['pullInterval']) ) {
                    // convert in milli second
            return intval($config['worker_plugin']['pull_assets']['pullInterval']) * 1000;
        } elseif (isset($config['worker_plugin']['retry_queue']) &&
            isset($config['worker_plugin']['retry_queue'][array_search($routing, AMQPConnection::$defaultQueues)])) {

            return intval($config['worker_plugin']['retry_queue'][array_search($routing, AMQPConnection::$defaultQueues)]);
        }

        return self::RETRY_DELAY;
    }
}
