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

        return array_merge($fields, ['charset', 'readonly', 'pooling', 'appname', 'encrypt', 'trust_server_certificate']);
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

        return $defaults;
    }
}