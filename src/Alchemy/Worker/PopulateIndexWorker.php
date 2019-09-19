<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application;
use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\SearchEngine\Elastic\ElasticsearchOptions;
use Alchemy\Phrasea\SearchEngine\Elastic\Indexer;
use Alchemy\WorkerPlugin\Model\DBManipulator;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;

class PopulateIndexWorker implements WorkerInterface
{
    use ApplicationBoxAware;

    private $app;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    /** @var Indexer $indexer */
    private $indexer;

    public function __construct(MessagePublisher $messagePublisher, Indexer $indexer)
    {
        $this->indexer = $indexer;
        $this->messagePublisher = $messagePublisher;
    }

    public function process(array $payload)
    {
        DBManipulator::savePopulateStatus($payload);

        /** @var ElasticsearchOptions $options */
        $options = $this->indexer->getIndex()->getOptions();

        $options->setIndexName($payload['indexName']);
        $options->setHost($payload['host']);
        $options->setPort($payload['port']);

        $databoxId = $payload['databoxId'];

        $indexExists = $this->indexer->indexExists();

        if (!$indexExists) {
            $this->messagePublisher->pushLog(sprintf("Index %s don't exist!", $payload['indexName']));
        } else {
            $databox = $this->findDataboxById($databoxId);

            try {
                $r = $this->indexer->populateIndex(Indexer::THESAURUS | Indexer::RECORDS, $databox); // , $temporary);

                $this->messagePublisher->pushLog(sprintf(
                    "Indexation of databox \"%s\" finished in %0.2f sec (Mem. %0.2f Mo)",
                    $databox->get_dbname(),
                    $r['duration']/1000,
                    $r['memory']/1048576
                ));
            } catch(\Exception $e) {
                DBManipulator::deletePopulateStatus($payload);

                $this->messagePublisher->pushLog(sprintf("Error on indexing : %s ", $e->getMessage()));
            }

        }

        // delete entry in populate_running
        DBManipulator::deletePopulateStatus($payload);
    }

}
