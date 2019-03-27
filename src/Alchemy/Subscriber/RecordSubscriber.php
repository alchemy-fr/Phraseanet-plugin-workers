<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\Phrasea\Core\Event\Record\MetadataChangedEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RecordSubscriber implements EventSubscriberInterface
{
    private $app;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->messagePublisher = $this->app['worker.event.publisher'];
    }

    public function onBuildSubdefs(RecordEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::SUBDEF_CREATION_TYPE,
            'payload' => [
                'recordId'  => $event->getRecord()->getRecordId(),
                'databoxId' => $event->getRecord()->getDataboxId()
            ]
        ];

        $this->messagePublisher->publishMessage($payload, 'subdef-queue');
    }

    public function onMetadataChange(MetadataChangedEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::WRITE_METADATAs_TYPE,
            'payload' => [
                'recordId'  => $event->getRecord()->getRecordId(),
                'databoxId' => $event->getRecord()->getDataboxId()
            ]
        ];

        $this->messagePublisher->publishMessage($payload, 'metadatas-queue');
    }

    public static function getSubscribedEvents()
    {
        return [
            RecordEvents::CREATED                   => 'onBuildSubdefs',
            RecordEvents::SUB_DEFINITION_REBUILD    => 'onBuildSubdefs',
            RecordEvents::METADATA_CHANGED          => 'onMetadataChange',
        ];
    }
}
