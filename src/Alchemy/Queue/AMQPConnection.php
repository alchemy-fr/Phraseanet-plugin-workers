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

    public static $dafaultQueues = [
        MessagePublisher::WRITE_METADATAS_TYPE  => MessagePublisher::METADATAS_QUEUE,
        MessagePublisher::SUBDEF_CREATION_TYPE  => MessagePublisher::SUBDEF_QUEUE,
        MessagePublisher::EXPORT_MAIL_TYPE      => MessagePublisher::EXPORT_QUEUE,
        MessagePublisher::WEBHOOK_TYPE          => MessagePublisher::WEBHOOK_QUEUE,
        MessagePublisher::ASSETS_INGEST_TYPE    => MessagePublisher::ASSETS_INGEST_QUEUE,
        MessagePublisher::CREATE_RECORD_TYPE    => MessagePublisher::CREATE_RECORD_QUEUE,
        MessagePublisher::POPULATE_INDEX_TYPE   => MessagePublisher::POPULATE_INDEX_QUEUE
    ];

    //  the corresponding worker queues and retry queues
    public static $dafaultRetryQueues = [
        MessagePublisher::METADATAS_QUEUE       => MessagePublisher::RETRY_METADATAS_QUEUE,
        MessagePublisher::SUBDEF_QUEUE          => MessagePublisher::RETRY_SUBDEF_QUEUE,
        MessagePublisher::EXPORT_QUEUE          => MessagePublisher::RETRY_EXPORT_QUEUE,
        MessagePublisher::WEBHOOK_QUEUE         => MessagePublisher::RETRY_WEBHOOK_QUEUE,
        MessagePublisher::ASSETS_INGEST_QUEUE   => MessagePublisher::RETRY_ASSETS_INGEST_QUEUE,
        MessagePublisher::CREATE_RECORD_QUEUE   => MessagePublisher::RETRY_CREATE_RECORD_QUEUE,
        MessagePublisher::POPULATE_INDEX_QUEUE  => MessagePublisher::RETRY_POPULATE_INDEX_QUEUE
    ];

    // message TTL in retry queue in millisecond
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

        if (isset(self::$dafaultRetryQueues[$queueName])) {
            $this->channel->queue_declare($queueName, false, true, false, false, false, new AMQPTable([
                'x-dead-letter-exchange'    => self::RETRY_ALCHEMY_EXCHANGE,            // the exchange to which republish a 'dead' message
                'x-dead-letter-routing-key' => self::$dafaultRetryQueues[$queueName]    // the routing key to apply to this 'dead' message
            ]));

            $this->channel->queue_bind($queueName, self::ALCHEMY_EXCHANGE, $queueName);

            // declare also the corresponding retry queue
            // use this to delay the delivery of a message to the alchemy-exchange
            $this->channel->queue_declare(self::$dafaultRetryQueues[$queueName], false, true, false, false, false, new AMQPTable([
                //  uncomment this when we want to treat non-ack message
                'x-dead-letter-exchange'    => AMQPConnection::ALCHEMY_EXCHANGE,
                'x-dead-letter-routing-key' => $queueName,
                'x-message-ttl'             => $this->getTtlPerRouting($queueName)
            ]));

            $this->channel->queue_bind(self::$dafaultRetryQueues[$queueName], AMQPConnection::RETRY_ALCHEMY_EXCHANGE, self::$dafaultRetryQueues[$queueName]);

        } elseif (in_array($queueName, self::$dafaultRetryQueues)) {
            // if it's a retry queue
            $routing = array_search($queueName, AMQPConnection::$dafaultRetryQueues);
            $this->channel->queue_declare($queueName, false, true, false, false, false, new AMQPTable([
                //  uncomment this when we want to treat non-ack message
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
            $this->channel->queue_delete($queuName);
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
        $config = $config['worker_plugin'];

        $messageTtl = isset($config[array_search($routing, AMQPConnection::$dafaultQueues)]) ? $config[array_search($routing, AMQPConnection::$dafaultQueues)] : self::RETRY_DELAY;

        return intval($messageTtl);
    }
}
