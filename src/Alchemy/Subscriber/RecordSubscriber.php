<?php

namespace Alchemy\WorkerPlugin\Subscriber;

use Alchemy\Phrasea\Application;
use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Core\Event\Record\MetadataChangedEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvents;
use Alchemy\Phrasea\Core\Event\Record\SubdefinitionCreateEvent;
use Alchemy\Phrasea\Core\Event\Record\SubDefinitionCreationFailedEvent;
use Alchemy\Phrasea\Databox\Subdef\MediaSubdefRepository;
use Alchemy\WorkerPlugin\Event\StoryCreateCoverEvent;
use Alchemy\WorkerPlugin\Event\SubdefinitionWritemetaEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Alchemy\WorkerPlugin\Worker\CreateRecordWorker;
use Alchemy\WorkerPlugin\Worker\Factory\WorkerFactoryInterface;
use Alchemy\WorkerPlugin\Worker\Resolver\TypeBasedWorkerResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RecordSubscriber implements EventSubscriberInterface
{
    use ApplicationBoxAware;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    /** @var TypeBasedWorkerResolver  $workerResolver*/
    private $workerResolver;

    /** @var  Application */
    private $app;

    public function __construct(Application $app)
    {
        $this->messagePublisher    = $app['alchemy_service.message.publisher'];
        $this->workerResolver      = $app['alchemy_service.type_based_worker_resolver'];
        $this->app                 = $app;
    }

    public function onSubdefinitionCreate(SubdefinitionCreateEvent $event)
    {
        $record = $this->findDataboxById($event->getRecord()->getDataboxId())->get_record($event->getRecord()->getRecordId());

        $subdefs = $record->getDatabox()->get_subdef_structure()->getSubdefGroup($record->getType());

        foreach ($subdefs as $subdef) {
            $payload = [
                'message_type' => MessagePublisher::SUBDEF_CREATION_TYPE,
                'payload' => [
                    'recordId'      => $event->getRecord()->getRecordId(),
                    'databoxId'     => $event->getRecord()->getDataboxId(),
                    'subdefName'    => $subdef->get_name(),
                    'status'        => $event->isNewRecord() ? MessagePublisher::NEW_RECORD_MESSAGE : ''
                ]
            ];

            $this->messagePublisher->publishMessage($payload, MessagePublisher::SUBDEF_QUEUE);
        }

    }

    public function onSubdefinitionCreationFailed(SubDefinitionCreationFailedEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::SUBDEF_CREATION_TYPE,
            'payload' => [
                'recordId'      => $event->getRecord()->getRecordId(),
                'databoxId'     => $event->getRecord()->getDataboxId(),
                'subdefName'    => $event->getSubDefinitionName(),
                'status'        => ''
            ]
        ];

        $this->messagePublisher->publishMessage($payload, MessagePublisher::RETRY_SUBDEF_QUEUE);
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
        $mediaSubdefRepository = $this->getMediaSubdefRepository($event->getRecord()->getDataboxId());
        $mediaSubdefs = $mediaSubdefRepository->findByRecordIdsAndNames([$event->getRecord()->getRecordId()]);

        foreach ($mediaSubdefs as $subdef) {
            if ($subdef->is_physically_present()) {
                $payload = [
                    'message_type' => MessagePublisher::WRITE_METADATAS_TYPE,
                    'payload' => [
                        'recordId'      => $event->getRecord()->getRecordId(),
                        'databoxId'     => $event->getRecord()->getDataboxId(),
                        'subdefName'    => $subdef->get_name()
                    ]
                ];

                $this->messagePublisher->publishMessage($payload, MessagePublisher::METADATAS_QUEUE);
            }
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

    public function onSubdefinitionWritemeta(SubdefinitionWritemetaEvent $event)
    {
        $payload = [
            'message_type' => MessagePublisher::WRITE_METADATAS_TYPE,
            'payload' => [
                'recordId'      => $event->getRecord()->getRecordId(),
                'databoxId'     => $event->getRecord()->getDataboxId(),
                'subdefName'    => $event->getSubdefName()
            ]
        ];

        $this->messagePublisher->publishMessage($payload, MessagePublisher::METADATAS_QUEUE);
    }

    public static function getSubscribedEvents()
    {
        return [
            RecordEvents::CREATED                           => 'onRecordCreated',
            RecordEvents::SUBDEFINITION_CREATE              => 'onSubdefinitionCreate',
            RecordEvents::SUB_DEFINITION_CREATION_FAILED    => 'onSubdefinitionCreationFailed',
            RecordEvents::METADATA_CHANGED                  => 'onMetadataChanged',
            WorkerPluginEvents::STORY_CREATE_COVER          => 'onStoryCreateCover',
            WorkerPluginEvents::SUBDEFINITION_WRITE_META    => 'onSubdefinitionWritemeta'
        ];
    }

    /**
     * @param $databoxId
     *
     * @return MediaSubdefRepository
     */
    private function getMediaSubdefRepository($databoxId)
    {
        return $this->app['provider.repo.media_subdef']->getRepositoryForDatabox($databoxId);
    }
}
