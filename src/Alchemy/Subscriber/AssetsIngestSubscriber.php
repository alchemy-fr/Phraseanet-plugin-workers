<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\WorkerPlugin\Event\AssetsCreateEvent;
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
            'message_type' => MessagePublisher::ASSETS_INGEST_TYPE,
            'payload' => $event->getData()
        ];

        $this->messagePublisher->publishMessage($payload, MessagePublisher::ASSETS_INGEST_QUEUE);
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerPluginEvents::ASSETS_CREATE => 'onAssetsCreate',
        ];
    }
}
