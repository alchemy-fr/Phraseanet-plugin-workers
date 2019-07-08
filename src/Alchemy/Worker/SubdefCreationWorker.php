<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Core\Event\Record\MetadataChangedEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvents;
use Alchemy\Phrasea\Media\SubdefGenerator;
use Alchemy\WorkerPlugin\Event\StoryCreateCoverEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SubdefCreationWorker implements WorkerInterface
{
    use ApplicationBoxAware;

    private $subdefGenerator;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    private $logger;
    private $dispatcher;

    public function __construct(
        SubdefGenerator $subdefGenerator,
        MessagePublisher $messagePublisher,
        LoggerInterface $logger,
        EventDispatcherInterface $dispatcher
    )
    {
        $this->subdefGenerator  = $subdefGenerator;
        $this->messagePublisher = $messagePublisher;
        $this->logger           = $logger;
        $this->dispatcher       = $dispatcher;
    }

    public function process(array $payload)
    {
        if(isset($payload['recordId']) && isset($payload['databoxId'])) {
            $recordId  = $payload['recordId'];
            $databoxId = $payload['databoxId'];

            $record = $this->findDataboxById($databoxId)->get_record($recordId);

            $oldLogger = $this->subdefGenerator->getLogger();

            if (!$record->isStory()) {
                $this->subdefGenerator->setLogger($this->logger);

                $this->subdefGenerator->generateSubdefs($record);

                $this->subdefGenerator->setLogger($oldLogger);

                $this->dispatcher->dispatch(RecordEvents::METADATA_CHANGED, new MetadataChangedEvent($record));

                $parents = $record->get_grouping_parents();

                if (!$parents->is_empty() && isset($payload['status']) && $payload['status'] == MessagePublisher::NEW_RECORD_MESSAGE) {
                    foreach ($parents->get_elements() as $story) {
                        if (self::checkIfFirstChild($story, $record)) {
                            $data = implode('_', [$databoxId, $story->getRecordId(), $recordId]);

                            $this->dispatcher->dispatch(WorkerPluginEvents::STORY_CREATE_COVER, new StoryCreateCoverEvent($data));
                        }
                    }
                }
            }
        }
    }

    public static function checkIfFirstChild(\record_adapter $story, \record_adapter $record)
    {
        $sql = "SELECT * FROM regroup WHERE rid_parent = :parent_record_id AND rid_child = :children_id and ord = :ord";

        $connection = $record->getDatabox()->get_connection();

        $stmt = $connection->prepare($sql);

        $stmt->execute([
            ':parent_record_id' => $story->getRecordId(),
            ':children_id'      => $record->getRecordId(),
            ':ord'              => 0,
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt->closeCursor();

        if ($row) {
            return true;
        }

        return false;
    }
}
