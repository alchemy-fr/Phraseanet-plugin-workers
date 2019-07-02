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
    const ASSETS_INJEST_TYPE   = 'newAssets';
    const CREATE_RECORD_TYPE   = 'createRecord';

    const EXPORT_QUEUE         = 'export-queue';
    const SUBDEF_QUEUE         = 'subdef-queue';
    const METADATAS_QUEUE      = 'metadatas-queue';
    const LOGS_QUEUE           = 'logs-queue';
    const WEBHOOK_QUEUE        = 'webhook-queue';
    const ASSETS_INJEST_QUEUE  = 'injest-queue';
    const CREATE_RECORD_QUEUE  = 'createrecord-queue';

    const NEW_RECORD_MESSAGE   = 'newrecord';

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

    /**
     * @param $message
     */
    public function pushLog($message)
    {
//        $data['message_type'] = self::LOGS_TYPE;
//        $data['payload']['message'] = $message;
//        $this->publishMessage($data, self::LOGS_QUEUE);

        // write logs directly in file
        $this->app['alchemy_service.logger']->info($message);
    }
}
