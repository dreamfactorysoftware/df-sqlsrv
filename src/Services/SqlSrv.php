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
        if (!in_array('sqlsrv', \PDO::getAvailableDrivers())) {
            // assume dblib driver and FreeTDS are available
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

        parent::adaptConfig($config);
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