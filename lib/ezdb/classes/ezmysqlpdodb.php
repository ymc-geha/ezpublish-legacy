<?php
/**
 * File containing the eZMySQLiDB class.
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 * @package lib
 */

/*!
  \class eZMySQLiDB eZMySQLiDB.php
  \ingroup eZDB
  \brief The eZMySQLiDB class provides MySQL implementation of the database interface.

  eZMySQLiDB is the MySQL implementation of eZDB.
  \sa eZDB
*/

class eZMySQLPDODB extends eZDBInterface
{
    const RELATION_FOREIGN_KEY = 5;
    const RELATION_FOREIGN_KEY_BIT = 32;

    /*!
      Create a new eZMySQLiDB object and connects to the database backend.
    */
    function __construct( $parameters )
    {
        $this->eZDBInterface( $parameters );

        eZDebug::createAccumulatorGroup( 'mysqlpdo_total', 'Mysql Total' );

        /// Connect to master server
        if ( !$this->DBWriteConnection )
        {
            $connection = $this->connect( $this->Server, $this->DB, $this->User, $this->Password, $this->SocketPath, $this->Charset, $this->Port );
            if ( $this->IsConnected )
            {
                $this->DBWriteConnection = $connection;
            }
        }

        // Connect to slave
        if ( !$this->DBConnection )
        {
            if ( $this->UseSlaveServer === true )
            {
                $connection = $this->connect( $this->SlaveServer, $this->SlaveDB, $this->SlaveUser, $this->SlavePassword, $this->SocketPath, $this->Charset, $this->SlavePort );
            }
            else
            {
                $connection = $this->DBWriteConnection;
            }

            if ( $connection && $this->DBWriteConnection )
            {
                $this->DBConnection = $connection;
                $this->IsConnected = true;
            }
        }

        // Initialize TempTableList
        $this->TempTableList = array();
    }

    /*!
     \private
     Opens a new connection to a MySQL database and returns the connection
    */
    function connect( $server, $db, $user, $password, $socketPath, $charset = null, $port = false )
    {
        $connection = false;

        $oldHandling = eZDebug::setHandleType( eZDebug::HANDLE_EXCEPTION );
        eZDebug::accumulatorStart( 'mysqlpdo_connection', 'mysqlpdo_total', 'Database connection' );
        try {
            $connection = new PDO("mysql:host=$server;dbname=$db", $user, $password);
        } catch( ErrorException $e ) {}
        eZDebug::accumulatorStop( 'mysqlpdo_connection' );
        eZDebug::setHandleType( $oldHandling );

        $maxAttempts = $this->connectRetryCount();
        $waitTime = $this->connectRetryWaitTime();
        $numAttempts = 1;
        while ( !$connection && $numAttempts <= $maxAttempts )
        {
            sleep( $waitTime );

            $oldHandling = eZDebug::setHandleType( eZDebug::HANDLE_EXCEPTION );
            eZDebug::accumulatorStart( 'mysqlpdo_connection', 'mysqlpdo_total', 'Database connection' );
            try {
                $connection = new PDO("mysql:host=$server;dbname=$db", $user, $password);
            } catch( ErrorException $e ) {}
            eZDebug::accumulatorStop( 'mysqlpdo_connection' );
            eZDebug::setHandleType( $oldHandling );

            $numAttempts++;
        }
        $this->setError();

        $this->IsConnected = true;

        if ( !$connection )
        {
            eZDebug::writeError( "Connection error: Couldn't connect to database server. Please try again later or inform the system administrator.\n{$this->ErrorMessage}", __CLASS__ );
            $this->IsConnected = false;
            throw new eZDBNoConnectionException( $server, $this->ErrorMessage, $this->ErrorNumber );
        }

        return $connection;
    }

    function databaseName()
    {
        return 'mysql';
    }

    function bindingType( )
    {
        return eZDBInterface::BINDING_NO;
    }

    function bindVariable( $value, $fieldDef = false )
    {
        return $value;
    }

    function checkCharset( $charset, &$currentCharset )
    {
        return true;
    }

