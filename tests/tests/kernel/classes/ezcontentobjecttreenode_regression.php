<?php
/**
 * File containing the eZContentObjectTreeNodeRegression class
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 * @package tests
 */

class eZContentObjectTreeNodeRegression extends ezpDatabaseTestCase
{
    protected $backupGlobals = false;

    public function __construct()
    {
        parent::__construct();
        $this->setName( "eZContentObjectTreeNode Regression Tests" );
    }

    /**
     * Test for regression #13497:
     * attribute operator throws a PHP fatal error on a node without parent in a displayable language
     *
     * Situation:
     *  - siteaccess with one language (fre-FR) and ShowUntranslatedObjects disabled
     *  - parent content node in another language (eng-GB) with always available disabled
     *  - content node in the siteaccess' language (fre-FR)
     *  - fetch this fre-FR node from anywhere, and call attribute() on it
     *
     * Result:
     *  - Fatal error: Call to a member function attribute() on a non-object in
     *    kernel/classes/ezcontentobjecttreenode.php on line 4225
     *
     * Explanation: the error actually comes from the can_remove_location attribute
     */
    public function testIssue13497()
    {
        $bkpLanguages = eZContentLanguage::prioritizedLanguageCodes();

        // Create a folder in english only
        $folder = new ezpObject( "folder", 2, 14, 1, 'eng-GB' );
        $folder->setAlwaysAvailableLanguageID( false );
        $folder->name = "Parent for " . __FUNCTION__;
        $folder->publish();

        $locale = eZLocale::instance( 'fre-FR' );
        $translation = eZContentLanguage::addLanguage( $locale->localeCode(), $locale->internationalLanguageName() );

        // Create an article in french only, as a subitem of the previously created folder
        $article = new ezpObject( "article", $folder->attribute( 'main_node_id' ), 14, 1, 'fre-FR' );
        $article->title = "Object for " . __FUNCTION__;
        $article->short_description = "Description of test for " . __FUNCTION__;
        $article->publish();

        $articleNodeID = $article->attribute( 'main_node_id' );

        // INi changes: set language to french only, untranslatedobjects disabled
        ezpINIHelper::setINISetting( 'site.ini', 'RegionalSettings', 'ContentObjectLocale', 'fre-FR' );
        // ezpINIHelper::setINISetting( 'site.ini', 'RegionalSettings', 'SiteLanguageList', array( 'fre-FR' ) );
        eZContentLanguage::setPrioritizedLanguages( array( 'fre-FR' ) );
        ezpINIHelper::setINISetting( 'site.ini', 'RegionalSettings', 'ShowUntranslatedObjects', 'disabled' );
        eZContentLanguage::expireCache();

        // This should crash
        eZContentObjectTreeNode::fetch( $articleNodeID )->attribute( 'can_remove_location' );

        ezpINIHelper::restoreINISettings();

        // re-expire cache for further tests
        eZContentLanguage::setPrioritizedLanguages( $bkpLanguages );
        $article->remove();
        $translation->removeThis();
        eZContentLanguage::expireCache();

        ezpINIHelper::restoreINISettings();
    }

    /**
     * Test for regression #15211
     *
     * The issue was reported as happening when a fetch_alias was called without
     * a second parameter. It turns out that this was just wrong, but led to the
     * following: if eZContentObjectTreeNode::createAttributeFilterSQLStrings
     * is called with the first parameter ($attributeFilter) is a string, a fatal
     * error "Cannot unset string offsets" is thrown.
     *
     * Test: Call this function with a string as the first parameter. Without the
     * fix, a fatal error occurs, while an empty filter is returned once fixed.
     */
    public function testIssue15211()
    {
        $attributeFilter = "somestring";

        // Without the fix, this is a fatal error
        $filterSQL = eZContentObjectTreeNode::createAttributeFilterSQLStrings(
            $attributeFilter );

        $this->assertInternalType( 'array', $filterSQL );
        $this->assertArrayHasKey( 'from', $filterSQL );
        $this->assertArrayHasKey( 'where', $filterSQL );
    }

