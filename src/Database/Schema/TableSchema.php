<?php
namespace DreamFactory\Core\SqlSrv\Database\Schema;

/**
 * TableSchema represents the metadata for a MSSQL table.
 */
class TableSchema extends \DreamFactory\Core\Database\Schema\TableSchema
{
    /**
     * @var string name of the catalog (database) that this table belongs to.
     * Defaults to null, meaning no schema (or the current database).
     */
    public $catalogName;
}
