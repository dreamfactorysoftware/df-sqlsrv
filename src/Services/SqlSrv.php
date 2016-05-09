<?php

namespace DreamFactory\Core\SqlSrv\Services;

use DreamFactory\Core\SqlDb\Services\SqlDb;

/**
 * Class SqlSrv
 *
 * @package DreamFactory\Core\SqlDb\Services
 */
class SqlSrv extends SqlDb
{
    public static function adaptConfig(array &$config)
    {
        $config['driver'] = 'sqlsrv';
        if (in_array('dblib', \PDO::getAvailableDrivers())) {
            if (null !== $dumpLocation = config('df.db.freetds.dump')) {
                if (!putenv("TDSDUMP=$dumpLocation")) {
                    \Log::alert('Could not write environment variable for TDSDUMP location.');
                }
            }
            if (null !== $dumpConfLocation = config('df.db.freetds.dumpconfig')) {
                if (!putenv("TDSDUMPCONFIG=$dumpConfLocation")) {
                    \Log::alert('Could not write environment variable for TDSDUMPCONFIG location.');
                }
            }
            if (null !== $confLocation = config('df.db.freetds.sqlsrv')) {
                if (!putenv("FREETDSCONF=$confLocation")) {
                    \Log::alert('Could not write environment variable for FREETDSCONF location.');
                }
            }
        }

        $dsn = isset($config['dsn']) ? $config['dsn'] : null;
        if (!empty($dsn)) {
            // default PDO DSN pieces
            $dsn = str_replace(' ', '', $dsn);
            if (!isset($config['port']) && (false !== ($pos = strpos($dsn, 'port=')))) {
                $temp = substr($dsn, $pos + 5);
                $config['port'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
            }
            if (!isset($config['host']) && (false !== ($pos = strpos($dsn, 'host=')))) {
                $temp = substr($dsn, $pos + 5);
                $host = (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                if (!isset($config['port']) && (false !== ($pos = stripos($host, ':')))) {
                    $temp = substr($host, $pos + 1);
                    $host = substr($host, 0, $pos);
                    $config['port'] = (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                }
                $config['host'] = $host;
            }
            if (!isset($config['database']) && (false !== ($pos = strpos($dsn, 'dbname=')))) {
                $temp = substr($dsn, $pos + 7);
                $config['database'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
            }
            if (!isset($config['charset'])) {
                if (false !== ($pos = strpos($dsn, 'charset='))) {
                    $temp = substr($dsn, $pos + 8);
                    $config['charset'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                } else {
                    $config['charset'] = 'utf8';
                }
            }
            // SQL Server native driver specifics
            if (!isset($config['host']) && (false !== ($pos = stripos($dsn, 'Server=')))) {
                $temp = substr($dsn, $pos + 7);
                $host = (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                if (!isset($config['port']) && (false !== ($pos = stripos($host, ',')))) {
                    $temp = substr($host, $pos + 1);
                    $host = substr($host, 0, $pos);
                    $config['port'] = (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                }
                $config['host'] = $host;
            }
            if (!isset($config['database']) && (false !== ($pos = stripos($dsn, 'Database=')))) {
                $temp = substr($dsn, $pos + 9);
                $config['database'] = (false !== $pos = stripos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
            }
        }

        if (!isset($config['collation'])) {
            $config['collation'] = 'utf8_unicode_ci';
        }

        // must be there
        if (!array_key_exists('database', $config)) {
            $config['database'] = null;
        }

        // must be there
        if (!array_key_exists('prefix', $config)) {
            $config['prefix'] = null;
        }

        // laravel database config requires options to be [], not null
        if (array_key_exists('options', $config) && is_null($config['options'])) {
            $config['options'] = [];
        }
    }

    protected function initStatements($statements = [])
    {
        if (is_string($statements)) {
            $statements = [$statements];
        } elseif (!is_array($statements)) {
            $statements = [];
        }

        // These are on by default for sqlsrv driver, but not dblib.
        // Also, can't use 'SET ANSI_DEFAULTS ON', seems to return false positives for DROP TABLE etc. todo
        array_unshift($statements, 'SET QUOTED_IDENTIFIER ON;');
        array_unshift($statements, 'SET ANSI_WARNINGS ON;');
        array_unshift($statements, 'SET ANSI_NULLS ON;');

        foreach ($statements as $statement) {
            $this->dbConn->statement($statement);
        }
    }
}