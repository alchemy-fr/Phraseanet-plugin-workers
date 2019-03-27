<?php

namespace Alchemy\WorkerPlugin\Queue;

use Silex\Application;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;
use Alchemy\Worker\WorkerInvoker;
use Alchemy\Queue\MessageQueueRegistry;
use PhpAmqpLib\Channel\AMQPChannel;

class MessageHandler
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function consume(AMQPChannel $channel, WorkerInvoker $workerInvoker, $argQueueName)
    {
        $publisher = $this->app['worker.event.publisher'];

        // define consume callbacks
        $callback = function (AMQPMessage $message) use ($channel, $workerInvoker, $publisher) {

            $data = json_decode($message->getBody(), true);
            try {
                $workerInvoker->invokeWorker($data['message_type'], json_encode($data['payload']));

                $channel->basic_ack($message->delivery_info['delivery_tag']);

                if ($data['message_type'] !==  MessagePublisher::LOGS_TYPE) {
                    $data['message'] = $data['message_type'].' have been treated';
                    $data['message_type'] = MessagePublisher::LOGS_TYPE;

                    $publisher->publishMessage($data, 'logs-queue');
                }
            } catch (\Exception $e) {
                $channel->basic_nack($message->delivery_info['delivery_tag']);
            }

        };

        /** @var MessageQueueRegistry $queueRegistry */
        $queueRegistry = $this->app['alchemy_worker.queue_registry'];

        foreach ($queueRegistry->getConfigurations() as $queueName => $config) {
            if ($argQueueName ) {
                if ($argQueueName === $queueName) {
                    $channel->basic_consume($queueName, Uuid::uuid4(), false, false, false, false, $callback);
                }
            } else {

                //  TODO : consume logs and write logs in file
                if ($queueName != 'logs-queue') {
                    $channel->basic_consume($queueName, Uuid::uuid4(), false, false, false, false, $callback);
                }
            }

        }
    }

}
