<?php

namespace Alchemy\WorkerPlugin\Worker;

use Alchemy\WorkerPlugin\Configuration\Config;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class PullAssetsWorker implements WorkerInterface
{
    private $messagePublisher;

    public function __construct(MessagePublisher $messagePublisher)
    {
        $this->messagePublisher = $messagePublisher;
    }

    public function process(array $payload)
    {
        $config = Config::getConfiguration();

        if (isset($config['worker_plugin']) && isset($config['worker_plugin']['pull_assets'])) {
            $config = $config['worker_plugin']['pull_assets'];
        }

        //  Begin mock API
        $mockHandler = new MockHandler([
            new Response(200, [
                'Content-Type' => 'application/json'
            ],
            '{"assets":["3525941f-f6a9-453c-98dd-64a500fd6b1d","05d369d9-f30e-4379-a7e6-63b3f73ce542"],"publisher":"86ab157f-d643-4e6c-a41a-c41ff577f041","commit_id":"005e37cc-55e9-4492-beea-a367a12ac279","token":"08c9911b20b879d8a55661d63779771c094920270d","base_url":"http:\/\/192.168.80.1:8080"}')
        ]);

        $uploaderClient = new Client(['handler' => $mockHandler]);

        // End mock API

        try{
            $body = $uploaderClient->get($config['endpoint'])->getBody()->getContents();
            $body = json_decode($body,true);
        } catch(\Exception $e) {
            $body = '';

            $this->messagePublisher->pushLog("An error occurred when fetching endpoint : " . $e->getMessage());
        }

//        $uploaderClient = new Client(['base_uri' => $config['endpoint']]);
//
//        $body = $uploaderClient->get('')->getBody()->getContents();

        // As an array the mock body is like this
//
//        $body['assets']     = ["3525941f-f6a9-453c-98dd-64a500fd6b1d","05d369d9-f30e-4379-a7e6-63b3f73ce542"];
//        $body['publisher']  = '86ab157f-d643-4e6c-a41a-c41ff577f041';
//        $body['commit_id']  = '005e37cc-55e9-4492-beea-a367a12ac279';
//        $body['token']      = '08c9911b20b879d8a55661d63779771c094920270d';
//        $body['base_url']   = 'http://192.168.80.1:8080';

        if (count($body) > 1) {
            $this->messagePublisher->pushLog("A new commit found in the uploader !");

            $payload = [
                'message_type'  => MessagePublisher::ASSETS_INGEST_TYPE,
                'payload'       => [
                    'assets'    => $body['assets'],
                    'publisher' => $body['publisher'],
                    'commit_id' => $body['commit_id'],
                    'token'     => $body['token'],
                    'base_url'  => $body['base_url']
                ]
            ];

            $this->messagePublisher->publishMessage($payload, MessagePublisher::ASSETS_INGEST_QUEUE);
        } else {
            $this->messagePublisher->pushLog("No new commit found in the uploader !");
        }
    }
}
