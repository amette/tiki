<?php
// (c) Copyright 2002-2009 by authors of the Tiki Wiki/CMS/Groupware Project
// 
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
// $Id$
//this script may only be included - so its better to die if called directly.
if (strpos($_SERVER["SCRIPT_NAME"], basename(__FILE__)) !== false) {
	header("location: index.php");
	exit;
}
if (version_compare(PHP_VERSION, '5.0.0', '<')) {
	header("location: tiki-install.php");
	exit;
}
require_once 'lib/setup/third_party.php';
require_once 'tiki-filter-base.php';
// Enable Versioning
// Please update the specified class below at release time, as well as
// adding new release to http://tikiwiki.org/{$branch}.version file
include_once ('lib/setup/twversion.class.php');
$TWV = new TWVersion();
$num_queries = 0;
$elapsed_in_db = 0.0;
$server_load = '';
$area = 'tiki';
$crumbs = array();
require_once ('lib/setup/tikisetup.class.php');
require_once ('lib/setup/timer.class.php');
$tiki_timer = new timer();
$tiki_timer->start();
require_once ('tiki-setup_base.php');
if ($prefs['feature_tikitests'] == 'y') require_once ('tiki_tests/tikitestslib.php');
$crumbs[] = new Breadcrumb($prefs['browsertitle'], '', $prefs['tikiIndex']);
if ($prefs['site_closed'] == 'y') require_once ('lib/setup/site_closed.php');
require_once ('lib/setup/error_reporting.php');
if ($prefs['feature_bot_bar_debug'] == 'y' || $prefs['use_load_threshold'] == 'y') require_once ('lib/setup/load_threshold.php');
require_once ('lib/setup/absolute_urls.php');
if (($prefs['feature_wysiwyg'] != 'n' && $prefs['feature_wysiwyg'] != 'y') || $prefs['case_patched'] == 'n') require_once ('lib/setup/patches.php');
require_once ('lib/setup/sections.php');
require_once ('lib/headerlib.php');
if (isset($_REQUEST['PHPSESSID'])) $tikilib->setSessionId($_REQUEST['PHPSESSID']);
elseif (function_exists('session_id')) $tikilib->setSessionId(session_id());
require_once ('lib/setup/cookies.php');
require_once ('lib/setup/js_detect.php');
require_once ('lib/setup/user_prefs.php');
require_once ('lib/setup/language.php');
require_once ('lib/setup/wiki.php');
if ($prefs['feature_polls'] == 'y') require_once ('lib/setup/polls.php');
if ($prefs['feature_mailin'] == 'y') require_once ('lib/setup/mailin.php');
if ($prefs['useGroupHome'] == 'y') require_once ('lib/setup/default_homepage.php');
require_once ('lib/setup/theme.php');
if ($prefs['feature_babelfish'] == 'y' || $prefs['feature_babelfish_logo'] == 'y') require_once ('lib/setup/babelfish.php');
if (!empty($varcheck_errors)) {
	$smarty->assign('msg', $varcheck_errors);
	$smarty->display('error.tpl');
	die;
}
if ($prefs['feature_challenge'] == 'y') {
	require_once ('lib/setup/challenge.php');
}
require_once ('lib/setup/menus.php');
if ($prefs['feature_usermenu'] == 'y') require_once ('lib/setup/usermenu.php');
if ($prefs['feature_live_support'] == 'y') require_once ('lib/setup/live_support.php');
if ($prefs['feature_referer_stats'] == 'y' || $prefs['feature_stats'] == 'y') require_once ('lib/setup/stats.php');
require_once ('lib/setup/dynamic_variables.php');
require_once ('lib/setup/output_compression.php');
if ($prefs['feature_debug_console'] == 'y') {
	// Include debugger class declaration. So use loggin facility in php files become much easier :)
	include_once ('lib/debug/debugger.php');
}
if ($prefs['feature_integrator'] == 'y') require_once ('lib/setup/integrator.php');
if ($prefs['feature_search'] == 'y' && $prefs['feature_search_fulltext'] != 'y' && $prefs['search_refresh_index_mode'] == 'random') {
	include_once ('lib/search/refresh.php');
	include_once('lib/search/refresh-functions.php');

	register_shutdown_function('refresh_search_index');

}
if (isset($_REQUEST['comzone'])) require_once ('lib/setup/comments_zone.php');
if ($prefs['feature_lastup'] == 'y') require_once ('lib/setup/last_update.php');
if (!empty($_SESSION['interactive_translation_mode']) && ($_SESSION['interactive_translation_mode'] == 'on')) {
	include_once ("lib/multilingual/multilinguallib.php");
	$cachelib->empty_full_cache();
}
if ($prefs['feature_freetags'] == 'y') require_once ('lib/setup/freetags.php');
if ($prefs['feature_categories'] == 'y') require_once ('lib/setup/categories.php');
if ($prefs['feature_userlevels'] == 'y') require_once ('lib/setup/userlevels.php');
if ($prefs['auth_method'] == 'openid') require_once ('lib/setup/openid.php');
if ($prefs['feature_wysiwyg'] == 'y') {
	if (!isset($_SESSION['wysiwyg'])) $_SESSION['wysiwyg'] = 'n';
	$smarty->assign_by_ref('wysiwyg', $_SESSION['wysiwyg']);
}
if ($prefs['feature_phplayers'] == 'y') require_once ('lib/setup/phplayers.php');

