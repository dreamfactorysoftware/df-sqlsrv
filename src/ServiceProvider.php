<?php
namespace DreamFactory\Core\SqlSrv;

use DreamFactory\Core\Components\ServiceDocBuilder;
use DreamFactory\Core\Components\DbSchemaExtensions;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use DreamFactory\Core\SqlSrv\Database\Connectors\SqlServerConnector;
use DreamFactory\Core\SqlSrv\Database\Schema\SqlServerSchema;
use DreamFactory\Core\SqlSrv\Database\SqlServerConnection;
use DreamFactory\Core\SqlSrv\Models\SqlSrvDbConfig;
use DreamFactory\Core\SqlSrv\Services\SqlSrv;
use Illuminate\Database\DatabaseManager;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use ServiceDocBuilder;

    public function register()
    {
        // Add our database drivers override.
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('sqlsrv', function ($config) {
                $connector = new SqlServerConnector();
                $connection = $connector->connect($config);

                return new SqlServerConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });

        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'            => 'sqlsrv',
                    'label'           => 'SQL Server',
                    'description'     => 'Database service supporting SQL Server connections.',
                    'group'           => ServiceTypeGroups::DATABASE,
                    'config_handler'  => SqlSrvDbConfig::class,
                    'default_api_doc' => function ($service) {
                        return $this->buildServiceDoc($service->id, SqlSrv::getApiDocInfo($service));
                    },
                    'factory'         => function ($config) {
                        return new SqlSrv($config);
                    },
                ])
            );
        });

        // Add our database extensions.
        $this->app->resolving('db.schema', function (DbSchemaExtensions $db) {
            $db->extend('sqlsrv', function ($connection) {
                return new SqlServerSchema($connection);
            });
        });
    }
}
