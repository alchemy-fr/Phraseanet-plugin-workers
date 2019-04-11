<?php

namespace Alchemy\WorkerPlugin\Queue;

use PhpAmqpLib\Message\AMQPMessage;
use Silex\Application;

class MessagePublisher
{
    const EXPORT_MAIL_TYPE     = 'exportMail';
    const LOGS_TYPE            = 'logs';
    const SUBDEF_CREATION_TYPE = 'subdefCreation';
    const WRITE_METADATAs_TYPE = 'writeMetadatas';

    const EXPORT_QUEUE         = 'export-queue';
    const SUBDEF_QUEUE         = 'subdef-queue';
    const METADATAS_QUEUE      = 'metadatas-queue';
    const LOGS_QUEUE           = 'logs-queue';
    const WEBHOOK_QUEUE        = 'webhook-queue';

    private $app;

    /**
     * @var string
     */
    private $queueName;

    public function __construct(Application $app)
    {
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
        $serverConnection = $this->app['alchemy_service.amqp.connection'];

        $msg = new AMQPMessage(json_encode($payload));

        $routing = $queueName?:$this->queueName;
        $channel = $serverConnection->setQueue($routing);
        $channel->basic_publish($msg, AMQPConnection::ALCHEMY_EXCHANGE, $routing);
    }

    public function connectionClose()
    {
        $this->app['alchemy_service.amqp.connection']->connectionClose();
    }
}
