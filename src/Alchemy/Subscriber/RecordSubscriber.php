<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\Phrasea\Core\Event\Record\MetadataChangedEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvents;
use Alchemy\Phrasea\Core\Event\Record\SubDefinitionRebuildEvent;
use Alchemy\Phrasea\Databox\Subdef\MediaSubdefRepository;
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
        $this->messagePublisher = $this->app['alchemy_service.message.publisher'];
    }

    public function onBuildSubdefs(SubDefinitionRebuildEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::SUBDEF_CREATION_TYPE,
            'payload' => [
                'recordId'  => $event->getRecord()->getRecordId(),
                'databoxId' => $event->getRecord()->getDataboxId()
            ]
        ];

        $this->messagePublisher->publishMessage($payload, MessagePublisher::SUBDEF_QUEUE);

        // avoid to execute the buildsubdef listener in the phraseanet core
        $event->stopPropagation();
    }

    public function onRecordCreated(RecordEvent $event)
    {
        $this->messagePublisher->pushLog(sprintf('The %s= %d was successfully created',
            ($event->getRecord()->isStory() ? "story story_id" : "record record_id"),
            $event->getRecord()->getRecordId()
        ));

        if (!$event->getRecord()->isStory()) {
            $payload = [
                'message_type' => MessagePublisher::SUBDEF_CREATION_TYPE,
                'payload' => [
                    'recordId'  => $event->getRecord()->getRecordId(),
                    'databoxId' => $event->getRecord()->getDataboxId()
                ]
            ];

            $this->messagePublisher->publishMessage($payload, MessagePublisher::SUBDEF_QUEUE);
        }

    }

    public function onMetadataChange(MetadataChangedEvent $event)
    {
        $subdefs = $this->getMediaSubdefRepository($event->getRecord()->getDataboxId())->findByRecordIdsAndNames([$event->getRecord()->getRecordId()]);

        if (count($subdefs) > 1 || $event->getRecord()->isStory()) {
            $payload = [
                'message_type' => MessagePublisher::WRITE_METADATAs_TYPE,
                'payload' => [
                    'recordId'  => $event->getRecord()->getRecordId(),
                    'databoxId' => $event->getRecord()->getDataboxId()
                ]
            ];

            $this->messagePublisher->publishMessage($payload, MessagePublisher::METADATAS_QUEUE);
        }
    }

    public static function getSubscribedEvents()
    {
        //  the method onBuildSubdefs listener in higher priority , so it called first and after stop event propagation$
        //  to avoid to execute phraseanet core listener

        return [
            RecordEvents::CREATED                   => 'onRecordCreated',
            RecordEvents::SUB_DEFINITION_REBUILD    => ['onBuildSubdefs', 10],
            RecordEvents::METADATA_CHANGED          => 'onMetadataChange',
        ];
    }

    /**
     * @return MediaSubdefRepository
     */
    private function getMediaSubdefRepository($databoxId)
{
    return $this->app['provider.repo.media_subdef']->getRepositoryForDatabox($databoxId);
}
}
