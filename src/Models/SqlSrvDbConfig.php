<?php
namespace DreamFactory\Core\SqlSrv\Models;

use DreamFactory\Core\SqlDb\Models\SqlDbConfig;

/**
 * SqlSrvDbConfig
 *
 */
class SqlSrvDbConfig extends SqlDbConfig
{
    public static function getDriverName()
    {
        return 'sqlsrv';
    }

    public static function getDefaultDsn()
    {
        // http://php.net/manual/en/ref.pdo-dblib.connection.php
        return 'dblib:host=localhost:2638;dbname=database';
    }
}