<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\EntityManagerAware;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Model\Entities\StoryWZ;
use Alchemy\Phrasea\Model\Repositories\UserRepository;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use GuzzleHttp\Client;

class AssetsWorker implements WorkerInterface
{
    use EntityManagerAware;

    private $app;

    /** @var MessagePublisher $messagePublisher */
    private $messagePublisher;

    public function __construct(PhraseaApplication $app)
    {
        $this->app              = $app;
        $this->messagePublisher = $this->app['alchemy_service.message.publisher'];
    }

    public function process(array $payload)
    {
        $start = microtime(true);
        $assets = $payload['assets'];

        $uploaderConfig = $this->app['worker_plugin.config']['worker_plugin'];

        $uploaderClient = new Client(['base_uri' => $uploaderConfig['url_uploader_service']]);


        //get first asset informations to check if it's a story
        $body = $uploaderClient->get('/assets/'.$assets[0], [
            'headers' => [
                'Authorization' => 'AssetToken '.$payload['token']
            ]
        ])->getBody()->getContents();

        $body = json_decode($body,true);

        $storyId = null;

        if (!empty($body['formData']['is_story'])) {
            $storyId = $this->createStory($body);
            $stop = microtime(true);

            if ($start) {
                $duration = $stop - $start;

                $messageLog = sprintf("A story story_id = %d created, duration = %s",
                    $storyId,
                    date('H:i:s', mktime(0,0, $duration))
                );
                $this->messagePublisher->pushLog($messageLog);
            }
        }

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

    private function createStory(array $body)
    {
        $storyId = null;

        $userRepository = $this->getUserRepository();
        $user = null;

        if (!empty($body['formData']['phraseanet_submiter_email'])) {
            $user = $userRepository->findByEmail($body['formData']['phraseanet_submiter_email']);
        }

        if ($user === null && !empty($body['formData']['phraseanet_user_submiter_id'])) {
            $user = $userRepository->find($body['formData']['phraseanet_user_submiter_id']);
        }

        if ($user !== null) {
            $base_id = $body['formData']['collection_destination'];

            $collection = \collection::getByBaseId($this->app, $base_id);

            $story = \record_adapter::createStory($this->app, $collection);
            $storyId = $story->getRecordId();

            $storyWZ = new StoryWZ();

            $storyWZ->setUser($user);
            $storyWZ->setRecord($story);

            $entityManager = $this->getEntityManager();
            $entityManager->persist($storyWZ);
            $entityManager->flush();
        }

        return $storyId;
    }

    /**
     * @return UserRepository
     */
    private function getUserRepository()
    {
        return $this->app['repo.users'];
    }
}
