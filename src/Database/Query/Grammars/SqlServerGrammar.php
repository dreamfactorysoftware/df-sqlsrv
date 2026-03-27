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

    /**
     * Wrap a table in keyword identifiers.
     * Handles linked server table names that use double-underscore separators
     * (e.g. SERVER__SCHEMA__TABLE) and converts them to four-part SQL Server
     * linked server syntax: [SERVER]..[SCHEMA].[TABLE]
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @return string
     */
    public function wrapTable($table)
    {
        if (!$this->isExpression($table) && substr_count($table, '__') === 2) {
            $parts = explode('__', $table, 3);
            if (count($parts) === 3) {
                return '[' . $parts[0] . ']..[' . $parts[1] . '].[' . $parts[2] . ']';
            }
        }

        return parent::wrapTable($table);
    }
}
