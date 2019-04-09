<?php

/*
 * This file is part of Phraseanet graylog plugin
 *
 * (c) 2005-2019 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\WorkerPlugin\Provider;

use Alchemy\Phrasea\Core\Configuration\PropertyAccess;
use Alchemy\Phrasea\Plugin\PluginProviderInterface;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\WorkerPlugin\Queue\AMQPConnection;
use Alchemy\WorkerPlugin\Queue\MessageHandler;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Alchemy\WorkerPlugin\Subscriber\ExportSubscriber;
use Alchemy\WorkerPlugin\Subscriber\RecordSubscriber;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class QueueServiceProvider implements PluginProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $app['alchemy_service.server'] = $app->share(function (Application $app) {
            $defaultConfiguration = [
                'host'      => 'localhost',
                'port'      => 5672,
                'user'      => 'guest',
                'password'  => 'guest',
                'vhost'     => '/'
            ];

            /** @var PropertyAccess $configuration */
            $configuration = $app['conf'];

            $serverConfigurations = $configuration->get(['rabbitmq', 'server'], $defaultConfiguration);

            return $serverConfigurations;
        });

        $app['alchemy_service.amqp.connection'] = $app->share(function (Application $app) {
            return new AMQPConnection($app['alchemy_service.server']);
        });

        $app['alchemy_service.message.handler'] = $app->share(function (Application $app) {
            return new MessageHandler($app);
        });

        $app['alchemy_service.message.publisher'] = $app->share(function (Application $app) {
            return new MessagePublisher($app);
        });

        $app['dispatcher'] = $app->share(
            $app->extend('dispatcher', function (EventDispatcherInterface $dispatcher, Application $app) {
                $dispatcher->addSubscriber(new RecordSubscriber($app));
                $dispatcher->addSubscriber(new ExportSubscriber($app));

                return $dispatcher;
            })
        );

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