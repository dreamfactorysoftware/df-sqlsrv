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