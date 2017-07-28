<?php

namespace DreamFactory\Core\SqlSrv\Database;

use Closure;
use DreamFactory\Core\SqlSrv\Database\Query\Grammars\SqlServerGrammar;
use Exception;
use Illuminate\Database\SqlServerConnection as LaravelSqlServerConnection;
use Lsw\DoctrinePdoDblib\Doctrine\DBAL\Driver\PDODblib\Driver as DblibDriver;
use Throwable;

class SqlServerConnection extends LaravelSqlServerConnection
{
    /**
     * @inheritdoc
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        if (in_array('dblib', \PDO::getAvailableDrivers())) {
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

        parent::__construct($pdo, $database, $tablePrefix, $config);
    }

    /**
     * @inheritdoc
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        // We override the default usage of dblib, for the sqlsrv driver, now available on Linux as well.
        if (in_array('sqlsrv', \PDO::getAvailableDrivers())) {
            return parent::transaction($callback);
        }

        for ($a = 1; $a <= $attempts; $a++) {
            $this->getPdo()->exec('BEGIN TRAN');

            // We'll simply execute the given callback within a try / catch block
            // and if we catch any exception we can rollback the transaction
            // so that none of the changes are persisted to the database.
            try {
                $result = $callback($this);

                $this->getPdo()->exec('COMMIT TRAN');
            }

            // If we catch an exception, we will roll back so nothing gets messed
            // up in the database. Then we'll re-throw the exception so it can
            // be handled how the developer sees fit for their applications.
            catch (Exception $e) {
                $this->getPdo()->exec('ROLLBACK TRAN');

                throw $e;
            } catch (Throwable $e) {
                $this->getPdo()->exec('ROLLBACK TRAN');

                throw $e;
            }

            return $result;
        }
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new SqlServerGrammar);
    }

    /**
     * @inheritdoc
     */
    protected function getDoctrineDriver()
    {
        // We override the default usage of dblib, for the sqlsrv driver, now available on Linux as well.
        if (!in_array('sqlsrv', \PDO::getAvailableDrivers())) {
            return new DblibDriver;
        }

        return parent::getDoctrineDriver();
    }
}
