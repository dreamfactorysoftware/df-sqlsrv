<?php

namespace DreamFactory\Core\SqlSrv;

use DreamFactory\Core\Components\DbSchemaExtensions;
use DreamFactory\Core\Enums\LicenseLevel;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use DreamFactory\Core\SqlSrv\Database\Connectors\SqlServerConnector;
use DreamFactory\Core\SqlSrv\Database\Schema\SqlServerSchema;
use DreamFactory\Core\SqlSrv\Database\SqlServerConnection;
use DreamFactory\Core\SqlSrv\Models\SqlSrvDbConfig;
use DreamFactory\Core\SqlSrv\Services\SqlSrv;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        // Add our database drivers override.
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('sqlsrv', function ($config, $name) {
                $config = Arr::add($config, 'name', $name);
                $connector = new SqlServerConnector();
                $connection = $connector->connect($config);

                return new SqlServerConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });

        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'                  => 'sqlsrv',
                    'label'                 => 'SQL Server',
                    'description'           => 'Database service supporting SQL Server connections.',
                    'group'                 => ServiceTypeGroups::DATABASE,
                    'subscription_required' => LicenseLevel::SILVER,
                    'config_handler'        => SqlSrvDbConfig::class,
                    'factory'               => function ($config) {
                        return new SqlSrv($config);
                    },
                ])
            );
        });

        // Add our database extensions.
        $this->app->resolving('df.db.schema', function (DbSchemaExtensions $db) {
            $db->extend('sqlsrv', function ($connection) {
                return new SqlServerSchema($connection);
            });
        });
    }
}
