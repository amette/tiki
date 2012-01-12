<?php
// (c) Copyright 2002-2012 by authors of the Tiki Wiki CMS Groupware Project
//
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
// $Id: mod-func-map_layer_selector.php 38675 2011-11-03 20:49:19Z pkdille $

if (strpos($_SERVER["SCRIPT_NAME"], basename(__FILE__)) !== false) {
	header("location: index.php");
	exit;
}


function module_map_mode_selector_info()
{
	return array(
		'name' => tra('Mode Selector'),
		'description' => tra("Toggle input modes for the map."),
		'prefs' => array(),
		'params' => array(
		),
	);
}

function module_map_mode_selector($mod_reference, $module_params)
{
}

