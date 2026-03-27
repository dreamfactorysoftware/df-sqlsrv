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

    protected function getConnectionFields()
    {
        $fields = parent::getConnectionFields();

        return array_merge($fields, [
            'charset', 'readonly', 'pooling', 'appname', 'encrypt', 'trust_server_certificate',
            'linked_servers', 'linked_server_schemas',
        ]);
    }

    public static function getDefaultConnectionInfo()
    {
        $defaults = parent::getDefaultConnectionInfo();
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $defaults[] = [
                'name'        => 'charset',
                'label'       => 'Character Set',
                'type'        => 'string',
                'description' => 'The character set to use for this connection, i.e. ' . static::getDefaultCharset()
            ];
        } else {
            $defaults[] = [
                'name'        => 'readonly',
                'label'       => 'Read Only',
                'type'        => 'boolean',
                'description' => 'Defines ApplicationIntent as ReadOnly.'
            ];
            $defaults[] = [
                'name'        => 'pooling',
                'label'       => 'Enable Connection Pooling',
                'type'        => 'boolean',
                'description' => 'Specifies whether the connection is assigned from a connection pool.'
            ];
            $defaults[] = [
                'name'        => 'encrypt',
                'label'       => 'Encrypted communication',
                'type'        => 'boolean',
                'description' => 'Specifies whether the communication with SQL Server is encrypted or unencrypted.'
            ];
            $defaults[] = [
                'name'        => 'trust_server_certificate',
                'label'       => 'Trust server certificate',
                'type'        => 'boolean',
                'description' => 'Specifies whether the client should trust or reject a self-signed server certificate.',
                'default'     => true
            ];
        }
        $defaults[] = [
            'name'        => 'appname',
            'label'       => 'Application Name',
            'type'        => 'string',
            'description' => 'The application name used in tracing.'
        ];
        $defaults[] = [
            'name'        => 'linked_servers',
            'label'       => 'Enable Linked Servers',
            'type'        => 'boolean',
            'default'     => false,
            'description' => 'When enabled, tables from SQL Server linked servers will be discoverable via the API. '
                . 'Use Linked Server Schemas to filter which schemas are exposed.'
        ];
        $defaults[] = [
            'name'        => 'linked_server_schemas',
            'label'       => 'Linked Server Schemas',
            'type'        => 'string',
            'description' => 'Comma-separated list of SERVER.SCHEMA pairs to expose (e.g. "FAM28.COMPANY_28_RPT,FAM63.COMPANY_63_RPT"). '
                . 'If empty and linked servers are enabled, all linked server tables will be listed (can be slow).'
        ];

        return $defaults;
    }
}