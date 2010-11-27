<?php
/**
* File containing the contentwaittimeout.php script
*
* @copyright Copyright (C) 1999-2010 eZ Systems AS. All rights reserved.
* @license http://ez.no/licenses/gnu_gpl GNU GPL v2
* @version //autogentag//
* @package
*/

/**
* This script starts parallel publishing processes in order to trigger lock wait timeouts
* Launch it using $./bin/php/ezexec.phhp contentwaittimeout.php
*
* To customize the class, parent node or concurrency level, modify the 3 variables below.
* @package tests
*/

$parentNode = 755;
$contentClass = 'article';
$concurrencyLevel = 20;

$currentJobs = array();
$signalQueue = array();

// Create the containing folder... NOT
// if mt_rand is initialized (it is in eZContentObject::create), and the process is forked, each fork will get the SAME
// "random" value when calling mt_rand again
/*$container = new ezpObject( 'folder', $parentNode );
$container->name = "Bench on $contentClass [concurrency: $concurrencyLevel]";
$container->publish();

eZDB::instance()->close();
unset( $GLOBALS['eZDBGlobalInstance'] );
sleep( 5 );

$parentNode = $container->attribute( 'main_node_id' );
echo "Main node ID: $parentNode\n";
*/
for( $i = 0; $i < $concurrencyLevel; $i++ )
{
    $pid = pcntl_fork();
    if ( $pid == - 1 )
    {
        // Problem launching the job
        error_log( 'Could not launch new job, exiting' );
    }
    else if ( $pid > 1 )
    {
        $currentJobs[] = $pid;
    }
    else
    {
        // Forked child, do your deeds....
        $exitStatus = 0; //Error code if you need to or whatever
        $myPid = getmypid();

        // No need if the DB ain't initialized before forking
        /*$db = eZDB::instance( false, false, true );
        eZDB::setInstance( $db );
        echo "#{$myPid}: DB Connection: " . ( $db->IsConnected() ? ' connected' : 'not connected' ) . "\n";*/

        // suppress error output
        fclose( STDERR );

        $object = new ezpObject( $contentClass, $parentNode );
        $object->title = "Wait Timeout Test, pid {$myPid}\n";
        $object->publish();

        eZExecution::cleanExit();
    }
}

echo "Main process: waiting for children...\n";
$errors = 0;
while ( !empty( $currentJobs ) )
{
    foreach( $currentJobs as $index => $pid )
    {
        if( pcntl_waitpid( $pid, $exitStatus, WNOHANG ) > 0 )
        {
            $exitCode = pcntl_wexitstatus( $exitStatus );
            if ( $exitCode != 0 )
            {
                $errors++;
                echo "process #$pid exited with code $exitCode\n";
            }
            else
            {
                echo "process #$pid exited successfully\n";
            }
            unset( $currentJobs[$index] );
        }
        usleep( 100 );
    }
}
echo "Done waiting.\n\nResult: $errors errors out of $concurrencyLevel publishing operations\n";
echo "Failures: " . ( round( $errors / $concurrencyLevel * 100, 0 ) ). "%\n";
?>