    function query( $sql, $server = false )
    {
        if ( $this->IsConnected )
        {
            eZDebug::accumulatorStart( 'mysqli_query', 'mysqli_total', 'Mysqli_queries' );

            if ( $this->InputTextCodec )
            {
                eZDebug::accumulatorStart( 'mysqli_conversion', 'mysqli_total', 'String conversion in mysqli' );
                $sql = $this->InputTextCodec->convertString( $sql );
                eZDebug::accumulatorStop( 'mysqli_conversion' );
            }

            if ( $this->OutputSQL )
            {
                $this->startTimer();
            }

            $sql = trim( $sql );

            // Check if we need to use the master or slave server by default
            if ( $server === false )
            {
                $server = strncasecmp( $sql, 'select', 6 ) === 0 && $this->TransactionCounter == 0 ?
                    eZDBInterface::SERVER_SLAVE : eZDBInterface::SERVER_MASTER;
            }

            $connection = ( $server == eZDBInterface::SERVER_SLAVE ) ? $this->DBConnection : $this->DBWriteConnection;

            $analysisText = false;

            $statement = $connection->prepare($sql);
            $statement->execute();

            $result = $statement;

            if ( $this->RecordError and !$result )
                $this->setError();

            eZDebug::accumulatorStop( 'mysqli_query' );
            if ( $result )
            {
                return $result;
            }
            else
            {
                $errorMessage = 'Query error (' . mysqli_errno( $connection ) . '): ' . mysqli_error( $connection ) . '. Query: ' . $sql;
                eZDebug::writeError( $errorMessage, __CLASS__  );
                $oldRecordError = $this->RecordError;
                // Turn off error handling while we unlock
                $this->RecordError = false;
                mysqli_query( $connection, 'UNLOCK TABLES' );
                $this->RecordError = $oldRecordError;

                $this->reportError();

                // This is to behave the same way as other RDBMS PHP API as PostgreSQL
                // functions which throws an error with a failing request.
                if ( $this->errorHandling == eZDB::ERROR_HANDLING_STANDARD )
                {
                    trigger_error( "mysqli_query(): $errorMessage", E_USER_ERROR );
                }
                else
                {
                    throw new eZDBException( $this->ErrorMessage, $this->ErrorNumber );
                }

                return false;
            }
        }
        else
        {
            eZDebug::writeError( "Trying to do a query without being connected to a database!", __CLASS__ );
        }


    }

    function arrayQuery( $sql, $params = array(), $server = false )
    {
        $retArray = array();
        if ( $this->IsConnected )
        {
            $limit = false;
            $offset = 0;
            $column = false;
            // check for array parameters
            if ( is_array( $params ) )
            {
                if ( isset( $params["limit"] ) and is_numeric( $params["limit"] ) )
                    $limit = $params["limit"];

                if ( isset( $params["offset"] ) and is_numeric( $params["offset"] ) )
                    $offset = $params["offset"];

                if ( isset( $params["column"] ) and ( is_numeric( $params["column"] ) or is_string( $params["column"] ) ) )
                    $column = $params["column"];
            }

            if ( $limit !== false and is_numeric( $limit ) )
            {
                $sql .= "\nLIMIT $offset, $limit ";
            }
            else if ( $offset !== false and is_numeric( $offset ) and $offset > 0 )
            {
                $sql .= "\nLIMIT $offset, 18446744073709551615"; // 2^64-1
            }
            $result = $this->query( $sql, $server );

            if ( $result == false )
            {
                $this->reportQuery( __CLASS__, $sql, false, false );
                return false;
            }

            $numRows = $result->rowCount();
            if ( $numRows > 0 )
            {
                if ( !is_string( $column ) )
                {
                    eZDebug::accumulatorStart( 'mysqlpdo_loop', 'mysqli_total', 'Looping result' );
                    for ( $i=0; $i < $numRows; $i++ )
                    {
                        if ( $this->InputTextCodec )
                        {
                            $tmpRow = $result->fetch(PDO::FETCH_ASSOC);
                            $convRow = array();
                            foreach( $tmpRow as $key => $row )
                            {
                                eZDebug::accumulatorStart( 'mysqlpdo_conversion', 'mysqlpdo_total', 'String conversion in mysqli' );
                                $convRow[$key] = $this->OutputTextCodec->convertString( $row );
                                eZDebug::accumulatorStop( 'mysqlpdo_conversion' );
                            }
                            $retArray[$i + $offset] = $convRow;
                        }
                        else
                            $retArray[$i + $offset] = $result->fetch(PDO::FETCH_ASSOC);
                    }
                    eZDebug::accumulatorStop( 'mysqlpdo_loop' );

                }
                else
                {
                    eZDebug::accumulatorStart( 'mysqlpdo_loop', 'mysqlpdo_total', 'Looping result' );
                    for ( $i=0; $i < $numRows; $i++ )
                    {
                        $tmp_row = $result->fetch(PDO::FETCH_ASSOC);
                        if ( $this->InputTextCodec )
                        {
                            eZDebug::accumulatorStart( 'mysqli_conversion', 'mysqli_total', 'String conversion in mysqli' );
                            $retArray[$i + $offset] = $this->OutputTextCodec->convertString( $tmp_row[$column] );
                            eZDebug::accumulatorStop( 'mysqli_conversion' );
                        }
                        else
                            $retArray[$i + $offset] =& $tmp_row[$column];
                    }
                    eZDebug::accumulatorStop( 'mysqli_loop' );
                }
            }
        }
        return $retArray;
    }

