<?php
/* $Id:  $ */

// this script may only be included - so it's better to die if called directly
if (strpos($_SERVER["SCRIPT_NAME"],basename(__FILE__)) !== false) {
  header("location: index.php");
  exit;
}

/**
 * \brief smarty_block_tabs : add tabs to a template
 *
 * params: name
 * params: toggle=y on n default
 *
 * usage: 
 * \code
 *	{tabset name='tabs}
 * 		{tab name='tab1'}tab content{/tab}
 * 		{tab name='tab2'}tab content{/tab}
 * 		{tab name='tab3'}tab content{/tab}
 *	{/tabset}
 * \endcode
 *
 */

function smarty_block_tabset($params, $content, &$smarty, &$repeat) {
	global $prefs, $smarty_tabset_name, $smarty_tabset, $smarty_tabset_i_tab, $cookietab, $headerlib;

	if ( $repeat ) {
		// opening 
		$smarty_tabset = array();
		if (!isset($smarty_tabset_i_tab)) {
			$smarty_tabset_i_tab = 1;
		}
		if ( isset($params['name']) and !empty($params['name']) ) {
			$smarty_tabset_name = $params['name'];
		} else {
			$smarty_tabset_name = "tiki_tabset";
		}
		global $smarty_tabset_name, $smarty_tabset;
		return;
	} else {
		$ret = '';
		//closing
		if ( $prefs['feature_tabs'] == 'y') {
			if (empty($params['toggle']) || $params['toggle'] != 'n') {
				require_once $smarty->_get_plugin_filepath('function','button');
				if (isset($_COOKIE["tabbed_$smarty_tabset_name"]) and $_COOKIE["tabbed_$smarty_tabset_name"] == 'n') {
					$button_params['_text'] = tra('Tab View');
				} else {
					$button_params['_text'] = tra('No Tabs');
				}
				$button_params['_auto_args']='*';
				$button_params['_onclick'] = "setCookie('tabbed_$smarty_tabset_name','".((isset($_COOKIE["tabbed_$smarty_tabset_name"]) && $_COOKIE["tabbed_$smarty_tabset_name"] == 'n') ? 'y' : 'n' )."') ;";
				$notabs = smarty_function_button($button_params,$smarty);
				$ret = "<div class='tabstoggle floatright'>$notabs</div><br class='clear'/>";
			}
		} else {
			return $content;
		}
		if ( isset($_COOKIE["tabbed_$smarty_tabset_name"]) && $_COOKIE["tabbed_$smarty_tabset_name"] == 'n' ) {
			return $ret.$content;
		}
		$ret .= '<div class="tabs">
			';
		$max = $smarty_tabset_i_tab - 1;
		$ini = $smarty_tabset_i_tab - count($smarty_tabset);
		$focus = $ini;
		foreach ($smarty_tabset as $value) {
			$ret .= '	<span id="tab'.$focus.'" class="tabmark tabinactive"><a href="#content'.$focus.'" onclick="javascript:tikitabs('.$focus.','.$max.','.$ini.'); return false;">'.$value.'</a></span>
				';
			++$focus;
		}
		$ret .= "</div>$content";
		if ($cookietab < $ini || $cookietab > $max) { // todo:: need to display the first tab
			$headerlib->add_js("tikitabs($ini, $max, $ini);");
		}
		return $ret;
	}
}