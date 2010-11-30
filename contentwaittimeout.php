#!/usr/bin/env php
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


require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => "eZ Publish Parallel publishing benchmark",
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( "[content-class:][concurrency-level:][parent-node:][generate-content]",
"",
array( 'content-class'     => "Identifier of the content class used for testing.",
       'concurrency-level' => "Parallel processes to use",
       'generate-content' => "Wether content should  be generated or not (not fully supported yet)",
       'parent-node'       => "Container content should be created in" ) );
$sys = eZSys::instance();

$script->initialize();

$optParentNode = 2;
$optContentClass = 'article';
$optConcurrencyLevel = 20;
$optGenerateContent = false;

if ( $options['content-class'] )
    $optContentClass = $options['content-class'];
if ( $options['concurrency-level'] )
    $optConcurrencyLevel = $options['concurrency-level'];
if ( $options['parent-node'] )
    $optParentNode = $options['parent-node'];
if ( $options['generate-content'] )
    $optGenerateContent = true;

$cli->output( "Options:" );
$cli->output( " * Concurrency level: $optConcurrencyLevel" );
$cli->output( " * Content class: $optContentClass" );
$cli->output( " * Generate content: $optGenerateContent" );
$cli->output();

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
for( $i = 0; $i < $optConcurrencyLevel; $i++ )
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

        $object = new ezpObject( $optContentClass, $optParentNode );
        $object->title = "Wait Timeout Test, pid {$myPid}\n";
        if ( $optGenerateContent === true )
            $object->body = file_get_contents( 'xmltextsource.txt' );
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
echo "Done waiting.\n\nResult: $errors errors out of $optConcurrencyLevel publishing operations\n";
echo "Failures: " . ( round( $errors / $optConcurrencyLevel * 100, 0 ) ). "%\n";
?>