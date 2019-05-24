<?php

namespace DreamFactory\Core\SqlSrv\Database\Schema;

use DreamFactory\Core\Database\Enums\DbFunctionUses;
use DreamFactory\Core\Database\Enums\FunctionTypes;
use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Database\Schema\FunctionSchema;
use DreamFactory\Core\Database\Schema\ParameterSchema;
use DreamFactory\Core\Database\Schema\ProcedureSchema;
use DreamFactory\Core\Database\Schema\RoutineSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\DbSimpleTypes;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\SqlDb\Database\Schema\SqlSchema;

/**
 * Schema is the class for retrieving metadata information from a MS SQL Server database.
 */
class SqlServerSchema extends SqlSchema
{
    const DEFAULT_SCHEMA = 'dbo';

    /**
     * @const string Quoting characters
     */
    const LEFT_QUOTE_CHARACTER = '[';

    const RIGHT_QUOTE_CHARACTER = ']';

    public static function useSqlsrv()
    {
        return (in_array('sqlsrv', \PDO::getAvailableDrivers()));
    }

    /**
     * @inheritdoc
     */
    public function getDefaultSchema()
    {
        return static::DEFAULT_SCHEMA;
    }

    /**
     * @inheritdoc
     */
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

        if (isset($info['is_primary_key']) && filter_var($info['is_primary_key'], FILTER_VALIDATE_BOOLEAN)) {
            $definition .= ' PRIMARY KEY';
        } elseif (isset($info['is_unique']) && filter_var($info['is_unique'], FILTER_VALIDATE_BOOLEAN)) {
            $definition .= ' UNIQUE';
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

    /**
     * @inheritdoc
     */
    protected function loadTableColumns(TableSchema $table)
    {
        $params = [
            ':table'  => $table->resourceName,
            ':schema' => $table->schemaName,
        ];
        $sql = <<<MYSQL
SELECT col.column_name, col.numeric_precision, col.numeric_scale, col.character_maximum_length, col.is_nullable, idcol.is_identity,
       col.data_type, col.column_default 
FROM INFORMATION_SCHEMA.COLUMNS AS col
LEFT JOIN sys.identity_columns AS idcol ON idcol.object_id = object_id('{$table->quotedName}') AND idcol.name = col.column_name
WHERE col.table_schema = :schema AND col.table_name = :table
ORDER BY col.ordinal_position
MYSQL;

        $columns = $this->connection->select($sql, $params);
        foreach ($columns as $column) {
            $column = array_change_key_case((array)$column, CASE_LOWER);
            $c = new ColumnSchema(['name' => $column['column_name']]);
            $c->quotedName = $this->quoteColumnName($c->name);
            $c->allowNull = to_bool($column['is_nullable']); // "NO" or "YES"
//            $c->isIndex = boolval($column['is_index']);
            $c->dbType = $column['data_type'];
            $c->precision = intval($column['numeric_precision']);
            $c->scale = intval($column['numeric_scale']);
            // all of this is for consistency across drivers
            if ($c->precision > 0) {
                if ($c->scale <= 0) {
                    $c->size = $c->precision;
                    $c->scale = null;
                }
            } else {
                $c->precision = null;
                $c->scale = null;
                $c->size = intval($column['character_maximum_length']);
                if ($c->size <= 0) {
                    $c->size = null;
                }
            }
            $c->autoIncrement = boolval($column['is_identity']);
//        $c->comment = strval($column['comment']);

            $c->fixedLength = $this->extractFixedLength($c->dbType);
            $c->supportsMultibyte = $this->extractMultiByteSupport($c->dbType);
            $this->extractType($c, $c->dbType);
            if (isset($column['column_default'])) {
                $this->extractDefault($c, $column['column_default']);
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

            $table->addColumn($c);
        }
    }

    protected function getTableConstraints($schema = '')
    {
        if (is_array($schema)) {
            $schema = implode("','", $schema);
        }

        $sql = <<<SQL
SELECT tc.constraint_type, tc.constraint_schema, tc.constraint_name, tc.constraint_type, tc.table_schema, tc.table_name, kcu.column_name, 
kcu2.table_schema as referenced_table_schema, kcu2.table_name as referenced_table_name, kcu2.column_name as referenced_column_name, 
rc.update_rule, rc.delete_rule
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu ON tc.constraint_schema = kcu.constraint_schema AND tc.constraint_name = kcu.constraint_name AND tc.table_name = kcu.table_name
LEFT JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc ON tc.constraint_schema = rc.constraint_schema AND tc.constraint_name = rc.constraint_name
LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu2 ON rc.unique_constraint_schema = kcu2.constraint_schema AND rc.unique_constraint_name = kcu2.constraint_name
WHERE tc.constraint_schema IN ('{$schema}');
SQL;

        $results = $this->connection->select($sql);
        $constraints = [];
        foreach ($results as $row) {
            $row = array_change_key_case((array)$row, CASE_LOWER);
            $ts = strtolower($row['table_schema']);
            $tn = strtolower($row['table_name']);
            $cn = strtolower($row['constraint_name']);
            $colName = array_get($row, 'column_name');
            $refColName = array_get($row, 'referenced_column_name');
            if (isset($constraints[$ts][$tn][$cn])) {
                $constraints[$ts][$tn][$cn]['column_name'] =
                    array_merge((array)$constraints[$ts][$tn][$cn]['column_name'], (array)$colName);

                if (isset($refColName)) {
                    $constraints[$ts][$tn][$cn]['referenced_column_name'] =
                        array_merge((array)$constraints[$ts][$tn][$cn]['referenced_column_name'], (array)$refColName);
                }
            } else {
                $constraints[$ts][$tn][$cn] = $row;
            }
        }

        return $constraints;
    }

    public function getSchemas()
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
    protected function getTableNames($schema = '')
    {
        $sql = <<<EOD
SELECT TABLE_NAME, TABLE_SCHEMA FROM [INFORMATION_SCHEMA].[TABLES] WHERE TABLE_TYPE = 'BASE TABLE'
EOD;

        if (!empty($schema)) {
            $sql .= " AND TABLE_SCHEMA = '$schema'";
        }

        $rows = $this->connection->select($sql);

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);

            $schemaName = isset($row['TABLE_SCHEMA']) ? $row['TABLE_SCHEMA'] : '';
            $resourceName = isset($row['TABLE_NAME']) ? $row['TABLE_NAME'] : '';
            $internalName = $schemaName . '.' . $resourceName;
            $name = $resourceName;
            $quotedName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($resourceName);
            $settings = compact('schemaName', 'resourceName', 'name', 'internalName', 'quotedName');
            $names[strtolower($name)] = new TableSchema($settings);
        }

        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function getViewNames($schema = '')
    {
        $sql = <<<EOD
SELECT TABLE_NAME, TABLE_SCHEMA FROM [INFORMATION_SCHEMA].[TABLES] WHERE TABLE_TYPE = 'VIEW'
EOD;

        if (!empty($schema)) {
            $sql .= " AND TABLE_SCHEMA = '$schema'";
        }

        $rows = $this->connection->select($sql);

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $schemaName = isset($row['TABLE_SCHEMA']) ? $row['TABLE_SCHEMA'] : '';
            $resourceName = isset($row['TABLE_NAME']) ? $row['TABLE_NAME'] : '';
            $internalName = $schemaName . '.' . $resourceName;
            $name = $resourceName;
            $quotedName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($resourceName);
            $settings = compact('schemaName', 'resourceName', 'name', 'internalName', 'quotedName');
            $settings['isView'] = true;
            $names[strtolower($name)] = new TableSchema($settings);
        }

        return $names;
    }

