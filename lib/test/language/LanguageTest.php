<?php
// (c) Copyright 2002-2012 by authors of the Tiki Wiki CMS Groupware Project
// 
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
// $Id$

require_once 'lib/tikiaccesslib.php';
require_once 'lib/language/Language.php';

/**
 * Test class for Language.
 * Generated by PHPUnit on 2010-08-05 at 10:04:14.
 */
class LanguageTest extends TikiTestCase
{
	public function testAddPhpSlashes()
	{
		$string = "\n \t \r " . '\\ $ "';
		$expectedResult = '\n \t \r \\\\ \$ \"';
		$this->assertEquals($expectedResult, Language::addPhpSlashes($string));
	}

	public function testRemovePhpSlashes()
	{
		$string = '\n \t \r \\\\ \$ \"';
		$expectedResult = "\n \t \r " . '\\ $ "';
		$this->assertEquals($expectedResult, Language::removePhpSlashes($string));
	}
	
	// TODO: We need a way to create a Tiki database just for the tests
	/*public function testGetDbTranslatedLanguages() {
	}*/
}