    /**
     * Test for issue #15062:
     * StateGroup Policy limitation SQL can break the 30 characters oracle
     * limitation for identifiers
     */
    public function testIssue15062()
    {
        $policyLimitationArray = array(
            array(
                'StateGroup_abcdefghijkl' => array( 1 ),
                'StateGroup_abcdefghiklmnop' => array( 1, 2, 3 ),
            ),
            array(
                'StateGroup_abcdefghijkl' => array( 2, 3 ),
            )
        );

        $sqlArray = eZContentObjectTreeNode::createPermissionCheckingSQL( $policyLimitationArray );

        $this->assertInternalType( 'array', $sqlArray );
        $this->assertArrayHasKey( 'from', $sqlArray );

        // we need to check that each identifier in the 'from' of this array
        // doesn't exceed 30 characters
        $matches = explode( ', ', str_replace( "\r\n", '', $sqlArray['from'] ) );
        foreach( $matches as $match )
        {
            if ( $match == '' )
                continue;
            list( $table, $alias ) = explode( ' ', $match );
            $this->assertTrue( strlen( $alias ) <= 30 , "Identifier {$alias} exceeds the 30 characters limit" );
        }
    }

    /**
     * Regression test for issue #15561:
     * eZContentObjectTreeNode::fetch() SQL error when conditions argument is given
     */
    public function testIssue15561()
    {
        // Create a new node so that we have a known object with a known ID
        $object = new ezpObject( 'article', 2 );
        $object->title = __FUNCTION__;
        $object->publish();
        $nodeID = $object->attribute( 'main_node_id' );

        $node = eZContentObjectTreeNode::fetch( $nodeID, false, true,
            array( 'contentobject_version' => 1 ) );

        $this->assertInstanceOf( 'eZContentObjectTreeNode', $node );
    }


    /**
     * Regression test for issue #16737
     * 1) Test executing the sql and verify that it doesn't have database error.
     * 2) Test the sorting in class_name, class_name with contentobject_id
     * The test should pass in mysql, postgresql and oracle
     */
    public function testIssue16737()
    {


        //test generated result of createSortingSQLStrings
        $sortList = array( array( 'class_name', true ) );
        $result = eZContentObjectTreeNode::createSortingSQLStrings( $sortList );
        $this->assertEquals( ', ezcontentclass_name.name as contentclass_name',
                            strtolower( $result['attributeTargetSQL'] ) );
        $this->assertEquals( 'contentclass_name asc', strtolower( $result['sortingFields'] ) );

        $sortListTwo = array( array( 'class_name', false ),
                              array( 'class_identifier', true ) );
        $result = eZContentObjectTreeNode::createSortingSQLStrings( $sortListTwo );
        $this->assertEquals( ', ezcontentclass_name.name as contentclass_name',
                            strtolower( $result['attributeTargetSQL'] ));
        $this->assertEquals( 'contentclass_name desc, ezcontentclass.identifier asc',
                            strtolower( $result['sortingFields'] ) );

        //test trash node with classname
        $sortBy = array( array( 'class_name', true ),
                         array( 'contentobject_id', true ) );
        $params = array( 'SortBy', $sortBy );
        $result = eZContentObjectTrashNode::trashList( $params );
        $this->assertEquals( array(), $result );
        $result = eZContentObjectTrashNode::trashList( $params, true );
        $this->assertEquals( 0, $result );  //if there is an error, there will be fatal error message

        //test subtreenode with classname
        $parent = new ezpObject( 'folder', 1 );
        $parent->publish();
        $parentNodeID = $parent->mainNode->node_id;

        $article = new ezpObject( 'article', $parentNodeID );
        $article->publish();

        $link = new ezpObject( 'link', $parentNodeID );
        $link->publish();

        $folder = new ezpObject( 'folder', $parentNodeID );
        $folder->publish();

        $folder2 = new ezpObject( 'folder', $parentNodeID );
        $folder2->publish();

        $sortBy = array( array( 'class_name', false ) );
        $params = array( 'SortBy' => $sortBy );
        $result = eZContentObjectTreeNode::subTreeByNodeID( $params, $parentNodeID );
        $this->assertEquals( $article->mainNode->node_id, $result[count( $result )-1]->attribute( 'node_id' ) );

        $sortBy = array( array( 'class_name', false ),
                         array( 'contentobject_id', false ) );
        $params = array( 'SortBy' => $sortBy );
        $result = eZContentObjectTreeNode::subTreeByNodeID( $params, $parentNodeID );
        $this->assertEquals( $folder2->mainNode->node_id, $result[1]->attribute( 'node_id' ) );
    }