    protected function getRoutineNames($type, $schema = '')
    {
        $bindings = [':type' => $type];
        $where = 'ROUTINE_TYPE = :type';
        if (!empty($schema)) {
            $where .= ' AND ROUTINE_SCHEMA = :schema';
            $bindings[':schema'] = $schema;
        }

        $sql = <<<MYSQL
SELECT ROUTINE_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.ROUTINES WHERE {$where}
MYSQL;

        $rows = $this->connection->select($sql, $bindings);

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $resourceName = array_get($row, 'ROUTINE_NAME');
            $schemaName = $schema;
            $internalName = $schemaName . '.' . $resourceName;
            $name = $resourceName;
            $quotedName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($resourceName);
            $returnType = array_get($row, 'DATA_TYPE');
            if (!empty($returnType) && (0 !== strcasecmp('void', $returnType))) {
                $returnType = static::extractSimpleType($returnType);
            }
            $settings = compact('schemaName', 'resourceName', 'name', 'internalName', 'quotedName', 'returnType');
            $names[strtolower($name)] =
                ('PROCEDURE' === $type) ? new ProcedureSchema($settings) : new FunctionSchema($settings);
        }

        return $names;
    }

    protected function loadParameters(RoutineSchema $holder)
    {
        $sql = <<<MYSQL
SELECT p.ORDINAL_POSITION, p.PARAMETER_MODE, p.PARAMETER_NAME, p.DATA_TYPE, p.CHARACTER_MAXIMUM_LENGTH, 
p.NUMERIC_PRECISION, p.NUMERIC_SCALE
FROM INFORMATION_SCHEMA.PARAMETERS AS p 
JOIN INFORMATION_SCHEMA.ROUTINES AS r ON r.SPECIFIC_NAME = p.SPECIFIC_NAME
WHERE r.ROUTINE_NAME = '{$holder->resourceName}' AND r.ROUTINE_SCHEMA = '{$holder->schemaName}'
MYSQL;

        $params = $this->connection->select($sql);
        foreach ($params as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $name = ltrim(array_get($row, 'PARAMETER_NAME'), '@'); // added on by some drivers, i.e. @name
            $pos = intval(array_get($row, 'ORDINAL_POSITION'));
            $simpleType = static::extractSimpleType(array_get($row, 'DATA_TYPE'));
            if (0 === $pos) {
                $holder->returnType = $simpleType;
            } else {
                $holder->addParameter(new ParameterSchema(
                    [
                        'name'       => $name,
                        'position'   => $pos,
                        'param_type' => array_get($row, 'PARAMETER_MODE'),
                        'type'       => $simpleType,
                        'db_type'    => array_get($row, 'DATA_TYPE'),
                        'length'     => (isset($row['CHARACTER_MAXIMUM_LENGTH']) ? intval(array_get($row,
                            'CHARACTER_MAXIMUM_LENGTH')) : null),
                        'precision'  => (isset($row['NUMERIC_PRECISION']) ? intval(array_get($row, 'NUMERIC_PRECISION'))
                            : null),
                        'scale'      => (isset($row['NUMERIC_SCALE']) ? intval(array_get($row, 'NUMERIC_SCALE'))
                            : null),
                    ]
                ));
            }
        }
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

    public static function getNativeDateTimeFormat($field_info)
    {
        $type = DbSimpleTypes::TYPE_STRING;
        if (is_string($field_info)) {
            $type = $field_info;
        } elseif ($field_info instanceof ColumnSchema) {
            $type = $field_info->type;
        } elseif ($field_info instanceof ParameterSchema) {
            $type = $field_info->type;
        }
        switch (strtolower(strval($type))) {
            case DbSimpleTypes::TYPE_DATE:
                return 'Y-m-d';

            case DbSimpleTypes::TYPE_DATETIME:
            case DbSimpleTypes::TYPE_DATETIME_TZ:
                if (($field_info instanceof ColumnSchema) || ($field_info instanceof ParameterSchema)) {
                    if ('datetime' === $field_info->dbType) {
                        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
                            return 'Y-m-d H:i:s.v'; // v for milliseconds .000 added in php 7
                        } else {
                            return 'Y-m-d H:i:s'; // will blow up if you use microseconds
                        }
                    }
                }

                return 'Y-m-d H:i:s.u';

            case DbSimpleTypes::TYPE_TIME:
            case DbSimpleTypes::TYPE_TIME_TZ:
                return 'H:i:s.u';

            case DbSimpleTypes::TYPE_TIMESTAMP:
            case DbSimpleTypes::TYPE_TIMESTAMP_TZ:
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE:
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE:
                return 'Y-m-d H:i:s.u P';
        }

        return null;
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
        if (!self::useSqlsrv()) {
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
        if (!self::useSqlsrv()) {
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
