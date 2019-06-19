<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\Phrasea\Application\Helper\EntityManagerAware;
use Alchemy\Phrasea\Application\Helper\BorderManagerAware;
use Alchemy\Phrasea\Application\Helper\DispatcherAware;
use Alchemy\Phrasea\Application\Helper\FilesystemAware;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Border\File;
use Alchemy\Phrasea\Border\Manager;
use Alchemy\Phrasea\Border\Visa;
use Alchemy\Phrasea\Core\Event\LazaretEvent;
use Alchemy\Phrasea\Core\Event\RecordEdit;
use Alchemy\Phrasea\Core\PhraseaEvents;
use Alchemy\Phrasea\Model\Entities\LazaretFile;
use Alchemy\Phrasea\Model\Entities\LazaretSession;
use GuzzleHttp\Client;

class AssetsWorker implements WorkerInterface
{
    use EntityManagerAware;
    use BorderManagerAware;
    use DispatcherAware;
    use FilesystemAware;

    private $app;
    private $logger;

    public function __construct(PhraseaApplication $app)
    {
        $this->app = $app;
        $this->logger = $this->app['alchemy_service.logger'];
    }

    public function process(array $payload)
    {
        //TODO: splite into different queue  to create a record and to create a story

        $uploaderConfig = $this->app['worker_plugin.config']['worker_plugin'];

        $uploaderClient = new Client(['base_uri' => $uploaderConfig['url_uploader_service']]);


        $assets = $payload['assets'];

        foreach($assets as $assetId) {

            //get asset informations
            $body = $uploaderClient->get('/assets/'.$assetId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$uploaderConfig['uploader_access_token']
                ]
            ])->getBody()->getContents();

            $body = json_decode($body);

            $tempfile = $this->getTemporaryFilesystem()->createTemporaryFile('download_', null, pathinfo($body->originalName, PATHINFO_EXTENSION));

            //download the asset
            $res = $uploaderClient->get('/assets/'.$assetId.'/download', [
                'headers' => [
                    'Authorization' => 'Bearer '.$uploaderConfig['uploader_access_token']
                ],
                'save_to' => $tempfile
            ]);

            if($res->getStatusCode() !== 200) {
                $this->logger->error(sprintf('Error %s downloading "%s"', $res->getStatusCode(), $uploaderConfig['url_uploader_service'].'/assets/'.$assetId.'/download'));
            }


            $lazaretSession = new LazaretSession();
            //TODO: get a authenticatedUser
//            $lazaretSession->setUser($this->getAuthenticatedUser());

            $this->getEntityManager()->persist($lazaretSession);


            $renamedFilename = $tempfile;
            $media = $this->app->getMediaFromUri($renamedFilename);

            $base_id = $body->formData->collection_destination;
            $collection = \collection::getByBaseId($this->app, $base_id);

            $packageFile = new File($this->app, $media, $collection, $body->originalName);

            //TODO : treat status and metadata formData

            $reasons = [];
            $elementCreated = null;

            $callback = function ($element, Visa $visa) use (&$reasons, &$elementCreated) {
                foreach ($visa->getResponses() as $response) {
                    if (!$response->isOk()) {
                        $reasons[] = $response->getMessage($this->app['translator']);
                    }
                }

                $elementCreated = $element;
            };

            $this->getBorderManager()->process( $lazaretSession, $packageFile, $callback, Manager::FORCE_RECORD);

            if ($elementCreated instanceof \record_adapter) {
                $this->logger->info(sprintf('The record record_id= %1$d was successfully created', $elementCreated->getId()));

                $this->dispatch(PhraseaEvents::RECORD_UPLOAD, new RecordEdit($elementCreated));

            } else {
                /** @var LazaretFile $elementCreated */
                $this->dispatch(PhraseaEvents::LAZARET_CREATE, new LazaretEvent($elementCreated));

                $this->logger->info('The file was moved to the quarantine');
            }

        }
    }
}
