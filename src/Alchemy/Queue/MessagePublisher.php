<?php

namespace Alchemy\WorkerPlugin\Queue;

use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use \PhpAmqpLib\Channel\AMQPChannel;
use Psr\Log\LoggerInterface;

class MessagePublisher
{
    const EXPORT_MAIL_TYPE     = 'exportMail';
    const WRITE_LOGS_TYPE      = 'writeLogs';
    const SUBDEF_CREATION_TYPE = 'subdefCreation';
    const WRITE_METADATAS_TYPE = 'writeMetadatas';
    const ASSETS_INGEST_TYPE   = 'assetsIngest';
    const CREATE_RECORD_TYPE   = 'createRecord';
    const WEBHOOK_TYPE         = 'webhook';
    const POPULATE_INDEX_TYPE  = 'populateIndex';

    // worker queue  to be consumed, when no ack , it is requeued to the retry queue
    const EXPORT_QUEUE         = 'export-queue';
    const SUBDEF_QUEUE         = 'subdef-queue';
    const METADATAS_QUEUE      = 'metadatas-queue';
    const LOGS_QUEUE           = 'logs-queue';
    const WEBHOOK_QUEUE        = 'webhook-queue';
    const ASSETS_INGEST_QUEUE  = 'ingest-queue';
    const CREATE_RECORD_QUEUE  = 'createrecord-queue';
    const POPULATE_INDEX_QUEUE = 'populateindex-queue';

    // retry queue
    // we can use these retry queue with TTL, so when message expires it is requeued to the corresponding worker queue
    const RETRY_EXPORT_QUEUE         = 'retry-export-queue';
    const RETRY_SUBDEF_QUEUE         = 'retry-subdef-queue';
    const RETRY_METADATAS_QUEUE      = 'retry-metadatas-queue';
    const RETRY_WEBHOOK_QUEUE        = 'retry-webhook-queue';
    const RETRY_ASSETS_INGEST_QUEUE  = 'retry-ingest-queue';
    const RETRY_CREATE_RECORD_QUEUE  = 'retry-createrecord-queue';
    const RETRY_POPULATE_INDEX_QUEUE = 'retry-populateindex-queue';

    const NEW_RECORD_MESSAGE   = 'newrecord';


    /** @var AMQPConnection $serverConnection */
    private $serverConnection;

    /** @var  Logger */
    private $logger;

    public function __construct(AMQPConnection $serverConnection, LoggerInterface $logger)
    {
        $this->serverConnection = $serverConnection;
        $this->logger           = $logger;
    }

    public function publishMessage(array $payload, $queueName)
    {
        $msg = new AMQPMessage(json_encode($payload));

        $channel = $this->serverConnection->setQueue($queueName);

        $channel->basic_publish($msg, AMQPConnection::ALCHEMY_EXCHANGE, $queueName);

        return true;
    }

    public function connectionClose()
    {
        $this->serverConnection->connectionClose();
    }

    /**
     * @param $message
     * @param string $method
     * @param array $context
     */
    public function pushLog($message, $method = 'info', $context = [])
    {
//        $data['message_type'] = self::LOGS_TYPE;
//        $data['payload']['message'] = $message;
//        $this->publishMessage($data, self::LOGS_QUEUE);

        // write logs directly in file

        call_user_func(array($this->logger, $method), $message, $context);
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel()
    {
        return $this->serverConnection->getChannel();
    }
}
