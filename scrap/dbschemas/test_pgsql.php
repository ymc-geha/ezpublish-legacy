<?php
//
// Created on: <30-Jan-2004 10:37:22 dr>
//
// Copyright (C) 1999-2004 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// Licencees holding a valid "eZ publish professional licence" version 2
// may use this file in accordance with the "eZ publish professional licence"
// version 2 Agreement provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" version 2 is available at
// http://ez.no/ez_publish/licences/professional/ and in the file
// PROFESSIONAL_LICENCE included in the packaging of this file.
// For pricing of this licence please contact us via e-mail to licence@ez.no.
// Further contact information is available at http://ez.no/company/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.

//

include ('../classes/ezdbschema.php');
include ('../classes/ezpgsqlschema.php');
include ('../classes/ezdbschemachecker.php');

$c = pg_connect('host=localhost dbname=eztest user=eztest password=eztest');

$dbschema1 = new eZPgsqlSchema();
$schema1 = $dbschema1->read( $c );

eZDbSchema::writeSchemaFile( $schema1, 'pgschema.php' );
eZPgsqlSchema::writeSchemaFile( $schema1, 'pgschema.sql' );

?>
