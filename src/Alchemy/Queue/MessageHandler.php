<?php

namespace Alchemy\WorkerPlugin\Queue;

use Alchemy\WorkerPlugin\Worker\ProcessPool;
use Alchemy\WorkerPlugin\Worker\WorkerInvoker;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;
use PhpAmqpLib\Channel\AMQPChannel;

class MessageHandler
{
    private $messagePublisher;

    public function __construct(MessagePublisher $messagePublisher)
    {
        $this->messagePublisher = $messagePublisher;
    }

    public function consume(AMQPChannel $channel, WorkerInvoker $workerInvoker, $argQueueName, $maxProcesses)
    {
        $publisher = $this->messagePublisher;

        // define consume callbacks
        $callback = function (AMQPMessage $message) use ($channel, $workerInvoker, $publisher) {

            $data = json_decode($message->getBody(), true);

//  TODO: if needed , retrieve message header and check count

//            /** @var AMQPTable $headers */
//            $headers = $message->get('application_headers');
//            $headerData = $headers->getNativeData();
//
//            $xDeatth = $headers['x-death'];
//
//            print_r($xDeatth);

            try {
                $workerInvoker->invokeWorker($data['message_type'], json_encode($data['payload']));

                $channel->basic_ack($message->delivery_info['delivery_tag']);

                if ($data['message_type'] !==  MessagePublisher::WRITE_LOGS_TYPE) {
                    $oldPayload = $data['payload'];
                    $message = $data['message_type'].' to be consumed! >> Payload ::'. json_encode($oldPayload);

                    $publisher->pushLog($message);
                }
            } catch (\Exception $e) {
                $channel->basic_nack($message->delivery_info['delivery_tag']);
            }

        };

        $prefetchCount = ProcessPool::MAX_PROCESSES;

        if ($maxProcesses) {
            $prefetchCount = $maxProcesses;
        }

        foreach (AMQPConnection::$dafaultQueues as $queueName) {
            if ($argQueueName ) {
                if (in_array($queueName, $argQueueName)) {
                    //  give one message to a worker consumer at a time
                    $channel->basic_qos(null, $prefetchCount, null);
                    $channel->basic_consume($queueName, Uuid::uuid4(), false, false, false, false, $callback);
                }
            } else {
                    //  give one message to a worker consumer at a time
                    $channel->basic_qos(null, $prefetchCount, null);
                    $channel->basic_consume($queueName, Uuid::uuid4(), false, false, false, false, $callback);
            }
        }

    }
}
