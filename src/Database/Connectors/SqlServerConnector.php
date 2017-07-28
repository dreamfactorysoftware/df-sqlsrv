<?php

namespace DreamFactory\Core\SqlSrv\Database\Connectors;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use Illuminate\Database\Connectors\SqlServerConnector as LaravelSqlServerConnector;

class SqlServerConnector extends LaravelSqlServerConnector
{
    /**
     * @inheritdoc
     */
    protected function getDsn(array $config)
    {
        $drivers = $this->getAvailableDrivers();
        // We override the default usage of dblib, for the sqlsrv driver, now available on Linux as well.
        if (in_array('sqlsrv', $drivers)) {
            return $this->getSqlSrvDsn($config);
        } elseif (in_array('dblib', $drivers)) {
            return $this->getDblibDsn($config);
        } elseif (in_array('odbc', $drivers) && array_get_bool($config, 'odbc')) {
            return $this->getOdbcDsn($config);
        } else {
            throw new InternalServerErrorException('No acceptable driver for SQL Server found.');
        }
    }
}
