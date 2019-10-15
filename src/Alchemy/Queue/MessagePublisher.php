<?php

namespace Alchemy\WorkerPlugin\Queue;

use Alchemy\WorkerPlugin\Configuration\Config;
use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;

class MessagePublisher
{
    const EXPORT_MAIL_TYPE     = 'exportMail';
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

    public function publishMessage(array $payload, $queueName, $retryCount = null, $workerMessage = '')
    {
        $msg = new AMQPMessage(json_encode($payload));
        $routing = array_search($queueName, AMQPConnection::$dafaultRetryQueues);

        if (count($retryCount) && $routing != false) {
            // ad a message header information
            $headers = new AMQPTable([
                'x-death' => [
                    [
                        'count'         => $retryCount,
                        'exchange'      => AMQPConnection::ALCHEMY_EXCHANGE,
                        'queue'         => $routing,
                        'routing-keys'  => $routing,
                        'reason'        => 'rejected',   // rejected is sended like nack
                        'time'          => new \DateTime('now', new \DateTimeZone('UTC'))
                    ]
                ],
                'worker-message' => $workerMessage
            ]);

            $msg->set('application_headers', $headers);
        }

        $channel = $this->serverConnection->setQueue($queueName);

        $exchange = in_array($queueName, AMQPConnection::$dafaultQueues) ? AMQPConnection::ALCHEMY_EXCHANGE : AMQPConnection::RETRY_ALCHEMY_EXCHANGE;
        $channel->basic_publish($msg, $exchange, $queueName);

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
        // write logs directly in file

        call_user_func(array($this->logger, $method), $message, $context);
    }
}
