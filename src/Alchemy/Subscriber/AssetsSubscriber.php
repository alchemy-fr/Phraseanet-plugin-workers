<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\WorkerPlugin\Event\AssetsCreateEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetsSubscriber implements EventSubscriberInterface
{
    private $app;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->messagePublisher = $this->app['alchemy_service.message.publisher'];
    }

    public function onCreateAssets(AssetsCreateEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::ASSETS_INJEST_TYPE,
            'payload' => $event->getData()
        ];

        $this->messagePublisher->publishMessage($payload, MessagePublisher::ASSETS_INJEST_QUEUE);
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerPluginEvents::ASSETS_CREATE => 'onCreateAssets',
        ];
    }
}
