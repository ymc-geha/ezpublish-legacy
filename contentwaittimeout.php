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

$parentNode = 70;
$contentClass = 'article';
$concurrencyLevel = 20;

$currentJobs = array();
$signalQueue = array();

for( $i = 0; $i < $concurrencyLevel; $i++ )
{
    $pid = pcntl_fork();
    if ( $pid == - 1 )
    {
        // Problem launching the job
        error_log( 'Could not launch new job, exiting' );
        return false;
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
        // echo "#{$myPid}: publishing object\n";

        // suppress error output
        fclose( STDERR );

        $object = new ezpObject( $contentClass, $parentNode );
        $object->title = "Wait Timeout Test, pid {$myPid}\n";
        @$object->publish();
        // echo "#{$myPid}: done\n";

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
?>