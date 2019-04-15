<?php

namespace Alchemy\WorkerPlugin\Queue;

use Alchemy\WorkerPlugin\Worker\WorkerInvoker;
use Silex\Application;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;
use PhpAmqpLib\Channel\AMQPChannel;

class MessageHandler
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function consume(AMQPChannel $channel, WorkerInvoker $workerInvoker, $argQueueName, $MWG, $clearMetadatas)
    {
        $publisher = $this->app['alchemy_service.message.publisher'];

        // define consume callbacks
        $callback = function (AMQPMessage $message) use ($channel, $workerInvoker, $publisher, $MWG, $clearMetadatas) {

            $data = json_decode($message->getBody(), true);

            // if write metadatas service, take account args MWG and clear-metadatas
            if ($data['message_type'] == MessagePublisher::WRITE_METADATAs_TYPE) {
                if ($MWG) {
                    $data['payload']['MWG'] = true;
                }

                if ($clearMetadatas) {
                    $data['payload']['clearDoc'] = true;
                }
            }

            try {
                $workerInvoker->invokeWorker($data['message_type'], json_encode($data['payload']));

                $channel->basic_ack($message->delivery_info['delivery_tag']);

                if ($data['message_type'] !==  MessagePublisher::LOGS_TYPE) {
                    $data['payload']['message'] = $data['message_type'].' have been consumed!';
                    $data['message_type'] = MessagePublisher::LOGS_TYPE;

                    $publisher->publishMessage($data, MessagePublisher::LOGS_QUEUE);
                }
            } catch (\Exception $e) {
                $channel->basic_nack($message->delivery_info['delivery_tag']);
            }

        };

        foreach (AMQPConnection::$dafaultQueues as $queueName) {
            if ($argQueueName ) {
                if (in_array($queueName, $argQueueName)) {
                    $channel->basic_consume($queueName, Uuid::uuid4(), false, false, false, false, $callback);
                }
            } else {
                    $channel->basic_consume($queueName, Uuid::uuid4(), false, false, false, false, $callback);
            }
        }

    }

}
