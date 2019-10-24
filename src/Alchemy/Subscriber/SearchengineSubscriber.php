<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\WorkerPlugin\Event\PopulateIndexEvent;
use Alchemy\WorkerPlugin\Event\PopulateIndexFailureEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchengineSubscriber implements EventSubscriberInterface
{
    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(MessagePublisher $messagePublisher)
    {
        $this->messagePublisher = $messagePublisher;
    }

    public function onPopulateIndex(PopulateIndexEvent $event)
    {
        $populateInfo = $event->getData();

        // make payload per databoxId
        foreach ($populateInfo['databoxIds'] as $databoxId) {
            $payload = [
                'message_type' => MessagePublisher::POPULATE_INDEX_TYPE,
                'payload' => [
                    'host'      => $populateInfo['host'],
                    'port'      => $populateInfo['port'],
                    'indexName' => $populateInfo['indexName'],
                    'databoxId' => $databoxId
                ]
            ];

            $this->messagePublisher->publishMessage($payload, MessagePublisher::POPULATE_INDEX_QUEUE);
        }
    }

    public function onPopulateIndexFailure(PopulateIndexFailureEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::POPULATE_INDEX_TYPE,
            'payload' => [
                'host'      => $event->getHost(),
                'port'      => $event->getPort(),
                'indexName' => $event->getIndexName(),
                'databoxId' => $event->getDataboxId(),
            ]
        ];

        $this->messagePublisher->publishMessage(
            $payload,
            MessagePublisher::RETRY_POPULATE_INDEX_QUEUE,
            $event->getCount(),
            $event->getWorkerMessage()
        );
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerPluginEvents::POPULATE_INDEX          => 'onPopulateIndex',
            WorkerPluginEvents::POPULATE_INDEX_FAILURE  => 'onPopulateIndexFailure'
        ];
    }
}

