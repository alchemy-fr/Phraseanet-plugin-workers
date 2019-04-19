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

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function publishMessage(array $payload, $queueName)
    {
        /** @var AMQPConnection $serverConnection */
        $serverConnection = $this->app['alchemy_service.amqp.connection'];

        $msg = new AMQPMessage(json_encode($payload));

        $channel = $serverConnection->setQueue($queueName);

        $channel->basic_publish($msg, AMQPConnection::ALCHEMY_EXCHANGE, $queueName);

        return true;
    }

    public function connectionClose()
    {
        $this->app['alchemy_service.amqp.connection']->connectionClose();
    }
}
