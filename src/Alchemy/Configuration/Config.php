<?php

namespace Alchemy\WorkerPlugin\Configuration;

use Symfony\Component\Yaml\Yaml;

class Config
{
    const WORKER_SERVICE_DATABASE_FILE = 'worker.db';

    public static function getConfigFilename()
    {
        $manifest_name = json_decode(file_get_contents(realpath(dirname(__FILE__)) . "/../../../manifest.json"))->name;
        $config_dir = realpath(dirname(__FILE__) . "/../../../../../config") . "/plugins/" . $manifest_name;

        if (!is_dir($config_dir)) {
            mkdir($config_dir, 0777, true);
        }

        $config_file = $config_dir . '/configuration.yml';

        return $config_file;
    }

    public static function getConfiguration()
    {
        $config = null;
        // locate the config for this plugin
        $config_file = self::getConfigFilename();

        if(file_exists($config_file)){
            try{
                $config = Yaml::parse(file_get_contents($config_file));
            }catch(\Exception $e){
                return null;
            }
        }

        return $config;
    }

    public static function setConfiguration(array $config)
    {
        $content = Yaml::dump(['worker_plugin' => $config]);

        file_put_contents(self::getConfigFilename(), $content);
    }

    public static function getPluginDatabaseFile()
    {
        $db_plugin_dir = realpath(dirname(__FILE__) . "/../../../") . "/db" ;

        if (!is_dir($db_plugin_dir)) {
            mkdir($db_plugin_dir, 0777, true);
        }

        $dbFile = $db_plugin_dir . '/' . self::WORKER_SERVICE_DATABASE_FILE;

        if (!is_file($dbFile)) {
            file_put_contents($dbFile, '');
        }

        return $dbFile;
    }

    public static function getWorkerSqliteConnection()
    {
        $db_conn = 'sqlite:'. self::getPluginDatabaseFile();
        $pdo = new \PDO($db_conn);

        return $pdo;
    }
}
