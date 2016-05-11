<?php
namespace DreamFactory\Core\SqlSrv;

use DreamFactory\Core\Database\DbSchemaExtensions;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use DreamFactory\Core\SqlSrv\Database\Schema\SqlServerSchema;
use DreamFactory\Core\SqlSrv\Models\SqlSrvDbConfig;
use DreamFactory\Core\SqlSrv\Services\SqlSrv;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df){
            $df->addType(
                new ServiceType([
                    'name'                  => 'sqlsrv',
                    'label'                 => 'SQL Server',
                    'description'           => 'Database service supporting SQL Server connections.',
                    'group'                 => ServiceTypeGroups::DATABASE,
                    'config_handler'        => SqlSrvDbConfig::class,
                    'factory'               => function ($config){
                        return new SqlSrv($config);
                    },
                ])
            );
        });

        // Add our database extensions.
        $this->app->resolving('db.schema', function (DbSchemaExtensions $db){
            $db->extend('sqlsrv', function ($connection){
                return new SqlServerSchema($connection);
            });
        });
    }
}