require_once ('lib/setup/smarty.php');
$smarty->assign_by_ref('phpErrors', $phpErrors);
$smarty->assign_by_ref('num_queries', $num_queries);
$smarty->assign_by_ref('elapsed_in_db', $elapsed_in_db);
$smarty->assign_by_ref('crumbs', $crumbs);
$smarty->assign('lock', false);
$smarty->assign('edit_page', 'n');
$smarty->assign('forum_mode', 'n');
$smarty->assign('uses_tabs', 'n');
$smarty->assign('uses_phplayers', 'n');
$smarty->assign('wiki_extras', 'n');
$smarty->assign('tikipath', $tikipath);
$smarty->assign('tikiroot', $tikiroot);
$smarty->assign('url_scheme', $url_scheme);
$smarty->assign('url_host', $url_host);
$smarty->assign('url_port', $url_port);
$smarty->assign('url_path', $url_path);
$smarty->assign('dir_level', $dir_level);
$smarty->assign('base_host', $base_host);
$smarty->assign('base_url', $base_url);
$smarty->assign('base_url_http', $base_url_http);
$smarty->assign('base_url_https', $base_url_https);
$smarty->assign('show_stay_in_ssl_mode', $show_stay_in_ssl_mode);
$smarty->assign('stay_in_ssl_mode', $stay_in_ssl_mode);
$smarty->assign('tiki_version', $TWV->version);
$smarty->assign('tiki_branch', $TWV->branch);
$smarty->assign('tiki_star', $TWV->star);
$smarty->assign('tiki_uses_svn', $TWV->svn);

if( isset( $_GET['msg'] ) ) {
	$smarty->assign( 'display_msg', $_GET['msg'] );
} elseif( isset( $_SESSION['msg'] ) ) {
	$smarty->assign( 'display_msg', $_SESSION['msg'] );
	unset($_SESSION['msg']);
} else {
	$smarty->assign( 'display_msg', '' );
}

$headerlib->add_jsfile( 'lib/tiki-js.js' );	// tiki-js.js gets included even if javascript_enabled==n for the js test

