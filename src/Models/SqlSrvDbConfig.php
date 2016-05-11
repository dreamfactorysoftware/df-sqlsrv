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
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            return 'sqlsrv';
        } else {
            return 'dblib';
        }
    }

    public static function getDefaultDsn()
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            // http://php.net/manual/en/ref.pdo-sqlsrv.connection.php
            return 'sqlsrv:Server=localhost,1433;Database=db';
        } else {
            // http://php.net/manual/en/ref.pdo-dblib.connection.php
            return 'dblib:host=localhost:1433;dbname=database;charset=UTF-8';
        }
    }

    public static function getDefaultPort()
    {
        return 1433;
    }
}