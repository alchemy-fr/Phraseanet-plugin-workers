<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Core\PhraseaTokens;
use Alchemy\Phrasea\Filesystem\FilesystemService;
use Alchemy\Phrasea\Media\SubdefGenerator;
use Alchemy\WorkerPlugin\Event\StoryCreateCoverEvent;
use Alchemy\WorkerPlugin\Event\SubdefinitionCreationFailureEvent;
use Alchemy\WorkerPlugin\Event\SubdefinitionWritemetaEvent;
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
    private $filesystem;

    public function __construct(
        SubdefGenerator $subdefGenerator,
        MessagePublisher $messagePublisher,
        LoggerInterface $logger,
        EventDispatcherInterface $dispatcher,
        FilesystemService $filesystem
    )
    {
        $this->subdefGenerator  = $subdefGenerator;
        $this->messagePublisher = $messagePublisher;
        $this->logger           = $logger;
        $this->dispatcher       = $dispatcher;
        $this->filesystem       = $filesystem;
    }

    public function process(array $payload)
    {
        if(isset($payload['recordId']) && isset($payload['databoxId'])) {
            $recordId       = $payload['recordId'];
            $databoxId      = $payload['databoxId'];
            $wantedSubdef   = [$payload['subdefName']];

            $databox = $this->findDataboxById($databoxId);
            $record = $databox->get_record($recordId);

            $oldLogger = $this->subdefGenerator->getLogger();

            if (!$record->isStory()) {
                $abConnection = $this->getApplicationBox()->get_connection();
                // check if there is a write meta running for the record or the same task running
                $statement = $abConnection->prepare('SELECT subdef_name FROM worker WHERE ((work & :write_meta) > 0 OR ((work & :make_subdef) > 0 AND subdef_name = :subdef_name) ) AND record_id = :record_id AND databox_id = :databox_id');
                $statement->execute([
                    'write_meta' => PhraseaTokens::WRITE_META,
                    'make_subdef'=> PhraseaTokens::MAKE_SUBDEF,
                    'subdef_name'=> $payload['subdefName'],
                    'record_id'  => $recordId,
                    'databox_id' => $databoxId
                ]);

                $rs = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $statement->closeCursor();

                if (count($rs)) {
                    // the file is in used to write meta
                    $payload = [
                        'message_type'  => MessagePublisher::SUBDEF_CREATION_TYPE,
                        'payload'       => $payload
                    ];
                    $this->messagePublisher->publishMessage($payload, MessagePublisher::DELAYED_SUBDEF_QUEUE);

                    $message = MessagePublisher::SUBDEF_CREATION_TYPE.' to be re-published! >> Payload ::'. json_encode($payload);
                    $this->messagePublisher->pushLog($message);

                    return ;
                }

                // tell that a file is in used to create subdef
                $abConnection->beginTransaction();

                try {
                    $sql = "INSERT INTO worker (databox_id, record_id, subdef_name, work) VALUES (:databox_id, :record_id, :subdef_name, :work)";
                    $statement = $abConnection->prepare($sql);
                    $statement->execute([
                        'databox_id'    => $databoxId,
                        'record_id'     => $recordId,
                        'subdef_name'   => $payload['subdefName'],
                        'work'          => PhraseaTokens::MAKE_SUBDEF,
                    ]);
                    $statement->closeCursor();
                    $abConnection->commit();
                } catch (\Exception $e) {
                    $abConnection->rollback();
                }

                $this->subdefGenerator->setLogger($this->logger);

                $this->subdefGenerator->generateSubdefs($record, $wantedSubdef);

                // begin to check if the subdef is successfully generated
                $subdef = $record->getDatabox()->get_subdef_structure()->getSubdefGroup($record->getType())->getSubdef($payload['subdefName']);
                $filePathToCheck = null;

                if ($record->has_subdef($payload['subdefName']) ) {
                    $filePathToCheck = $record->get_subdef($payload['subdefName'])->getRealPath();
                }

                $filePathToCheck = $this->filesystem->generateSubdefPathname($record, $subdef, $filePathToCheck);

                if (!$this->filesystem->exists($filePathToCheck)) {

                    $count = isset($payload['count']) ? $payload['count'] + 1 : 2 ;

                    $this->dispatcher->dispatch(WorkerPluginEvents::SUBDEFINITION_CREATION_FAILURE, new SubdefinitionCreationFailureEvent(
                        $record,
                        $payload['subdefName'],
                        'Subdef generation failed !',
                        $count
                    ));

                    $this->subdefGenerator->setLogger($oldLogger);
                    return ;
                }
                // checking ended

                // order to write meta for the subdef if needed
                $this->dispatcher->dispatch(WorkerPluginEvents::SUBDEFINITION_WRITE_META, new SubdefinitionWritemetaEvent($record, $payload['subdefName']));

                $this->subdefGenerator->setLogger($oldLogger);

                //  update jeton when subdef is created
                $this->updateJeton($record);

                $parents = $record->get_grouping_parents();

                //  create a cover for a story
                //  used when uploaded via uploader-service and grouped as a story
                if (!$parents->is_empty() && isset($payload['status']) && $payload['status'] == MessagePublisher::NEW_RECORD_MESSAGE  && in_array($payload['subdefName'], array('thumbnail', 'preview'))) {
                    foreach ($parents->get_elements() as $story) {
                        if (self::checkIfFirstChild($story, $record)) {
                            $data = implode('_', [$databoxId, $story->getRecordId(), $recordId, $payload['subdefName']]);

                            $this->dispatcher->dispatch(WorkerPluginEvents::STORY_CREATE_COVER, new StoryCreateCoverEvent($data));
                        }
                    }
                }

                $abConnection->beginTransaction();
                try {
                    // subdef creation is finished for this subdef_name, so delete from the worker table
                    $abConnection->executeUpdate('DELETE FROM worker WHERE record_id = :record_id AND databox_id = :databox_id AND subdef_name = :subdef_name', [
                        'databox_id'    => $databoxId,
                        'record_id'     => $recordId,
                        'subdef_name'   => $payload['subdefName'],
                    ]);
                    $abConnection->commit();
                } catch (\Exception $e) {
                    $abConnection->rollback();
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

    private function updateJeton(\record_adapter $record)
    {
        $connection = $record->getDatabox()->get_connection();
        $connection->beginTransaction();

        // mark subdef created
        $sql = 'UPDATE record'
            . ' SET jeton=(jeton & ~(:token)), moddate=NOW()'
            . ' WHERE record_id=:record_id';

        $stmt = $connection->prepare($sql);

        $stmt->execute([
            ':record_id'    => $record->getRecordId(),
            ':token'        => PhraseaTokens::MAKE_SUBDEF,
        ]);

        $connection->commit();
        $stmt->closeCursor();
    }
}
