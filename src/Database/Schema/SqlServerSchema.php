<?php
namespace DreamFactory\Core\SqlSrv\Database\Schema;

use DreamFactory\Core\Database\Enums\DbFunctionUses;
use DreamFactory\Core\Database\Enums\FunctionTypes;
use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Database\Schema\RoutineSchema;
use DreamFactory\Core\Database\Components\Schema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\DbResourceTypes;
use DreamFactory\Core\Enums\DbSimpleTypes;
use DreamFactory\Core\Exceptions\ForbiddenException;

/**
 * Schema is the class for retrieving metadata information from a MS SQL Server database.
 */
class SqlServerSchema extends Schema
{
    /**
     * Underlying database provides field-level schema, i.e. SQL (true) vs NoSQL (false)
     */
    const PROVIDES_FIELD_SCHEMA = true;

    const DEFAULT_SCHEMA = 'dbo';

    /**
     * @const string Quoting characters
     */
    const LEFT_QUOTE_CHARACTER = '[';

    const RIGHT_QUOTE_CHARACTER = ']';

    /**
     * @param boolean $refresh if we need to refresh schema cache.
     *
     * @return string default schema.
     */
    public function getDefaultSchema($refresh = false)
    {
        return static::DEFAULT_SCHEMA;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedResourceTypes()
    {
        return [
            DbResourceTypes::TYPE_TABLE,
            DbResourceTypes::TYPE_VIEW,
            DbResourceTypes::TYPE_PROCEDURE,
            DbResourceTypes::TYPE_FUNCTION
        ];
    }

    protected function translateSimpleColumnTypes(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'pk':
            case DbSimpleTypes::TYPE_ID:
                $info['type'] = 'int';
                $info['allow_null'] = false;
                $info['auto_increment'] = true;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case DbSimpleTypes::TYPE_REF:
                $info['type'] = 'int';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case DbSimpleTypes::TYPE_DATETIME:
                $info['type'] = 'datetime2';
                break;
            case DbSimpleTypes::TYPE_TIMESTAMP:
                $info['type'] = 'datetimeoffset';
                break;
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE:
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE:
                $info['type'] = 'datetimeoffset';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $default = 'CURRENT_TIMESTAMP';
                    $info['default'] = ['expression' => $default];
                }
                break;
            case DbSimpleTypes::TYPE_USER_ID:
            case DbSimpleTypes::TYPE_USER_ID_ON_CREATE:
            case DbSimpleTypes::TYPE_USER_ID_ON_UPDATE:
                $info['type'] = 'int';
                break;

            case DbSimpleTypes::TYPE_BOOLEAN:
                $info['type'] = 'bit';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (int)filter_var($default, FILTER_VALIDATE_BOOLEAN);
                }
                break;

            case DbSimpleTypes::TYPE_INTEGER:
                $info['type'] = 'int';
                break;

            case DbSimpleTypes::TYPE_DOUBLE:
                $info['type'] = 'float';
                $info['type_extras'] = '(53)';
                break;

            case DbSimpleTypes::TYPE_TEXT:
                $info['type'] = 'varchar';
                $info['type_extras'] = '(max)';
                break;
            case 'ntext':
                $info['type'] = 'nvarchar';
                $info['type_extras'] = '(max)';
                break;
            case 'image':
                $info['type'] = 'varbinary';
                $info['type_extras'] = '(max)';
                break;

            case DbSimpleTypes::TYPE_STRING:
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $national =
                    (isset($info['supports_multibyte'])) ? filter_var($info['supports_multibyte'],
                        FILTER_VALIDATE_BOOLEAN) : false;
                if ($fixed) {
                    $info['type'] = ($national) ? 'nchar' : 'char';
                } elseif ($national) {
                    $info['type'] = 'nvarchar';
                } else {
                    $info['type'] = 'varchar';
                }
                break;

