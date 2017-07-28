<?php

namespace DreamFactory\Core\SqlSrv\Database\Query\Grammars;

class SqlServerGrammar extends \Illuminate\Database\Query\Grammars\SqlServerGrammar
{
    /**
     * @inheritdoc
     */
    public function getDateFormat()
    {
        if (in_array('sqlsrv', \PDO::getAvailableDrivers())) {
            return parent::getDateFormat();
        }

        // DBLIB driver datetime format doesn't include .000
        return 'Y-m-d H:i:s';
    }
}