    /**
     * Regression test for issue #16949
     * 1) Test there is no pending object in sub objects
     * 2) Test there is one pending object in sub objects
     */
    public function testIssue16949()
    {
        //create object
        $top = new ezpObject( 'article', 2 );
        $top->title = 'TOP ARTICLE';
        $top->publish();
        $child = new ezpObject( 'article', $top->mainNode->node_id );
        $child->title = 'THIS IS AN ARTICLE';
        $child->publish();

        $adminUser = eZUser::fetchByName( 'admin' );
        $adminUserID = $adminUser->attribute( 'contentobject_id' );
        $currentUser = eZUser::currentUser();
        $currentUserID = eZUser::currentUserID();
        eZUser::setCurrentlyLoggedInUser( $adminUser, $adminUserID );

        $result = eZContentObjectTreeNode::subtreeRemovalInformation( array( $top->mainNode->node_id ) );
        $this->assertFalse( $result['has_pending_object'] );
        $workflowArticle = new ezpObject( 'article', $top->mainNode->node_id );
        $workflowArticle->title = 'THIS IS AN ARTICLE WITH WORKFLOW';
        $workflowArticle->publish();
        $version = $workflowArticle->currentVersion();
        $version->setAttribute( 'status', eZContentObjectVersion::STATUS_PENDING );
        $version->store();
        $result = eZContentObjectTreeNode::subtreeRemovalInformation( array( $top->mainNode->node_id ) );
        $this->assertTrue( $result['has_pending_object'] );

        eZUser::setCurrentlyLoggedInUser( $currentUser, $currentUserID );
    }

    /**
     * Regression test for issue {@see #17632 http://issues.ez.no/17632}
     *
     * In a multi language environment, a node fetched with a language other than the prioritized one(s) will return the
     * URL alias in the prioritized language
     */
    public function testIssue17632()
    {
        $bkpLanguages = eZContentLanguage::prioritizedLanguageCodes();

        $strNameEngGB = __FUNCTION__ . " eng-GB";
        $strNameFreFR = __FUNCTION__ . " fre-FR";

        // add a secondary language
        $locale = eZLocale::instance( 'fre-FR' );
        $translation = eZContentLanguage::addLanguage( $locale->localeCode(), $locale->internationalLanguageName() );

        // set the prioritize language list to contain english
        // ezpINIHelper::setINISetting( 'site.ini', 'RegionalSettings', 'SiteLanguageList', array( 'fre-FR' ) );
        eZContentLanguage::setPrioritizedLanguages( array( 'fre-FR' ) );

        // Create an object with data in fre-FR and eng-GB
        $folder = new ezpObject( 'folder', 2, 14, 1, 'eng-GB' );
        $folder->publish();

        // Workaround as setting folder->name directly doesn't produce the expected result
        $folder->addTranslation( 'eng-GB', array( 'name' => $strNameEngGB ) );
        $folder->addTranslation( 'fre-FR', array( 'name' => $strNameFreFR ) );

        $nodeId = $folder->main_node_id;

        // fetch the node with no default parameters. Should return the french URL Alias
        $node = eZContentObjectTreeNode::fetch( $nodeId );
        self::assertEquals( 'testIssue17632-fre-FR' , $node->attribute( 'url_alias' ) );

        // fetch the node in english. Should return the english URL Alias
        $node = eZContentObjectTreeNode::fetch( $nodeId, 'eng-GB' );
        self::assertEquals( 'testIssue17632-eng-GB' , $node->attribute( 'url_alias' ) );

        $folder->remove();
        $translation->removeThis();
        ezpINIHelper::restoreINISettings();
        eZContentLanguage::setPrioritizedLanguages( $bkpLanguages );
    }