    function subString( $string, $from, $len = null )
    {
        if ( $len == null )
        {
            return " substring( $string from $from ) ";
        }else
        {
            return " substring( $string from $from for $len ) ";
        }
    }

    function concatString( $strings = array() )
    {
        $str = implode( "," , $strings );
        return " concat( $str  ) ";
    }

    function md5( $str )
    {
        return " MD5( $str ) ";
    }

    function bitAnd( $arg1, $arg2 )
    {
        return 'cast(' . $arg1 . ' & ' . $arg2 . ' AS SIGNED ) ';
    }

    function bitOr( $arg1, $arg2 )
    {
        return 'cast( ' . $arg1 . ' | ' . $arg2 . ' AS SIGNED ) ';
    }

    function supportedRelationTypeMask()
    {
        return eZDBInterface::RELATION_TABLE_BIT | self::RELATION_FOREIGN_KEY_BIT;
    }

    function supportedRelationTypes()
    {
        return array( self::RELATION_FOREIGN_KEY, eZDBInterface::RELATION_TABLE );
    }

    function relationCounts( $relationMask )
    {
        if ( $relationMask & eZDBInterface::RELATION_TABLE_BIT )
            return $this->relationCount();
        else
            return 0;
    }

    function relationCount( $relationType = eZDBInterface::RELATION_TABLE )
    {
        if ( !in_array( $relationType, $this->supportedRelationTypes() ) )
        {
            eZDebug::writeError( "Unsupported relation type '$relationType'", __METHOD__ );
            return false;
        }
        $count = false;
        if ( $this->IsConnected )
        {
            switch ( $relationType )
            {
                case eZDBInterface::RELATION_TABLE:
                {
                    $query = 'SHOW TABLES from `' . $this->DB .'`';
                    $statement = $this->DBConnection->prepare($query);
                    $statement->execute();
                    $result = $statement;
                    $this->reportQuery( __CLASS__, $query, false, false );
                    $count = $result->rowCount();
                    $result->closeCursor();
                } break;

                case self::RELATION_FOREIGN_KEY:
                {
                    $count = count( $this->relationList( self::RELATION_FOREIGN_KEY ) );
                } break;
            }
        }
        return $count;
    }

