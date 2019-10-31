<?php

namespace Alchemy\WorkerPlugin\Queue;

use Alchemy\WorkerPlugin\Worker\ProcessPool;
use Alchemy\WorkerPlugin\Worker\WorkerInvoker;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Ramsey\Uuid\Uuid;

class MessageHandler
{
    const MAX_OF_TRY = 3;

    private $messagePublisher;

    public function __construct(MessagePublisher $messagePublisher)
    {
        $this->messagePublisher = $messagePublisher;
    }

    public function consume(AMQPConnection $serverConnection, WorkerInvoker $workerInvoker, $argQueueName, $maxProcesses)
    {
        $publisher = $this->messagePublisher;

        $channel = $serverConnection->getChannel();

        // define consume callbacks
        $callback = function (AMQPMessage $message) use ($channel, $workerInvoker, $publisher) {

            $data = json_decode($message->getBody(), true);

            $count = 0;

            if ($message->has('application_headers')) {
                /** @var AMQPTable $headers */
                $headers = $message->get('application_headers');

                $headerData = $headers->getNativeData();
                if (isset($headerData['x-death'])) {
                    $xDeathHeader = $headerData['x-death'];

                    foreach ($xDeathHeader as $xdeath) {
                        $queue = $xdeath['queue'];
                        if (!in_array($queue, AMQPConnection::$defaultQueues)) {
                            continue;
                        }

                        $count = $xdeath['count'];
                        $data['payload']['count'] = $count;
                    }
                }
            }

            // if message is yet executed 3 times, save the unprocessed message in the corresponding failed queues
            if ($count > self::MAX_OF_TRY) {
                $this->messagePublisher->publishFailedMessage($data['payload'], $headers, AMQPConnection::$defaultFailedQueues[$data['message_type']]);

                $logMessage = sprintf("Rabbit message executed 3 times, it's to be saved in %s , payload >>> %s",
                    AMQPConnection::$defaultFailedQueues[$data['message_type']],
                    json_encode($data['payload'])
                );
                $this->messagePublisher->pushLog($logMessage);

                $channel->basic_ack($message->delivery_info['delivery_tag']);
            } else {

                try {
                    $workerInvoker->invokeWorker($data['message_type'], json_encode($data['payload']));

                    $channel->basic_ack($message->delivery_info['delivery_tag']);

                    $oldPayload = $data['payload'];
                    $message = $data['message_type'].' to be consumed! >> Payload ::'. json_encode($oldPayload);

                    $publisher->pushLog($message);
                } catch (\Exception $e) {
                    $channel->basic_nack($message->delivery_info['delivery_tag']);
                }
            }
        };

        $prefetchCount = ProcessPool::MAX_PROCESSES;

        if ($maxProcesses) {
            $prefetchCount = $maxProcesses;
        }

        foreach (AMQPConnection::$defaultQueues as $queueName) {
            if ($argQueueName ) {
                if (in_array($queueName, $argQueueName)) {
                    $serverConnection->setQueue($queueName);

                    //  give prefetch message to a worker consumer at a time
                    $channel->basic_qos(null, $prefetchCount, null);
                    $channel->basic_consume($queueName, Uuid::uuid4(), false, false, false, false, $callback);
                }
            } else {
                $serverConnection->setQueue($queueName);

                //  give prefetch message to a worker consumer at a time
                $channel->basic_qos(null, $prefetchCount, null);
                $channel->basic_consume($queueName, Uuid::uuid4(), false, false, false, false, $callback);
            }
        }

    }
}
