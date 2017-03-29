<?php
namespace DreamFactory\Core\SqlSrv\Models;

use DreamFactory\Core\SqlDb\Models\SqlDbConfig;

/**
 * SqlSrvDbConfig
 *
 */
class SqlSrvDbConfig extends SqlDbConfig
{
    public static function getDriverName()
    {
        return 'sqlsrv';
    }

    public static function getDefaultPort()
    {
        return 1433;
    }

    public static function getSchema()
    {
        $schema = parent::getSchema();
        $extras = [
            'charset' => [
                'name'        => 'charset',
                'label'       => 'Character Set',
                'type'        => 'string',
                'description' => 'The character set to use for this connection, i.e. ' . static::getDefaultCharset()
            ],
            'readonly' => [
                'name'        => 'readonly',
                'label'       => 'Read Only',
                'type'        => 'boolean',
                'description' => 'Defines ApplicationIntent as ReadOnly.'
            ],
            'pooling' => [
                'name'        => 'pooling',
                'label'       => 'Enable Connection Pooling',
                'type'        => 'boolean',
                'description' => 'Specifies whether the connection is assigned from a connection pool.'
            ],
            'appname' => [
                'name'        => 'appname',
                'label'       => 'Application Name',
                'type'        => 'string',
                'description' => 'The application name used in tracing.'
            ]
        ];

        $pos = array_search('options', array_keys($schema));
        $front = array_slice($schema, 0, $pos, true);
        $end = array_slice($schema, $pos, null, true);

        return array_merge($front, $extras, $end);
    }
}