    /**
     * Regression test for EZP-20933
     * Two policies coming from two groups should be additive, not substractive
     * @covers eZContentObjectTreeNode::canCreateClassList()
     */
    public function testCanCreateClassList_EZP20933()
    {
        eZINI::instance()->setVariable( 'RoleSettings', 'EnableCaching', 'no' );

        // create <group1>
        $group1 = $this->createGroup( __METHOD__ . " Group 1" );

        // create <group2>
        $group2 = $this->createGroup( __METHOD__ . " Group 2" );

        // create <folder> in /Media
        $folder = $this->createFolder( __METHOD__, 43 );

        // create <role1> with content/create, no limitations
        $role1 = $this->createRole( __METHOD__ . " Role 1" );
        $this->createPolicy( $role1, 'content', 'create' );

        // assign <role1> to <group1> with limitation to <Media>
        $role1->assignToUser( $group1->attribute( 'id' ), 43 );

        // role2  <role2> with content/create, classes( Image )
        $role2 = $this->createRole( __METHOD__ . " Role 2" );
        $policy = $this->createPolicy( $role2, 'content', 'create' );
        $policyLimitation = eZPolicyLimitation::createNew( $policy->attribute('id'), 'Class' );
        eZPolicyLimitationValue::createNew( $policyLimitation->attribute( 'id' ), 5 );

        // assign <role2> to <group2> with limitation to <Media>
        $role2->assignToUser( $group2->attribute( 'id' ), 'subtree', 43 );

        // create <user> in <group1>, add location to <group2>
        $user = $this->createUser(
            array( $group1->attribute( 'main_node_id' ), $group2->attribute( 'main_node_id' ) ),
            'ezp20933'
        );

        eZRole::expireCache();
        eZUser::cleanupCache();

        // check that <user> is allowed to create any Content class in <folder>
        eZUser::setCurrentlyLoggedInUser(
            eZUser::fetch( $user->attribute( 'id' ) ),
            $user->attribute( 'id' )
        );

        $canCreateClassList = $folder->mainNode()->canCreateClassList();
        self::assertCount(
            count( eZContentClass::fetchList() ),
            $canCreateClassList,
            "User should be able to create all classes"
        );

        ezpINIHelper::restoreINISettings();
    }

