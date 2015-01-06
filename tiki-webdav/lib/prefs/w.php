<?php
// (c) Copyright 2002-2010 by authors of the Tiki Wiki/CMS/Groupware Project
// 
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
// $Id$

function prefs_w_list() {
	return array(
		'w_displayed_default' => array(
			'name' => 'Display by default',
			'type' => 'flag',
		),
		'w_use_dir' => array(
			'name' => tra('Path (if stored in directory)'),
			'type' => 'text',
			'size' => '20',
			'perspective' => false,
		),
		'w_use_db' => array(
			'name' => tra('Storage'),
			'type' => 'radio',
			'perspective' => false,
			'options' => array(
				'y' => tra('Store in database'),
				'n' => tra('Store in directory'),
			),
		),
	);	
	
}