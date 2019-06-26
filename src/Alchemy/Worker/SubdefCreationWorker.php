<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\ApplicationBoxAware;
use Alchemy\Phrasea\Media\SubdefGenerator;
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

            if(!$record->isStory()){
                $subdefGenerator->setLogger($this->app['alchemy_service.logger']);

                $subdefGenerator->generateSubdefs($record);

                $subdefGenerator->setLogger($oldLogger);
            }

        }
    }
}
