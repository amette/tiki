<?php

// $Id$

// Copyright (c) 2002-2007, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

//
// $Id$
//

if (strpos($_SERVER["SCRIPT_NAME"],basename(__FILE__)) !== false) {
  header("location: index.php");
  exit;
}

function smarty_function_debugger($params, &$smarty) {
	
	global $prefs;
	if ($prefs['feature_debug_console'] == 'y') {
		global $debugger;
		
		require_once ('lib/debug/debugger.php');
		
		//global $smarty;
		
		// Get current URL
		$smarty->assign('console_father', $_SERVER["REQUEST_URI"]);
		
		// Set default value
		$smarty->assign('result_type', NO_RESULT);
		
		// Exec user command in internal debugger
		if (isset($_REQUEST["command"])) {
			// Exec command in debugger
			$command_result = $debugger->execute($_REQUEST["command"]);
		
			$smarty->assign('command', $_REQUEST["command"]);
			$smarty->assign('result_type', $debugger->result_type());
		
			// If result need temlate then we have $command_result array...
			if ($debugger->result_type() == TPL_RESULT) {
				$smarty->assign('result_tpl', $debugger->result_tpl());
		
				$smarty->assign_by_ref('command_result', $command_result);
			} else {
				$smarty->assign('command_result', $command_result);
			}
		} else {
			$smarty->assign('command', "");
		}
		
		// Draw tabs to array. Note that it MUST be AFTER exec command.
		// Bcouse 'exec' can change state of smth so tabs content should be changed...
		$tabs_list = $debugger->background_tabs_draw();
		// Add results tab which is always exists...
		$tabs_list["console"] = $smarty->fetch("debug/tiki-debug_console_tab.tpl");
		ksort ($tabs_list);
		$tabs = array();
		
		// TODO: Use stupid dbl loop to generate links code and divs,
		//       but it is quite suitable for
		foreach ($tabs_list as $tname => $tcode) {
			// Generate href code for current button
			$href = 'javascript:';
		
			foreach ($tabs_list as $tn => $t)
				$href .= (($tn == $tname) ? 'show' : 'hide') . "('" . md5($tn). "');";
		
			//
			$tabs[] = array(
				"button_caption" => $tname,
				"tab_id" => md5($tname),
				"button_href" => $href,
				"tab_code" => $tcode
			);
		}
		
		// Debug console open/close
		//require_once('lib/setup/cookies.php');
		$c = getCookie('debugconsole', 'menu');
		$smarty->assign('debugconsole_style', $c == 'o' ? 'display:block;' : 'display:none;');
		
		$smarty->assign_by_ref('tabs', $tabs);
		
		$js = '';
		if ($prefs['feature_jquery_ui'] == 'y') {
			global $headerlib;
			require_once('lib/headerlib.php');
			$js = $headerlib->add_jq_onready( "
\$jq('#debugconsole').draggable({
	stop: function(event, ui) {
		off = \$jq('#debugconsole').offset();
   		setCookie('debugconsole_poition', off.left + ',' + off.top);
	}
});
debugconsole_pos = getCookie('debugconsole_poition').split(',');
if (debugconsole_pos) {
	\$jq('#debugconsole').css({'left': debugconsole_pos[0] + 'px', 'top': debugconsole_pos[1] + 'px'});
} 
" );
		}
		$ret = $smarty->fetch('debug/function.debugger.tpl') . $js;
		return $ret;
	}
}
