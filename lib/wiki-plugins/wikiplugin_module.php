<?php
/* $Id: wikiplugin_module.php,v 1.8 2003-07-06 11:07:50 mose Exp $
Displays a module inlined in page

Parameters
module name : module=>lambda
align : align=>(left|center|right)
max : max=>20
np : np=>(0|1) # (for non-parsed content)
module args : arg=>value (depends on module)

Example:
{MODULE(module=>last_modified_pages,align=>left,max=>3,maxlen=>22)}
{/MODULE}

about module params : all params are passed in $module_params
so if you need to use parmas just add them in MODULE()
like the trackerId in the above example.
*/

function wikiplugin_module_help() {
  return tra("Displays a module inlined in page");
}

function wikiplugin_module($data,$params) {
  global $tikilib, $cache_time, $smarty, $dbTiki, $feature_directory, $ranklib, $feature_trackers, $tikidomain, 
		$user, $feature_tasks, $feature_user_bookmarks, $tiki_p_tasks, $tiki_p_create_bookmarks, $imagegallib;
	$out = '';
  extract($params);
	if(!isset($align)) {$align='left';}
	if(!isset($max)) {$max='10';}
	if(!isset($np)) {$np='0';}
  if(!isset($module)) {
		$out = '<form class="box" id="modulebox">';
		$out.= '<br><select name="choose">';
		$out.= '<option value="">'.tra('Please choose a module').'</option>';
		$out.= '<option value="" style="background-color:#bebebe;">'.tra('to be used as argument').'</option>';
		$out.= '<option value="" style="background-color:#bebebe;">{MODULE(module=>name_of_module)}</option>';
		$handle=opendir('modules');
		while ($file = readdir($handle)) {
			if ((substr($file,0,4) == "mod-") and (substr($file,-4,4) == ".php")) {
				$mod = substr(substr(basename($file),4),0,-4);
				$out.= "<option value=\"$mod\">$mod</option>";
			}
		}
		$out.= '</select></form>';
	} else {
		if(!isset($args)) {$args='';}
		$cachefile = 'modules/cache/'.$tikidomain.'mod-'.$module.'.tpl.cache';
		$phpfile = 'modules/mod-'.$module.'.php';
		$template= 'modules/mod-'.$module.'.tpl';
		$nocache= 'templates/modules/mod-'.$module.'.tpl.nocache';
		if((!file_exists($cachefile)) || (file_exists($nocache)) || ( (time() - filemtime($cachefile))>$cache_time )){
			if(file_exists($phpfile)) {
				$module_rows = $max;
				$module_params = $params;
				include_once($phpfile);
			}
			$template_file = 'templates/'.$template;
			if(file_exists($template_file)) {
				$out = $smarty->fetch($template);
			} else {
				if($tikilib->is_user_module($module)) {
					$info = $tikilib->get_user_module($module);
					$smarty->assign_by_ref('user_title',$info["title"]);
					$smarty->assign_by_ref('user_data',$info["data"]);
					$out = $smarty->fetch('modules/user_module.tpl');
				}
			}
			$fp = fopen($cachefile,"w+");
			fwrite($fp,$data,strlen($data));
			fclose($fp);
		} else {
			$fp = fopen($cachefile,"r");
			$out = fread($fp,filesize($cachefile));
			fclose($fp);
		}
		$out = eregi_replace("\n","",$out);
	}
	if ($out) {
		if ($np) {
  		$data = "<div style='float:$align;'>~pp~$out~/pp~</div>".$data;
		} else {
  		$data = "<div style='float:$align;'>$out</div>".$data;
		}
	} else {
		$data = "<div style='float:$align;color:#AA2200;'>".tra("Sorry no such module")."<br/><b>$module</b></div>".$data;
	}
	return $data;
}
?>
