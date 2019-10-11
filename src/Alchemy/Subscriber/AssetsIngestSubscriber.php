<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\WorkerPlugin\Event\AssetsCreateEvent;
use Alchemy\WorkerPlugin\Event\AssetsCreationFailureEvent;
use Alchemy\WorkerPlugin\Event\AssetsCreationRecordFailureEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetsIngestSubscriber implements EventSubscriberInterface
{
    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(MessagePublisher $messagePublisher)
    {
        $this->messagePublisher = $messagePublisher;
    }

    public function onAssetsCreate(AssetsCreateEvent $event)
    {
        $payload = [
            'message_type'  => MessagePublisher::ASSETS_INGEST_TYPE,
            'payload'       => $event->getData()
        ];

        $this->messagePublisher->publishMessage($payload, MessagePublisher::ASSETS_INGEST_QUEUE);
    }

    public function onAssetsCreationFailure(AssetsCreationFailureEvent $event)
    {
        $payload = [
            'message_type'  => MessagePublisher::ASSETS_INGEST_TYPE,
            'payload'       => $event->getPayload()
        ];

        $retryCount = 1;
        $this->messagePublisher->publishMessage(
            $payload,
            MessagePublisher::RETRY_ASSETS_INGEST_QUEUE,
            $retryCount,
            $event->getWorkerMessage()
        );
    }

    public function onAssetsCreationRecordFailure(AssetsCreationRecordFailureEvent $event)
    {
        $payload = [
            'message_type'  => MessagePublisher::CREATE_RECORD_TYPE,
            'payload'       => $event->getPayload()
        ];

        $retryCount = 1;
        $this->messagePublisher->publishMessage(
            $payload,
            MessagePublisher::RETRY_CREATE_RECORD_QUEUE,
            $retryCount,
            $event->getWorkerMessage()
        );
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerPluginEvents::ASSETS_CREATE                  => 'onAssetsCreate',
            WorkerPluginEvents::ASSETS_CREATION_FAILURE        => 'onAssetsCreationFailure',
            WorkerPluginEvents::ASSETS_CREATION_RECORD_FAILURE => 'onAssetsCreationRecordFailure'
        ];
    }
}