            case DbSimpleTypes::TYPE_BINARY:
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $info['type'] = ($fixed) ? 'binary' : 'varbinary';
                break;
        }
    }

    protected function validateColumnSettings(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'bit':
            case 'tinyint':
            case 'smallint':
            case 'int':
            case 'bigint':
            case 'money':
            case 'smallmoney':
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default) && is_numeric($default)) {
                    $info['default'] = intval($default);
                }
                break;

            case 'decimal':
            case 'numeric':
                if (!isset($info['type_extras'])) {
                    $length =
                        (isset($info['length']))
                            ? $info['length']
                            : ((isset($info['precision'])) ? $info['precision']
                            : null);
                    if (!empty($length)) {
                        $scale =
                            (isset($info['decimals']))
                                ? $info['decimals']
                                : ((isset($info['scale'])) ? $info['scale']
                                : null);
                        $info['type_extras'] = (!empty($scale)) ? "($length,$scale)" : "($length)";
                    }
                }

                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default) && is_numeric($default)) {
                    $info['default'] = floatval($default);
                }
                break;
            case 'real':
            case 'float':
                if (!isset($info['type_extras'])) {
                    $length =
                        (isset($info['length']))
                            ? $info['length']
                            : ((isset($info['precision'])) ? $info['precision']
                            : null);
                    if (!empty($length)) {
                        $info['type_extras'] = "($length)";
                    }
                }

                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default) && is_numeric($default)) {
                    $info['default'] = floatval($default);
                }
                break;

            case 'char':
            case 'nchar':
            case 'binary':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                }
                break;

            case 'varchar':
            case 'nvarchar':
            case 'varbinary':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                } else // requires a max length
                {
                    $info['type_extras'] = '(' . static::DEFAULT_STRING_MAX_SIZE . ')';
                }
                break;

            case 'time':
            case 'datetime':
            case 'datetime2':
            case 'datetimeoffset':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                }
                break;
        }
    }

    /**
     * @param array $info
     *
     * @return string
     * @throws \Exception
     */
    protected function buildColumnDefinition(array $info)
    {
        $type = (isset($info['type'])) ? $info['type'] : null;
        $typeExtras = (isset($info['type_extras'])) ? $info['type_extras'] : null;

        $definition = $type . $typeExtras;

        $allowNull = (isset($info['allow_null'])) ? filter_var($info['allow_null'], FILTER_VALIDATE_BOOLEAN) : false;
        $definition .= ($allowNull) ? ' NULL' : ' NOT NULL';

        $default = (isset($info['default'])) ? $info['default'] : null;
        if (isset($default)) {
            if (is_array($default)) {
                $expression = (isset($default['expression'])) ? $default['expression'] : null;
                if (null !== $expression) {
                    $definition .= ' DEFAULT ' . $expression;
                }
            } else {
                $default = $this->quoteValue($default);
                $definition .= ' DEFAULT ' . $default;
            }
        }

        $auto = (isset($info['auto_increment'])) ? filter_var($info['auto_increment'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($auto) {
            $definition .= ' IDENTITY';
        }

        $isUniqueKey = (isset($info['is_unique'])) ? filter_var($info['is_unique'], FILTER_VALIDATE_BOOLEAN) : false;
        $isPrimaryKey =
            (isset($info['is_primary_key'])) ? filter_var($info['is_primary_key'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($isPrimaryKey && $isUniqueKey) {
            throw new \Exception('Unique and Primary designations not allowed simultaneously.');
        }

        if ($isUniqueKey) {
            $definition .= ' UNIQUE';
        } elseif ($isPrimaryKey) {
            $definition .= ' PRIMARY KEY';
        }

        return $definition;
    }

    /**
     * Compares two table names.
     * The table names can be either quoted or unquoted. This method
     * will consider both cases.
     *
     * @param string $name1 table name 1
     * @param string $name2 table name 2
     *
     * @return boolean whether the two table names refer to the same table.
     */
    public function compareTableNames($name1, $name2)
    {
        $name1 = str_replace(['[', ']'], '', $name1);
        $name2 = str_replace(['[', ']'], '', $name2);

        return parent::compareTableNames(strtolower($name1), strtolower($name2));
    }

    /**
     * Resets the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or max value of a primary key plus one (i.e. sequence trimming).
     *
     * @param TableSchema  $table   the table schema whose primary key sequence will be reset
     * @param integer|null $value   the value for the primary key of the next new row inserted.
     *                              If this is not set, the next new row's primary key will have the max value of a
     *                              primary key plus one (i.e. sequence trimming).
     *
     */
    public function resetSequence($table, $value = null)
    {
        if ($table->sequenceName === null) {
            return;
        }
        if ($value !== null) {
            $value = (int)($value) - 1;
        } else {
            $sql = <<<MYSQL
SELECT MAX([{$table->primaryKey}]) FROM {$table->quotedName}
MYSQL;
            $value = (int)$this->selectValue($sql);
        }
        $name = strtr($table->quotedName, ['[' => '', ']' => '']);
        $this->connection->statement("DBCC CHECKIDENT ('$name',RESEED,$value)");
    }

    private $normalTables = [];  // non-view tables

    /**
     * Enables or disables integrity check.
     *
     * @param boolean $check  whether to turn on or off the integrity check.
     * @param string  $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *
     */
    public function checkIntegrity($check = true, $schema = '')
    {
        $enable = $check ? 'CHECK' : 'NOCHECK';
        if (!isset($this->normalTables[$schema])) {
            $this->normalTables[$schema] = $this->findTableNames($schema);
        }
        $db = $this->connection;
        foreach ($this->normalTables[$schema] as $table) {
            $tableName = $this->quoteTableName($table->name);
            /** @noinspection SqlNoDataSourceInspection */
            $db->statement("ALTER TABLE $tableName $enable CONSTRAINT ALL");
        }
    }

    protected function findTableReferences()
    {
        $rc = 'INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS';
        $kcu = 'INFORMATION_SCHEMA.KEY_COLUMN_USAGE';
        if (isset($this->catalogName)) {
            $kcu = $this->catalogName . '.' . $kcu;
            $rc = $this->catalogName . '.' . $rc;
        }

        //From http://msdn2.microsoft.com/en-us/library/aa175805(SQL.80).aspx
        $sql = <<<EOD
		SELECT
		     KCU1.TABLE_SCHEMA AS 'table_schema'
		   , KCU1.TABLE_NAME AS 'table_name'
		   , KCU1.COLUMN_NAME AS 'column_name'
		   , KCU2.TABLE_SCHEMA AS 'referenced_table_schema'
		   , KCU2.TABLE_NAME AS 'referenced_table_name'
		   , KCU2.COLUMN_NAME AS 'referenced_column_name'
		FROM {$this->quoteTableName($rc)} RC
		JOIN {$this->quoteTableName($kcu)} KCU1
		ON KCU1.CONSTRAINT_CATALOG = RC.CONSTRAINT_CATALOG
		   AND KCU1.CONSTRAINT_SCHEMA = RC.CONSTRAINT_SCHEMA
		   AND KCU1.CONSTRAINT_NAME = RC.CONSTRAINT_NAME
		JOIN {$this->quoteTableName($kcu)} KCU2
		ON KCU2.CONSTRAINT_CATALOG = RC.UNIQUE_CONSTRAINT_CATALOG
		   AND KCU2.CONSTRAINT_SCHEMA =	RC.UNIQUE_CONSTRAINT_SCHEMA
		   AND KCU2.CONSTRAINT_NAME = RC.UNIQUE_CONSTRAINT_NAME
		   AND KCU2.ORDINAL_POSITION = KCU1.ORDINAL_POSITION
EOD;

        return $this->connection->select($sql);
    }

    /**
     * @inheritdoc
     */
    protected function findColumns(TableSchema $table)
    {
        $sql = <<<MYSQL
SELECT col.name, col.precision, col.scale, col.max_length, col.collation_name, col.is_nullable, col.is_identity,
       coltype.name as type, coldef.definition as default_definition, 
       '0' as is_primary_key, '0' as is_unique, '0' as is_index
FROM sys.columns AS col
LEFT OUTER JOIN sys.types AS coltype ON coltype.user_type_id = col.user_type_id
LEFT OUTER JOIN sys.default_constraints AS coldef ON coldef.parent_column_id = col.column_id AND coldef.parent_object_id = col.object_id
WHERE col.object_id = object_id('{$table->quotedName}')
MYSQL;

        $columns = $this->connection->select($sql);

        // gather index information
        $sql = <<<MYSQL
SELECT col.name, idx.name as is_index, idx.is_unique, idx.is_primary_key
FROM sys.index_columns AS idx_col
LEFT OUTER JOIN sys.indexes AS idx ON idx_col.index_id = idx.index_id AND idx.object_id = idx_col.object_id
LEFT OUTER JOIN sys.columns AS col ON idx_col.column_id = col.column_id AND idx_col.object_id = col.object_id
WHERE idx_col.object_id = object_id('{$table->quotedName}')
MYSQL;

        $indexes = $this->connection->select($sql);
        foreach ($indexes as $index) {
            foreach ($columns as &$column) {
                if ($index->name === $column->name) {
                    if (boolval($index->is_primary_key)) {
                        $column->is_primary_key = true;
                    }
                    if (boolval($index->is_unique)) {
                        $column->is_unique = true;
                    }
                    if (boolval($index->is_index)) {
                        $column->is_index = true;
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * Creates a table column.
     *
     * @param array $column column metadata
     *
     * @return ColumnSchema normalized column metadata
     */
    protected function createColumn($column)
    {
        $c = new ColumnSchema(['name' => $column['name']]);
        $c->quotedName = $this->quoteColumnName($c->name);
        $c->allowNull = boolval($column['is_nullable']);
        $c->isPrimaryKey = boolval($column['is_primary_key']);
        $c->isUnique = boolval($column['is_unique']);
        $c->isIndex = boolval($column['is_index']);
        $c->dbType = $column['type'];
        $c->precision = intval($column['precision']);
        $c->scale = intval($column['scale']);
        // all of this is for consistency across drivers
        if ($c->precision > 0) {
            if ($c->scale <= 0) {
                $c->size = $c->precision;
                $c->scale = null;
            }
        } else {
            $c->precision = null;
            $c->scale = null;
            $c->size = intval($column['max_length']);
            if ($c->size <= 0) {
                $c->size = null;
            }
        }
        $c->autoIncrement = boolval($column['is_identity']);
//        $c->comment = strval($column['comment']);

        $c->fixedLength = $this->extractFixedLength($c->dbType);
        $c->supportsMultibyte = $this->extractMultiByteSupport($c->dbType);
        $this->extractType($c, $c->dbType);
        if (isset($column['default_definition'])) {
            $this->extractDefault($c, $column['default_definition']);
        }
        // special type handlers
        switch ($c->dbType) {
            case 'image':
                $c->dbFunction = [
                    [
                        'use'           => [DbFunctionUses::SELECT],
                        'function'      => "(CONVERT(varbinary(max), {$c->quotedName}))",
                        'function_type' => FunctionTypes::DATABASE
                    ]
                ];
                break;
            case 'timestamp': // deprecated, not a real timestamp, but internal rowversion
            case 'rowversion':
            $c->dbFunction = [
                [
                    'use'           => [DbFunctionUses::SELECT],
                    'function'      => "CAST({$c->quotedName} AS BIGINT)",
                    'function_type' => FunctionTypes::DATABASE
                ]
            ];
                break;
            case 'geometry':
            case 'geography':
            case 'hierarchyid':
            $c->dbFunction = [
                [
                    'use'           => [DbFunctionUses::SELECT],
                    'function'      => "({$c->quotedName}.ToString())",
                    'function_type' => FunctionTypes::DATABASE
                ]
            ];
                break;
            case 'uniqueidentifier':
                $c->dbFunction = [
                    [
                        'use'           => [DbFunctionUses::SELECT],
                        'function'      => "(CONVERT(varchar(255), {$c->quotedName}))",
                        'function_type' => FunctionTypes::DATABASE
                    ]
                ];
                break;
        }

        return $c;
    }

    protected function findSchemaNames()
    {
        $sql = <<<MYSQL
SELECT schema_name FROM INFORMATION_SCHEMA.SCHEMATA WHERE schema_name NOT IN
('INFORMATION_SCHEMA', 'sys', 'db_owner', 'db_accessadmin', 'db_securityadmin',
'db_ddladmin', 'db_backupoperator', 'db_datareader', 'db_datawriter',
'db_denydatareader', 'db_denydatawriter')
MYSQL;

        return $this->selectColumn($sql);
    }

    /**
     * @inheritdoc
     */
    protected function findTableNames($schema = '')
    {
        $sql = <<<EOD
SELECT TABLE_NAME, TABLE_SCHEMA FROM [INFORMATION_SCHEMA].[TABLES] WHERE TABLE_TYPE = 'BASE TABLE'
EOD;

        if (!empty($schema)) {
            $sql .= " AND TABLE_SCHEMA = '$schema'";
        }

        $rows = $this->connection->select($sql);

        $defaultSchema = $this->getNamingSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $schemaName = isset($row['TABLE_SCHEMA']) ? $row['TABLE_SCHEMA'] : '';
            $resourceName = isset($row['TABLE_NAME']) ? $row['TABLE_NAME'] : '';
            $internalName = $schemaName . '.' . $resourceName;
            $name = ($addSchema) ? $internalName : $resourceName;
            $quotedName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($resourceName);;
            $settings = compact('schemaName', 'resourceName', 'name', 'internalName', 'quotedName');
            $names[strtolower($name)] = new TableSchema($settings);
        }

        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function findViewNames($schema = '')
    {
        $sql = <<<EOD
SELECT TABLE_NAME, TABLE_SCHEMA FROM [INFORMATION_SCHEMA].[TABLES] WHERE TABLE_TYPE = 'VIEW'
EOD;

        if (!empty($schema)) {
            $sql .= " AND TABLE_SCHEMA = '$schema'";
        }

        $rows = $this->connection->select($sql);

        $defaultSchema = $this->getNamingSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $schemaName = isset($row['TABLE_SCHEMA']) ? $row['TABLE_SCHEMA'] : '';
            $resourceName = isset($row['TABLE_NAME']) ? $row['TABLE_NAME'] : '';
            $internalName = $schemaName . '.' . $resourceName;
            $name = ($addSchema) ? $internalName : $resourceName;
            $quotedName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($resourceName);
            $settings = compact('schemaName', 'resourceName', 'name', 'internalName', 'quotedName');
            $settings['isView'] = true;
            $names[strtolower($name)] = new TableSchema($settings);
        }

        return $names;
    }

    /**
     * @inheritdoc
     */
    public function renameTable($table, $newName)
    {
        return "sp_rename '$table', '$newName'";
    }

    /**
     * @inheritdoc
     */
    public function renameColumn($table, $name, $newName)
    {
        return "sp_rename '$table.$name', '$newName', 'COLUMN'";
    }

    /**
     * @inheritdoc
     */
    public function addColumn($table, $column, $type)
    {
        return <<<MYSQL
ALTER TABLE $table ADD {$this->quoteColumnName($column)} {$this->getColumnType($type)};
MYSQL;
    }

    /**
     * @inheritdoc
     */
    public function alterColumn($table, $column, $definition)
    {
        $sql = <<<MYSQL
ALTER TABLE $table ALTER COLUMN {$this->quoteColumnName($column)} {$this->getColumnType($definition)}
MYSQL;

        return $sql;
    }

    /**
     * @inheritdoc
     */
    public function dropColumns($table, $columns)
    {
        $columns = (array)$columns;

        if (!empty($columns)) {
            return $this->connection->statement("ALTER TABLE $table DROP COLUMN" . implode(',', $columns));
        }

        return false;
    }

    public function getTimestampForSet()
    {
        return $this->connection->raw('(SYSDATETIMEOFFSET())');
    }

    /**
     * Extracts the PHP type from DB type.
     *
     * @param ColumnSchema $column
     * @param string       $dbType DB type
     */
    public function extractType(ColumnSchema $column, $dbType)
    {
        parent::extractType($column, $dbType);

        if ((false !== strpos($dbType, 'varchar')) && (null === $column->size)) {
            $column->type = DbSimpleTypes::TYPE_TEXT;
        }
        if ((0 === strcasecmp($dbType, 'timestamp')) || (0 === strcasecmp($dbType, 'rowversion'))) {
            $column->type = DbSimpleTypes::TYPE_BIG_INT;
        }
    }

    /**
     * Extracts the default value for the column.
     * The value is typecasted to correct PHP type.
     *
     * @param ColumnSchema $field
     * @param mixed        $defaultValue the default value obtained from metadata
     */
    public function extractDefault(ColumnSchema $field, $defaultValue)
    {
        if ($defaultValue == '(NULL)') {
            $field->defaultValue = null;
        } elseif ($field->type === DbSimpleTypes::TYPE_BOOLEAN) {
            if ('((1))' === $defaultValue) {
                $field->defaultValue = true;
            } elseif ('((0))' === $defaultValue) {
                $field->defaultValue = false;
            } else {
                $field->defaultValue = null;
            }
        } elseif ($field->type === DbSimpleTypes::TYPE_TIMESTAMP) {
            $field->defaultValue = null;
        } else {
            parent::extractDefault($field, str_replace(['(', ')', "'"], '', $defaultValue));
        }
    }

    /**
     * Extracts size, precision and scale information from column's DB type.
     * We do nothing here, since sizes and precisions have been computed before.
     *
     * @param ColumnSchema $field
     * @param string       $dbType the column's DB type
     */
    public function extractLimit(ColumnSchema $field, $dbType)
    {
    }

    public function typecastToNative($value, $field_info, $allow_null = true)
    {
        switch ($field_info->dbType) {
            case 'rowversion':
            case 'timestamp':
                throw new ForbiddenException('Field type not able to be set.');
            case 'uniqueidentifier':
                if (0 === strcasecmp('null', $value)) {
                    return null;
                }
                break;
        }

        return parent::typecastToNative($value, $field_info, $allow_null);
    }

    /**
     * @inheritdoc
     */
    protected function getProcedureStatement(RoutineSchema $routine, array $param_schemas, array &$values)
    {
        if (!in_array('sqlsrv', \PDO::getAvailableDrivers())) {
            // Note that using the dblib driver doesn't allow binding of output parameters,
            // and also requires declaration prior to and selecting after to retrieve them.
            $paramStr = '';
            $prefix = '';
            $postfix = '';
            foreach ($param_schemas as $key => $paramSchema) {
                switch ($paramSchema->paramType) {
                    case 'IN':
                        $pName = ':' . $paramSchema->name;
                        $paramStr .= (empty($paramStr)) ? $pName : ", $pName";
                        break;
                    case 'INOUT':
                        $pName = '@' . $paramSchema->name;
                        $paramStr .= (empty($paramStr) ? $pName : ", $pName") . " OUTPUT";
                        $paramType = $paramSchema->dbType;
                        if ((DbSimpleTypes::TYPE_INTEGER !== $paramSchema->type) && !empty($paramSchema->length)) {
                            $paramType .= '(' . $paramSchema->length . ')';
                        }
                        $prefix .= "DECLARE $pName $paramType;";
                        if (array_key_exists($key, $values)) {
                            // workaround for MS reporting OUT-behaving params as INOUT
                            if (is_null($value = array_get($values, $key))) {
                                $value = 'NULL';
                            }
                            $prefix .= "SET $pName = $value;";
                        }
                        $postfix .= "SELECT $pName as " . $this->quoteColumnName($paramSchema->name) . ';';
                        break;
                    case 'OUT':
                        $pName = '@' . $paramSchema->name;
                        $paramStr .= (empty($paramStr) ? $pName : ", $pName") . " OUTPUT";
                        $paramType = $paramSchema->dbType;
                        if (!empty($paramSchema->length)) {
                            $paramType .= '(' . $paramSchema->length . ')';
                        }
                        $prefix .= "DECLARE $pName $paramType;";
                        $postfix .= "SELECT $pName as " . $this->quoteColumnName($paramSchema->name) . ';';
                        break;
                    default:
                        break;
                }
            }

            return "$prefix EXEC {$routine->quotedName} $paramStr; $postfix";
        } else {
            $paramStr = '';
            foreach ($param_schemas as $key => $paramSchema) {
                switch ($paramSchema->paramType) {
                    case 'IN':
                    case 'INOUT':
                    case 'OUT':
                        $pName = '@' . $paramSchema->name;
                        $paramStr .= (empty($paramStr) ? $pName : ", $pName") . '=:' . $paramSchema->name;
                        break;
                    default:
                        break;
                }
            }

            return "EXEC {$routine->quotedName} $paramStr";
        }
    }

    protected function doRoutineBinding($statement, array $paramSchemas, array &$values)
    {
        if (!in_array('sqlsrv', \PDO::getAvailableDrivers())) {
            // do dblib version of binding
            foreach ($paramSchemas as $key => $paramSchema) {
                switch ($paramSchema->paramType) {
                    case 'IN':
                        $this->bindValue($statement, ':' . $paramSchema->name, array_get($values, $key));
                        break;
                    case 'INOUT':
                    case 'OUT':
                        // Note that using the dblib driver doesn't allow binding of output parameters,
                        // and also requires declaration prior to and selecting after to retrieve them.
                        break;
                }
            }
        } else {
            parent::doRoutineBinding($statement, $paramSchemas, $values);
        }
    }

    /**
     * @inheritdoc
     */
    protected function getFunctionStatement(RoutineSchema $routine, array $param_schemas, array &$values)
    {
        // must always use schema in function name
        $name = $routine->quotedName;
        if (0 !== strpos($name, '.')) {
            $name = static::DEFAULT_SCHEMA . '.' . $name;
        }

        $paramStr = $this->getRoutineParamString($param_schemas, $values);

        switch ($routine->returnType) {
            case DbSimpleTypes::TYPE_TABLE:
                return "SELECT * FROM $name($paramStr) AS " . $this->quoteColumnName('output');
            default:
                return "SELECT $name($paramStr) AS " . $this->quoteColumnName('output');
        }
    }
}
