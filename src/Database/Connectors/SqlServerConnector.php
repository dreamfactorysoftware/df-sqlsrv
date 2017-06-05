<?php

namespace DreamFactory\Core\SqlSrv\Database\Connectors;

use Illuminate\Database\Connectors\SqlServerConnector as LaravelSqlServerConnector;

class SqlServerConnector extends LaravelSqlServerConnector
{
    /**
     * @inheritdoc
     */
    protected function getDsn(array $config)
    {
        // We override the default usage of dblib, for the sqlsrv driver, now available on Linux as well.
        if (in_array('sqlsrv', $this->getAvailableDrivers())) {
            return $this->getSqlSrvDsn($config);
        } elseif (in_array('dblib', $this->getAvailableDrivers())) {
            return $this->getDblibDsn($config);
        } elseif ($this->prefersOdbc($config)) {
            return $this->getOdbcDsn($config);
        } else {
            return $this->getDblibDsn($config);
        }
    }
}
