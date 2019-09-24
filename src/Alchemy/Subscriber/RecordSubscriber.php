<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Core\Event\Record\MetadataChangedEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvents;
use Alchemy\Phrasea\Core\Event\Record\SubdefinitionCreateEvent;
use Alchemy\WorkerPlugin\Event\StoryCreateCoverEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Alchemy\WorkerPlugin\Worker\CreateRecordWorker;
use Alchemy\WorkerPlugin\Worker\Factory\WorkerFactoryInterface;
use Alchemy\WorkerPlugin\Worker\Resolver\TypeBasedWorkerResolver;
use Alchemy\WorkerPlugin\Worker\Resolver\WorkerResolverInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RecordSubscriber implements EventSubscriberInterface
{
    use ApplicationBoxAware;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    /** @var TypeBasedWorkerResolver  $workerResolver*/
    private $workerResolver;

    public function __construct(
        MessagePublisher $messagePublisher,
        WorkerResolverInterface $workerResolver
    )
    {
        $this->messagePublisher    = $messagePublisher;
        $this->workerResolver      = $workerResolver;
    }

    public function onSubdefinitionCreate(SubdefinitionCreateEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::SUBDEF_CREATION_TYPE,
            'payload' => [
                'recordId'  => $event->getRecord()->getRecordId(),
                'databoxId' => $event->getRecord()->getDataboxId(),
                'status'    => $event->isNewRecord() ? MessagePublisher::NEW_RECORD_MESSAGE : ''
            ]
        ];

        $this->messagePublisher->publishMessage($payload, MessagePublisher::SUBDEF_QUEUE);
    }

    public function onRecordCreated(RecordEvent $event)
    {
        $this->messagePublisher->pushLog(sprintf('The %s= %d was successfully created',
            ($event->getRecord()->isStory() ? "story story_id" : "record record_id"),
            $event->getRecord()->getRecordId()
        ));
    }

    public function onMetadataChanged(MetadataChangedEvent $event)
    {
        $record = $this->findDataboxById($event->getRecord()->getDataboxId())->get_record($event->getRecord()->getRecordId());

        if (count($record->get_subdefs()) > 1) {
            $payload = [
                'message_type' => MessagePublisher::WRITE_METADATAS_TYPE,
                'payload' => [
                    'recordId'  => $event->getRecord()->getRecordId(),
                    'databoxId' => $event->getRecord()->getDataboxId()
                ]
            ];

            $this->messagePublisher->publishMessage($payload, MessagePublisher::METADATAS_QUEUE);
        }
    }

    public function onStoryCreateCover(StoryCreateCoverEvent $event)
    {
        /** @var  WorkerFactoryInterface[] $factories */
        $factories = $this->workerResolver->getFactories();

        /** @var CreateRecordWorker $createRecordWorker */
        $createRecordWorker = $factories[MessagePublisher::CREATE_RECORD_TYPE]->createWorker();

        $createRecordWorker->setStoryCover($event->getData());
    }

    public static function getSubscribedEvents()
    {
        return [
            RecordEvents::CREATED                  => 'onRecordCreated',
            RecordEvents::SUBDEFINITION_CREATE     => 'onSubdefinitionCreate',
            RecordEvents::METADATA_CHANGED         => 'onMetadataChanged',
            WorkerPluginEvents::STORY_CREATE_COVER => 'onStoryCreateCover',
        ];
    }
}