    /**
     * Test for a possible border case in EZP-20933
     * @covers eZContentObjectTreeNode::canCreateClassList()
     */
    public function testCanCreateClassList_EZP20933_languages()
    {
        $limitedClassId = 5;

        $this->addLanguage( 'fre-FR' );
        eZContentLanguage::setPrioritizedLanguages(
            array( 'eng-GB', 'fre-FR' )
        );

        // create a user
        eZINI::instance()->setVariable( 'RoleSettings', 'EnableCaching', 'no' );

        // create <group1>
        $group1 = $this->createGroup( __METHOD__ . " Group 1" );

        // create <group2>
        $group2 = $this->createGroup( __METHOD__ . " Group 2" );

        // create <folder> in /Media
        $folder = $this->createFolder( __METHOD__, 43 );

        // create <role1> with content/create, limited to eng-GB content
        $role1 = $this->createRole( __METHOD__ . " Role 1" );
        $policy = $this->createPolicy( $role1, 'content', 'create' );
        $policyLimitation = eZPolicyLimitation::createNew( $policy->attribute('id'), 'Language' );
        eZPolicyLimitationValue::createNew( $policyLimitation->attribute( 'id' ), 'eng-GB' );

        // role2  <role2> with content/create, classes( Image )
        $role2 = $this->createRole( __METHOD__ . " Role 2" );
        $policy = $this->createPolicy( $role2, 'content', 'create' );
        $policyLimitation = eZPolicyLimitation::createNew( $policy->attribute('id'), 'Class' );
        eZPolicyLimitationValue::createNew( $policyLimitation->attribute( 'id' ), $limitedClassId );

        // assign <role1> to <group1> with limitation to <testFolder>
        $role1->assignToUser( $group1->attribute( 'id' ), 'subtree', $folder->mainNodeID() );

        // assign <role2> to <group2> with limitation to <testFolder>
        $role2->assignToUser( $group2->attribute( 'id' ), 'subtree', $folder->mainNodeID() );

        // create <user> in <group1>, add location to <group2>
        $user = $this->createUser(
            array( $group1->attribute( 'main_node_id' ), $group2->attribute( 'main_node_id' ) ),
            'ezp20933_languages'
        );

        eZRole::expireCache();
        eZUser::cleanupCache();

        // check that <user> is allowed to create any Content class in <folder>
        eZUser::setCurrentlyLoggedInUser(
            eZUser::fetch( $user->attribute( 'id' ) ),
            $user->attribute( 'id' )
        );

        $canCreateClassList = $folder->mainNode()->canCreateClassList( true );

        self::assertCount(
            count( eZContentClass::fetchList() ),
            $canCreateClassList,
            "User should be able to create all classes"
        );

        /** @var $class eZContentClass */
        foreach ( $canCreateClassList as $class )
        {
            $expectedLanguages = ( $class->attribute( 'id' ) != $limitedClassId )
                ? array( 'eng-GB' )
                : array( 'eng-GB', 'fre-FR' );
            self::assertEquals(
                $expectedLanguages,
                $class->canInstantiateLanguages(),
                "Failed asserting that user can create " . $class->attribute( 'identifier' ) . "objects in " . join( ', ', $expectedLanguages )
            );
        }

        ezpINIHelper::restoreINISettings();
    }


    /**
     * @return \eZRole
     */
    private function createRole( $roleName )
    {
        $role = \eZRole::create( $roleName );
        $role->store();
        return $role;
    }

    /**
     * @param eZRole $role
     * @param string $module
     * @param string $function
     *
     * @return eZPolicy
     */
    private function createPolicy( \eZRole $role, $moduleName = '*', $functionName = '*' )
    {
        $policy = \eZPolicy::create(
            $role->attribute( 'id' ),
            $moduleName,
            $functionName
        );
        $policy->store();
        return $policy;
    }

    /**
     * @return \eZContentObject
     */
    public function createGroup( $name )
    {
        $group = new \ezpObject( 'user_group', 5, 14, 2 );
        $group->name = $name;
        $group->addTranslation( 'eng-GB', array( 'name' => $name ) );
        $group->publish();

        return $group->object;
    }

    /**
     * Creates a user in groups $parentGroupsIds.
     * The login will be used for the email (<login>@example.com),
     * and the password will be set to publish.
     *
     * @return \eZContentObject
     */
    private function createUser( array $parentGroupsIds, $login, $options = array() )
    {
        $password = eZUser::createHash( $login, 'publish', null, \eZUser::PASSWORD_HASH_MD5_USER );

        $user = new \ezpObject( 'user', array_shift( $parentGroupsIds ), 14, 2 );
        $user->user_account = "$login|$login@example.com|$password|0|2";
        foreach ( $parentGroupsIds as $groupId )
        {
            $user->addNode( $groupId );
        }
        $user->publish();
        return $user->object;
    }

    /**
     * @param $name
     * @param $parentNodeId
     *
     * @return \eZContentObject
     */
    private function createFolder( $name, $parentNodeId )
    {
        $folder = new \ezpObject( 'folder', $parentNodeId  );
        $folder->name = $name;
        $folder->addTranslation( 'eng-GB', array( 'name' => $name ) );
        $folder->publish();
        return $folder->object;
    }

    /**
     * Adds a language to the system if it isn't there yet
     * @param string $locale
     */
    private function addLanguage( $localeCode )
    {
        if ( eZContentLanguage::fetchByLocale( $localeCode ) )
            return;

        $locale = eZLocale::instance( $localeCode );
        if ( $locale->isValid() )
        {
            eZContentLanguage::addLanguage( $localeCode );
        }
    }
}
