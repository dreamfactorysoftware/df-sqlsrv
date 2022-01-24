<?php

namespace DreamFactory\Core\SqlSrv\Database\Connectors;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use Illuminate\Database\Connectors\SqlServerConnector as LaravelSqlServerConnector;

class SqlServerConnector extends LaravelSqlServerConnector
{
    /**
     * @inheritdoc
     */
    protected function getSqlSrvDsn(array $config)
    {
        if (isset($config['database'])) $config['database'] = $config['database'] ?: null;
        if (isset($config['appname'])) $config['appname'] = $config['appname'] ?: null;
        if (isset($config['readonly'])) $config['readonly'] = $config['readonly'] ?: null;
        if (isset($config['encrypt'])) $config['encrypt'] = $config['encrypt'] ?: null;
        if (isset($config['trust_server_certificate'])) $config['trust_server_certificate'] = $config['trust_server_certificate'] ?: null;

        return parent::getSqlSrvDsn($config);
    }

    protected function getDsn(array $config)
    {
        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $config[$key] = $value;
            }
        }

        if (isset($config['attributes'])) {
            foreach ($config['attributes'] as $key => $value) {
                $config[$key] = $value;
            }
        }

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