    function relationList( $relationType = eZDBInterface::RELATION_TABLE )
    {
        if ( !in_array( $relationType, $this->supportedRelationTypes() ) )
        {
            eZDebug::writeError( "Unsupported relation type '$relationType'", __METHOD__ );
            return false;
        }
        if ( $this->IsConnected )
        {
            switch ( $relationType )
            {
                case eZDBInterface::RELATION_TABLE:
                {
                    $tables = array();
                    $query = 'SHOW TABLES from `' . $this->DB .'`';
                    $statement = $this->DBConnection->prepare($query);
                    $statement->execute();
                    $result = $statement;
                    $this->reportQuery( __CLASS__, $query, false, false );
                    while( $row =$result->fetch())
                    {
                        $tables[] = $row[0];
                    }
                    $result->closeCursor();
                    return $tables;
                } break;

                case self::RELATION_FOREIGN_KEY:
                {
                    /**
                     * Ideally, we would have queried information_schema.KEY_COLUMN_USAGE
                     * However, a known bug causes queries on this table to potentially be VERY slow (http://bugs.mysql.com/bug.php?id=19588)
                     *
                     * The query would look like this:
                     * SELECT table_name AS from_table, column_name AS from_column, referenced_table_name AS to_table,
                     *        referenced_column_name AS to_column
                     * FROM information_schema.KEY_COLUMN_USAGE
                     * WHERE REFERENCED_TABLE_SCHEMA = '{$this->DB}'
                     *   AND REFERENCED_TABLE_NAME is not null;
                     *
                     * Result as of MySQL 5.1.48 / August 2010:
                     *
                     * +---------------+-------------+----------+-----------+
                     * | from_table    | from_column | to_table | to_column |
                     * +---------------+-------------+----------+-----------+
                     * | ezdbfile_data | name_hash   | ezdbfile | name_hash |
                     * +---------------+-------------+----------+-----------+
                     * 1 row in set (12.56 sec)
                     *
                     * The only way out right now is to parse SHOW CREATE TABLE for each table and extract CONSTRAINT lines
                     */

                    $foreignKeys = array();
                    foreach( $this->relationList( eZDBInterface::RELATION_TABLE ) as $table )
                    {
                        $query = "SHOW CREATE TABLE $table";
                        $statement = $this->DBConnection->prepare($query);
                        $statement->execute();
                        $result = $statement;
                        $this->reportQuery( __CLASS__, $query, false, false );
                        if ( $result->rowCount() === 1 )
                        {
                            $row = $result->fetch();
                            if ( strpos( $row[1], "CONSTRAINT" ) !== false )
                            {
                                if ( preg_match_all( '#CONSTRAINT [`"]([^`"]+)[`"] FOREIGN KEY \([`"].*[`"]\) REFERENCES [`"]([^`"]+)[`"] \([`"].*[`"]\)#', $row[1], $matches, PREG_PATTERN_ORDER ) )
                                {
                                    // $foreignKeys[] = array( 'table' => $table, 'keys' => $matches[1] );
                                    foreach( $matches[1] as $fkMatch )
                                    {
                                        $foreignKeys[] = array( 'table' => $table, 'fk' => $fkMatch );
                                    }
                                }
                            }
                        }
                    }
                    return $foreignKeys;
                }
            }
        }
    }

    function eZTableList( $server = eZDBInterface::SERVER_MASTER )
    {
        $tables = array();
        if ( $this->IsConnected )
        {
            if ( $this->UseSlaveServer && $server == eZDBInterface::SERVER_SLAVE )
            {
                $connection = $this->DBConnection;
                $db = $this->SlaveDB;
            }
            else
            {
                $connection = $this->DBWriteConnection;
                $db = $this->DB;
            }

            $query = 'SHOW TABLES from `' . $db .'`';
            $statement = $this->DBConnection->prepare($query);
            $statement->execute();
            $result = $statement;
            $this->reportQuery( __CLASS__, $query, false, false );
            while( $row = $result->fetch() )
            {
                $tableName = $row[0];
                if ( substr( $tableName, 0, 2 ) == 'ez' )
                {
                    $tables[$tableName] = eZDBInterface::RELATION_TABLE;
                }
            }
            $result->closeCursor();
        }
        return $tables;
    }

    function relationMatchRegexp( $relationType )
    {
        return "#^ez#";
    }

    function removeRelation( $relationName, $relationType )
    {
        $relationTypeName = $this->relationName( $relationType );
        if ( !$relationTypeName )
        {
            eZDebug::writeError( "Unknown relation type '$relationType'", __METHOD__ );
            return false;
        }

        if ( $this->IsConnected )
        {
            switch ( $relationType )
            {
                case self::RELATION_FOREIGN_KEY:
                {
                    $sql = "ALTER TABLE {$relationName['table']} DROP FOREIGN KEY {$relationName['fk']}";
                    $this->query( $sql );
                    return true;
                } break;

                default:
                {
                    $sql = "DROP $relationTypeName $relationName";
                    return $this->query( $sql );
                }
            }
        }
        return false;
    }

    /**
     * Local eZDBInterface::relationName() override to support the foreign keys type relation
     * @param $relationType
     * @return string|false
     */
    public function relationName( $relationType )
    {
        if ( $relationType == self::RELATION_FOREIGN_KEY )
            return 'FOREIGN_KEY';
        else
            return parent::relationName( $relationType );
    }

