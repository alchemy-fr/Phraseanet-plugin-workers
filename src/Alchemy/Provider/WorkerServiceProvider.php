<?php

namespace Alchemy\WorkerPlugin\Provider;

use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Core\LazyLocator;
use Alchemy\Phrasea\Plugin\PluginProviderInterface;
use Alchemy\WorkerPlugin\Queue\MessagePublisher;
use Alchemy\WorkerPlugin\Worker\AssetsWorker;
use Alchemy\WorkerPlugin\Worker\ExportMailWorker;
use Alchemy\WorkerPlugin\Worker\Factory\CallableWorkerFactory;
use Alchemy\WorkerPlugin\Worker\ProcessPool;
use Alchemy\WorkerPlugin\Worker\Resolver\TypeBasedWorkerResolver;
use Alchemy\WorkerPlugin\Worker\SubdefCreationWorker;
use Alchemy\WorkerPlugin\Worker\WorkerInvoker;
use Alchemy\WorkerPlugin\Worker\WriteLogsWorker;
use Alchemy\WorkerPlugin\Worker\WriteMetadatasWorker;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Silex\Application;

class WorkerServiceProvider implements PluginProviderInterface
{
    public function register(Application $app)
    {
        $app['alchemy_service.type_based_worker_resolver'] = $app->share(function () {
            return new TypeBasedWorkerResolver();
        });

        $app['alchemy_service.logger'] = $app->share(function (Application $app) {
            $logger = new $app['monolog.logger.class']('alchemy-service logger');
            $logger->pushHandler(new RotatingFileHandler(
                $app['log.path'] . DIRECTORY_SEPARATOR . 'worker_service.log',
                10,
                Logger::INFO
            ));

            return $logger;
        });

        // use the console logger
        $loggerSetter = function (LoggerAwareInterface $loggerAware) use ($app) {
            if (isset($app['logger'])) {
                $loggerAware->setLogger($app['logger']);
            }

            return $loggerAware;
        };

        $app['alchemy_service.process_pool'] = $app->share(function (Application $app) use ($loggerSetter) {
            return $loggerSetter(new ProcessPool());
        });

        $app['alchemy_service.worker_invoker'] = $app->share(function (Application $app) use ($loggerSetter) {
            return $loggerSetter(new WorkerInvoker($app['alchemy_service.process_pool']));
        });

        $app['alchemy_service.type_based_worker_resolver']->setFactory(MessagePublisher::SUBDEF_CREATION_TYPE, new CallableWorkerFactory(function () use ($app) {
            return (new SubdefCreationWorker($app))
                ->setApplicationBox($app['phraseanet.appbox']);
        }));

        $app['alchemy_service.type_based_worker_resolver']->setFactory(MessagePublisher::WRITE_METADATAs_TYPE, new CallableWorkerFactory(function () use ($app) {
            return (new WriteMetadatasWorker($app))
                ->setApplicationBox($app['phraseanet.appbox']);
        }));

        $app['alchemy_service.type_based_worker_resolver']->setFactory(MessagePublisher::EXPORT_MAIL_TYPE, new CallableWorkerFactory(function () use ($app) {
            return (new ExportMailWorker($app))
                ->setDelivererLocator(new LazyLocator($app, 'notification.deliverer'));
        }));

        $app['alchemy_service.type_based_worker_resolver']->setFactory(MessagePublisher::LOGS_TYPE, new CallableWorkerFactory(function () use ($app) {
            return new WriteLogsWorker($app);
        }));

        $app['alchemy_service.type_based_worker_resolver']->setFactory(MessagePublisher::ASSETS_INJEST_TYPE, new CallableWorkerFactory(function () use ($app) {
            return (new AssetsWorker($app))
                ->setBorderManagerLocator(new LazyLocator($app, 'border-manager'))
                ->setEntityManagerLocator(new LazyLocator($app, 'orm.em'))
                ->setFileSystemLocator(new LazyLocator($app, 'filesystem'))
                ->setTemporaryFileSystemLocator(new LazyLocator($app, 'temporary-filesystem'))
                ->setDispatcher($app['dispatcher']);
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
