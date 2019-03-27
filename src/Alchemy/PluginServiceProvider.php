<?php

/*
 * This file is part of Phraseanet graylog plugin
 *
 * (c) 2005-2013 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\WorkerPlugin;

use Alchemy\Phrasea\Plugin\PluginProviderInterface;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Worker\CallableWorkerFactory;
use Alchemy\WorkerPlugin\Queue\AMQPConnection;
use Alchemy\WorkerPlugin\Queue\MessageHandler;
use Alchemy\WorkerPlugin\Worker\SubdefCreationWorker;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Alchemy\WorkerPlugin\Worker\WriteMetadatasWorker;
use Alchemy\WorkerPlugin\Subscriber\RecordSubscriber;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PluginServiceProvider implements PluginProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $app['worker.amqp.connection'] = $app->share(function (Application $app) {
            return new AMQPConnection($app['alchemy_worker.queue_registry']);
        });

        $app['worker.message.handler'] = $app->share(function (Application $app) {
            return new MessageHandler($app);
        });

        $app['worker.event.publisher'] = $app->share(function (Application $app) {
            return new MessagePublisher($app['alchemy_worker.queue_registry'], $app);
        });

        $app['dispatcher'] = $app->share(
            $app->extend('dispatcher', function (EventDispatcherInterface $dispatcher, Application $app) {
                $dispatcher->addSubscriber(new RecordSubscriber($app));

                return $dispatcher;
            })
        );

        $app['alchemy_worker.worker_resolver']->setFactory(MessagePublisher::SUBDEF_CREATION_TYPE, new CallableWorkerFactory(function () use ($app) {
            return (new SubdefCreationWorker($app))
                ->setApplicationBox($app['phraseanet.appbox']);
        }));

        $app['alchemy_worker.worker_resolver']->setFactory(MessagePublisher::WRITE_METADATAs_TYPE, new CallableWorkerFactory(function () use ($app) {
            return (new WriteMetadatasWorker($app))
                ->setApplicationBox($app['phraseanet.appbox']);
        }));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {

    }

    /**
     * {@inheritdoc}
     */
    public static function create(PhraseaApplication $app)
    {
        return new static();
    }

}