    function lock( $table )
    {
        if ( $this->IsConnected )
        {
            if ( is_array( $table ) )
            {
                $lockQuery = "LOCK TABLES";
                $first = true;
                foreach( array_keys( $table ) as $tableKey )
                {
                    if ( $first == true )
                        $first = false;
                    else
                        $lockQuery .= ",";
                    $lockQuery .= " " . $table[$tableKey]['table'] . " WRITE";
                }
                $this->query( $lockQuery );
            }
            else
            {
                $this->query( "LOCK TABLES $table WRITE" );
            }
        }
    }

    function unlock()
    {
        if ( $this->IsConnected )
        {
            $this->query( "UNLOCK TABLES" );
        }
    }

    /*!
     The query to start the transaction.
    */
    function beginQuery()
    {
        return $this->query("BEGIN WORK");
    }

    /*!
     The query to commit the transaction.
    */
    function commitQuery()
    {
        return $this->query( "COMMIT" );
    }

    /*!
     The query to cancel the transaction.
    */
    function rollbackQuery()
    {
        return mysqli_query( $this->DBWriteConnection, "ROLLBACK" );
    }

    /**
     * Returns the last serial ID generated with an auto increment field.
     *
     * @param string|bool $table
     * @param string|bool $column
     * @return int|bool The most recent value for the sequence
     */
    function lastSerialID( $table = false, $column = false )
    {
        if ( $this->IsConnected )
        {
            $id = $this->DBWriteConnection->lastInsertId();
            return $id;
        }

        return false;
    }

    function escapeString( $str )
    {
        if ( $this->IsConnected )
        {
            $result = $this->DBConnection->quote($str);
            $result = substr($result,1, -1);
            return $result;
        }
        else
        {
            eZDebug::writeDebug( 'escapeString called before connection is made', __METHOD__ );
            return $str;
        }
    }

    function close()
    {
        if ( $this->IsConnected )
        {
            if ( $this->UseSlaveServer === true )
                $this->DBConnection = null;
            $this->DBWriteConnection = null;
        }
    }

    function createDatabase( $dbName )
    {
        if ( $this->IsConnected )
        {
            $this->query( "CREATE DATABASE $dbName" );
            $this->setError();
        }
    }

    function removeDatabase( $dbName )
    {
        if ( $this->IsConnected )
        {
            $this->query( "DROP DATABASE $dbName" );
            $this->setError();
        }
    }

    /**
     * Sets the internal error messages & number
     * @param MySQLi $connection database connection handle, overrides the current one if given
     */
    function setError( $connection = false )
    {
        if ( $this->IsConnected )
        {
            if ( $connection === false )
                $connection = $this->DBConnection;

            $this->ErrorMessage = $connection->errorInfo();
            $this->ErrorNumber = $connection->errorCode();
        }
    }

    function availableDatabases()
    {
        $databaseArray = $this->DBConnection->query( 'SHOW DATABASES' );

        if ( $this->errorNumber() != 0 )
        {
            return null;
        }

        $databases = array();

        $numRows = $databaseArray->rowCount();
        if ( count( $numRows ) == 0 )
        {
            return false;
        }

        while ( $row = $databaseArray->fetch() )
        {
            // we don't allow "mysql" or "information_schema" database to be shown anywhere
            $curDB = $row[0];
            if ( strcasecmp( $curDB, 'mysql' ) != 0 && strcasecmp( $curDB, 'information_schema' ) != 0 )
            {
                $databases[] = $curDB;
            }
        }
        return $databases;
    }

    function databaseServerVersion()
    {
        if ( $this->IsConnected )
        {
            $versionInfo = $this->DBConnection->getAttribute(PDO::ATTR_SERVER_VERSION);

            $versionArray = explode( '.', $versionInfo );

            return array( 'string' => $versionInfo,
                          'values' => $versionArray );
        }

        return false;
    }

    function databaseClientVersion()
    {
        $versionInfo = $this->DBConnection->getAttribute(PDO::ATTR_CLIENT_VERSION);

        $versionArray = explode( '.', $versionInfo );

        return array( 'string' => $versionInfo,
                      'values' => $versionArray );
    }

    function isCharsetSupported( $charset )
    {
        return true;
    }

    function supportsDefaultValuesInsertion()
    {
        return false;
    }

    function dropTempTable( $dropTableQuery = '', $server = self::SERVER_SLAVE )
    {
        $dropTableQuery = str_ireplace( 'DROP TABLE', 'DROP TEMPORARY TABLE', $dropTableQuery );
        parent::dropTempTable( $dropTableQuery, $server );
    }

    protected $TempTableList;

}
