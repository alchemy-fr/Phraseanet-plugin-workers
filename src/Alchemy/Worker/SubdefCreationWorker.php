<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Core\Event\Record\MetadataChangedEvent;
use Alchemy\Phrasea\Core\Event\Record\RecordEvents;
use Alchemy\Phrasea\Media\SubdefGenerator;
use Alchemy\WorkerPlugin\Event\StoryCreateCoverEvent;
use Alchemy\WorkerPlugin\Event\WorkerPluginEvents;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Silex\Application;

class SubdefCreationWorker implements WorkerInterface
{
    use ApplicationBoxAware;

    private $app;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->messagePublisher = $this->app['alchemy_service.message.publisher'];
    }

    public function process(array $payload)
    {
        if(isset($payload['recordId']) && isset($payload['databoxId'])) {
            $recordId  = $payload['recordId'];
            $databoxId = $payload['databoxId'];

            $record = $this->findDataboxById($databoxId)->get_record($recordId);

            /** @var SubdefGenerator $subdefGenerator */
            $subdefGenerator = $this->app['subdef.generator'];
            $oldLogger = $subdefGenerator->getLogger();

            if (!$record->isStory()) {
                $subdefGenerator->setLogger($this->app['alchemy_service.logger']);

                $subdefGenerator->generateSubdefs($record);

                $subdefGenerator->setLogger($oldLogger);

                $this->app['dispatcher']->dispatch(RecordEvents::METADATA_CHANGED, new MetadataChangedEvent($record));

                $parents = $record->get_grouping_parents();

                if (!$parents->is_empty() && isset($payload['status']) && $payload['status'] == MessagePublisher::NEW_RECORD_MESSAGE) {
                    foreach ($parents->get_elements() as $story) {
                        if (self::checkIfFirstChild($story, $record)) {
                            $data = implode('_', [$databoxId, $story->getRecordId(), $recordId]);

                            $this->app['dispatcher']->dispatch(WorkerPluginEvents::STORY_CREATE_COVER, new StoryCreateCoverEvent($data));
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
