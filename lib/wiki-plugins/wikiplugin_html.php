<?php
// (c) Copyright 2002-2012 by authors of the Tiki Wiki CMS Groupware Project
// 
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
// $Id$

function wikiplugin_html_info()
{
	return array(
		'name' => tra('HTML'),
		'documentation' => 'PluginHTML',
		'description' => tra('Add HTML to a page'),
		'prefs' => array('wikiplugin_html'),
		'body' => tra('HTML code'),
		'validate' => 'all',
		'filter' => 'rawhtml_unsafe',
		'icon' => 'pics/icons/mime/html.png',
		'tags' => array( 'basic' ),	
		'params' => array(
			'wiki' => array(
				'required' => false,
				'name' => tra('Wiki syntax'),
				'description' => tra('Parse wiki syntax within the HTML code.'),
				'options' => array(
					array('text' => '', 'value' => ''), 
					array('text' => tra('No'), 'value' => 0),
					array('text' => tra('Yes'), 'value' => 1),
				),
				'filter' => 'int',
				'default' => '',
			),
		),
	);
}

function wikiplugin_html($data, $params)
{
	global $tikilib;

	// strip out sanitisation which may have occurred when using nested plugins
	$html = str_replace('<x>', '', $data);
	
	// parse using is_html if wiki param set, or just decode html entities
	if ( isset($params['wiki']) && $params['wiki'] === 1 ) {
		$html = $tikilib->parse_data($html, array('is_html' => true));
	} else {
		$html  = html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');
	}

	return '~np~' . $html . '~/np~';
}
