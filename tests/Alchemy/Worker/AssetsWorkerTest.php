<?php

namespace Alchemy\WorkerPlugin\Tests\Worker;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class AssetsWorkerTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $body = [
            'id'           => "d7c40d3f-06b6-40ba-88e6-397159c14ed7",
            'size'         => "151791",
            'formData'     => [
                'title' => "Document test"
            ],
            'originalName' => "test.png",
            'mimeType'     => "image/png",
            'createdAt'    => "2019-05-15T14:13:54+00:00"
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($body)),
            new Response(200, [], json_encode($body))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $request = $client->request('GET', '/assets/d7c40d3f-06b6-40ba-88e6-397159c14ed7');

        $this->assertEquals(200, $request->getStatusCode());

        $body = json_decode($request->getBody());

        $this->assertEquals('d7c40d3f-06b6-40ba-88e6-397159c14ed7', $body->id);
        $this->assertEquals('151791', $body->size);
        $this->assertEquals('image/png', $body->mimeType);
        $this->assertEquals('test.png', $body->originalName);
    }
}
