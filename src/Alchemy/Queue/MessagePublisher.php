<?php

namespace Alchemy\WorkerPlugin\Queue;

use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class MessagePublisher
{
    const EXPORT_MAIL_TYPE     = 'exportMail';
    const WRITE_LOGS_TYPE      = 'writeLogs';
    const SUBDEF_CREATION_TYPE = 'subdefCreation';
    const WRITE_METADATAS_TYPE = 'writeMetadatas';
    const ASSETS_INGEST_TYPE   = 'assetsIngest';
    const CREATE_RECORD_TYPE   = 'createRecord';

    const EXPORT_QUEUE         = 'export-queue';
    const SUBDEF_QUEUE         = 'subdef-queue';
    const METADATAS_QUEUE      = 'metadatas-queue';
    const LOGS_QUEUE           = 'logs-queue';
    const WEBHOOK_QUEUE        = 'webhook-queue';
    const ASSETS_INGEST_QUEUE  = 'ingest-queue';
    const CREATE_RECORD_QUEUE  = 'createrecord-queue';

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
     */
    public function pushLog($message)
    {
//        $data['message_type'] = self::LOGS_TYPE;
//        $data['payload']['message'] = $message;
//        $this->publishMessage($data, self::LOGS_QUEUE);

        // write logs directly in file
        $this->logger->info($message);
    }
}
