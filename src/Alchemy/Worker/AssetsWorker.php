<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\EntityManagerAware;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Model\Entities\StoryWZ;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;


class AssetsWorker implements WorkerInterface
{
    use EntityManagerAware;

    private $app;
    private $logger;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(PhraseaApplication $app)
    {
        $this->app              = $app;
        $this->logger           = $this->app['alchemy_service.logger'];
        $this->messagePublisher = $this->app['alchemy_service.message.publisher'];
    }

    public function process(array $payload)
    {
        //TODO: get is_story from message

        // never execute here
        $isStory = false;
        $storyId = null;

        if($isStory){

            // fixture of base_id an storyname
            $base_id = 1;
            $storyName = 'test story queue';

            $collection = \collection::getByBaseId($this->app, $base_id);

            $story = \record_adapter::createStory($this->app, $collection);
            $storyId = $story->getRecordId();

            $metadatas = [];

            foreach ($collection->get_databox()->get_meta_structure() as $meta) {
                if ($meta->get_thumbtitle()) {
                    $value = $storyName;
                } else {
                    continue;
                }

                $metadatas[] = [
                    'meta_struct_id' => $meta->get_id(),
                    'meta_id'        => null,
                    'value'          => $value,
                ];

                break;
            }

            $story->set_metadatas($metadatas)->rebuild_subdefs();

            $storyWZ = new StoryWZ();
            //TODO : get a authenticated user from the message
//            $storyWZ->setUser($this->getAuthenticatedUser());
//            $storyWZ->setUser($this->app['repo.users']->find(1));
            $storyWZ->setRecord($story);

            $entityManager = $this->getEntityManager();
            $entityManager->persist($storyWZ);
            $entityManager->flush();
        }

        $assets = $payload['assets'];

        foreach($assets as $assetId) {
            $createRecordMessage['message_type'] = MessagePublisher::CREATE_RECORD_TYPE;
            $createRecordMessage['payload'] = [
                'asset'      => $assetId,
                'publisher'  => $payload['publisher'],
                'assetToken' => $payload['token'],
                'storyId'    => $storyId
            ];

            $this->messagePublisher->publishMessage($createRecordMessage, MessagePublisher::CREATE_RECORD_QUEUE);
        }
    }
}
