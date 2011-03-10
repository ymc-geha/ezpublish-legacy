<?php
/**
 * File containing the eZContentOperationCollectionRegression class
 *
 * @copyright Copyright (C) 1999-2010 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/gnu_gpl GNU GPLv2
 * @package tests
 */

class eZContentOperationCollectionRegression extends ezpDatabaseTestCase
{
    protected $backupGlobals = false;

    public function __construct()
    {
        parent::__construct();
        $this->setName( "eZContentOperationCollection Regression Tests" );
    }

    /**
     * Helper method to aid the development and test and verification of results.
     *
     * The method will output information about the inputted objects and nodes
     *
     * @param mixed $testObjects (array=>ezpObject)
     * @return void
     */
    protected function debugBasicNodeInfo( $testObjects )
    {
        echo "\n";

        foreach ( $testObjects as $obj )
        {
            if ( $obj instanceof ezpObject )
            {
                printf( "%21s - [Node: %s] | [Object: %s]\n", $obj->name, $obj->mainNode->node_id, $obj->id );
            }
            else if ( $obj instanceof eZContentObjectTreeNode )
            {
                printf( "%21s - [Node: %s] | [Object: %s]\n", $obj->attribute( 'name' ), $obj->attribute( 'node_id' ), $obj->attribute( 'contentobject_id' ) );
            }
        }

        echo "\n";
    }
}
?>
