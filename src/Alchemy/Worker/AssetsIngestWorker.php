<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\EntityManagerAware;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Model\Entities\StoryWZ;
use Alchemy\Phrasea\Model\Repositories\UserRepository;
use Alchemy\WorkerPlugin\Configuration\Config;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use GuzzleHttp\Client;

class AssetsIngestWorker implements WorkerInterface
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
        $assets = $payload['assets'];

        $this->saveAssetsList($payload['commit_id'], $assets);

        $uploaderClient = new Client(['base_uri' => $payload['base_url']]);

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
        }

        foreach ($assets as $assetId) {
            $createRecordMessage['message_type'] = MessagePublisher::CREATE_RECORD_TYPE;
            $createRecordMessage['payload'] = [
                'asset'      => $assetId,
                'publisher'  => $payload['publisher'],
                'assetToken' => $payload['token'],
                'storyId'    => $storyId,
                'base_url'   => $payload['base_url'],
                'commit_id'  => $payload['commit_id']
            ];

            $this->messagePublisher->publishMessage($createRecordMessage, MessagePublisher::CREATE_RECORD_QUEUE);
        }
    }

    private function saveAssetsList($commitId, $assetIds)
    {
        $pdo = Config::getWorkerSqliteConnection();

        $pdo->beginTransaction();

        try {
            $pdo->query("CREATE TABLE IF NOT EXISTS commits(commit_id TEXT NOT NULL, asset TEXT NOT NULL);");

            // insert all assets ID in the temporary sqlite database
            foreach ($assetIds as $assetId) {
                $stmt = $pdo->prepare("INSERT INTO commits(commit_id, asset) VALUES(:commit_id, :asset)");

                $stmt->execute([
                    ':commit_id' => $commitId,
                    ':asset'     => $assetId
                ]);
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
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