if ($prefs['javascript_enabled'] == 'y') {
	
	$headerlib->add_jsfile( 'lib/jquery/jquery.js' );
	$headerlib->add_jsfile( 'lib/jquery_tiki/tiki-jquery.js' );
	
	if( $prefs['feature_jquery_ui'] == 'y' ) {
		$headerlib->add_jsfile( 'lib/jquery/jquery-ui/ui/jquery-ui.js' );
		$headerlib->add_cssfile( 'lib/jquery/jquery-ui/themes/' . $prefs['feature_jquery_ui_theme'] . '/jquery-ui.css' );
	}
	
	if( $prefs['feature_jquery_tooltips'] == 'y' ) {
		$headerlib->add_jsfile( "lib/jquery/cluetip/lib/jquery.hoverIntent.js" );
		$headerlib->add_jsfile( "lib/jquery/cluetip/lib/jquery.bgiframe.min.js" );
		$headerlib->add_jsfile( "lib/jquery/cluetip/jquery.cluetip.js" );
		$headerlib->add_cssfile( "lib/jquery/cluetip/jquery.cluetip.css" );
	}
	
	if( $prefs['feature_jquery_autocomplete'] == 'y' ) {
		$headerlib->add_jsfile( "lib/jquery/jquery-autocomplete/lib/jquery.ajaxQueue.js" );
		if( $prefs['feature_jquery_tooltips'] != 'y' ) {
			$headerlib->add_jsfile( "lib/jquery/jquery-autocomplete/lib/jquery.bgiframe.min.js" );
		}
		$headerlib->add_jsfile( "lib/jquery/jquery-autocomplete/jquery.autocomplete.js" );
		$headerlib->add_cssfile( "lib/jquery/jquery-autocomplete/jquery.autocomplete.css" );
	}
	
	if( $prefs['feature_jquery_superfish'] == 'y' ) {
		$headerlib->add_jsfile( "lib/jquery/superfish/js/superfish.js" );
		$headerlib->add_jsfile( "lib/jquery/superfish/js/supersubs.js" );
	}
	if( $prefs['feature_jquery_reflection'] == 'y' ) {
		$headerlib->add_jsfile( "lib/jquery/reflection-jquery/js/reflection.js" );
	}
	if( $prefs['feature_jquery_sheet'] == 'y' ) {
		$headerlib->add_cssfile( "lib/jquery/jquery.sheet/jquery.sheet.base.css" );
		$headerlib->add_jsfile( "lib/jquery/jquery.sheet/jquery.sheet.js" );
	}
	if( $prefs['feature_jquery_tablesorter'] == 'y' ) {
		$headerlib->add_cssfile( "lib/jquery_tiki/tablesorter/themes/tiki/style.css" );
		$headerlib->add_jsfile( "lib/jquery/tablesorter/jquery.tablesorter.js" );
		$headerlib->add_jsfile( "lib/jquery/tablesorter/addons/pager/jquery.tablesorter.pager.js" );
	}
	if( $prefs['feature_jquery_cycle'] == 'y' ) {
		$headerlib->add_jsfile( "lib/jquery/malsup-cycle/jquery.cycle.all.js" );
	}
	if( $prefs['feature_shadowbox'] == "y" ) {
		$headerlib->add_jsfile( "lib/jquery/colorbox/jquery.colorbox.js" );
		$headerlib->add_cssfile( "lib/jquery/colorbox/styles/colorbox.css" );
	}
	
	if( $prefs['feature_jquery_ui'] == 'y' || $prefs['feature_jquery_tooltips'] == 'y' || $prefs['feature_jquery_autocomplete'] == 'y' || $prefs['feature_jquery_superfish'] == 'y' || $prefs['feature_jquery_reflection'] == 'y' || $prefs['feature_jquery_cycle'] == 'y' || $prefs['feature_shadowbox'] == 'y' ) {
		$headerlib->add_jsfile( "lib/jquery/jquery.cookie.js" );
		$headerlib->add_jsfile( "lib/jquery/jquery.async.js" );
		$headerlib->add_jsfile( "lib/jquery/jquery.columnmanager/jquery.columnmanager.js" );
		$headerlib->add_jsfile( "lib/jquery/treeTable/src/javascripts/jquery.treeTable.js" );
		$headerlib->add_cssfile( "lib/jquery/treeTable/src/stylesheets/jquery.treeTable.css" );
	}
	
	if( ( $prefs['feature_jquery'] != 'y' || $prefs['feature_jquery_tablesorter'] != 'y' ) && $prefs['javascript_enabled'] == 'y' ) {
		$headerlib->add_jsfile( 'lib/tiki-js-sorttable.js' );
	}
	
	if( $prefs['feature_phplayers'] == 'y' ) {
		$headerlib->add_jsfile( "lib/phplayers/libjs/layersmenu-library.js" );
		$headerlib->add_jsfile( "lib/phplayers/libjs/layersmenu.js" );
		$headerlib->add_jsfile( "lib/phplayers/libjs/layerstreemenu-cookies.js" );
	}
	
	if( $prefs['wikiplugin_flash'] == 'y' ) {
		$headerlib->add_jsfile( 'lib/swfobject/swfobject.js' );
	}
}	// end if $prefs['javascript_enabled'] == 'y'