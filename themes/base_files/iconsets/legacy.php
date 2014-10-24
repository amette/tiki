<?php 
// (c) Copyright 2002-2014 by authors of the Tiki Wiki CMS Groupware Project
// 
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

//The default iconset associates icon names to icon fonts. It is used as the fallback for all other iconsets.


// This script may only be included - so its better to die if called directly.
if (strpos($_SERVER['SCRIPT_NAME'], basename(__FILE__)) !== false) {
	header('location: index.php');
	exit;
}

$iconset = array(
	'_settings' => array(
		'iconset_name' => tr('Legacy'),
		'iconset_description' => tr('Legacy (pre Tiki14) icons, mainly using famfamfam images'),
		'icon_path_image' => 'img/icons',
		'icon_tag' => 'img',
	),
	'actions' => array(
		'image_file_name' => 'application_form.png',
	),
	'add' => array(
		'image_file_name' => 'add.png',
	),
	'administer' => array(
		'image_file_name' => 'wrench.png',
	),
	'check' => array(
		'image_file_name' => 'select.gif',
	),
	'comments' => array(
		'image_file_name' => 'comments.png',
	),
	'copy' => array(
		'image_file_name' => 'ico_copy.gif',
	),
	'create' => array(
		'image_file_name' => 'add.png',
	),
	'delete' => array(
		'image_file_name' => 'cross.png',
	),
	'edit' => array(
		'image_file_name' => 'page_edit.png',
	),
	'envelope' => array(
		'image_file_name' => 'email.png',
	),
	'error' => array( 
		'image_file_name' => 'exclamation.png',
	),
	'export' => array( 
		'image_file_name' => 'disk.png',
	),
	'file-archive' => array( 
		'image_file_name' => 'folder.png',
	),
	'group' => array(
		'image_file_name' => 'group.png',
	),
	'group-watch' => array( 
		'image_file_name' => 'eye_group.png',
	),
	'help' => array( 
		'image_file_name' => 'help.png',
	),
	'history' => array(
		'image_file_name' => 'database.png',
	),
	'import' => array( 
		'image_file_name' => 'upload.png',
	),
	'info' => array( 
		'image_file_name' => 'information.png',
	),
	'link' => array( 
		'image_file_name' => 'link.png',
	),
	'list' => array( 
		'image_file_name' => 'application_view_list.png',
	),
	'menuitem' => array(
		'image_file_name' => 'omo.png',
	),
	'notepad' => array(
		'image_file_name' => 'disk.png',
	),
	'notification' => array(
		'image_file_name' => 'announce.png',
	),
	'ok' => array(
		'image_file_name' => 'accept.png',
	),
	'permission' => array(
		'image_file_name' => 'key.png',
	),
	'post' => array(
		'image_file_name' => 'pencil_add.png',
	),
	'print' => array(
		'image_file_name' => 'printer.png',
	),
	'refresh' => array(
		'image_file_name' => 'arrow_refresh.png',
	),
	'remove' => array(
		'image_file_name' => 'cross.png',
	),
	'rss' => array(
		'image_file_name' => 'feed.png',
	),
	'settings' => array(
		'image_file_name' => 'wrench.png',
	),
	'share' => array(
		'image_file_name' => 'sharethis.png',
	),
	'stop-watching' => array(
		'image_file_name' => 'no-eye.png',
	),
	'success' => array(
		'image_file_name' => 'accept.png',
	),
	'tag' => array(
		'image_file_name' => 'tag_blue.png',
	),
	'trash' => array(
		'image_file_name' => 'bin.png',
	),
	'view' => array(
		'image_file_name' => 'shape_square.png',
	),
	'warning' => array(
		'image_file_name' => 'sticky.png',
	),
	'watch' => array(
		'image_file_name' => 'eye.png',
	),
	'watch-group' => array(
		'image_file_name' => 'eye_group.png',
	),
);