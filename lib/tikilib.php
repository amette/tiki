<?php

//this script may only be included - so its better to die if called directly.
if (strpos($_SERVER["SCRIPT_NAME"],basename(__FILE__)) !== false) {
    header("location: index.php");
}

require_once ('lib/pear/Date.php');
require_once ('lib/tikidate.php');
require_once ('lib/tikidblib.php');
//performance collecting:
//require_once ('lib/tikidblib-debug.php');

// This class is included by all the Tiki php scripts, so it's important
// to keep the class as small as possible to improve performance.
// What goes in this class:
// * generic functions that MANY scripts must use
// * shared functions (marked as /*shared*/) are functions that are
//   called from Tiki modules.
class TikiLib extends TikiDB {
    var $db; // The ADODB db object used to access the database

    var $buffer;
    var $flag;
    var $parser;
    var $pre_handlers = array();
    var $pos_handlers = array();
    var $postedit_handlers = array();
    var $usergroups_cache = array();

    var $num_queries = 0;

    // Constructor receiving a PEAR::Db database object.
    function TikiLib($db) {
	if (!$db) {
	    die ("Invalid db object passed to TikiLib constructor");
	}

	$this->db = $db;
    }


    /*shared*/
    function httprequest($url, $reqmethod = "GET") {
	global $use_proxy,$proxy_host,$proxy_port;
	// test url :
	if (!preg_match("/^[-_a-zA-Z0-9:\/\.\?&;=\+~%]*$/",$url)) return false;
	// rewrite url if sloppy # added a case for https urls
	if ( (substr($url,0,7) <> "http://") and
		(substr($url,0,8) <> "https://")
	   ) {
	    $url = "http://" . $url;
	}
	// (cdx) params for HTTP_Request.
	// The timeout may be defined by a DEFINE("HTTP_TIMEOUT",5) in some file...
	$aSettingsRequest=array("method"=>$reqmethod,"timeout"=>5);

	if (substr_count($url, "/") < 3) {
	    $url .= "/";
	}
	// Proxy settings
	if ($use_proxy == 'y') {
	    $aSettingsRequest["proxy_host"]=$proxy_host;
	    $aSettingsRequest["proxy_port"]=$proxy_port;
	}
	include_once ('lib/pear/HTTP/Request.php');
	$req = &new HTTP_Request($url, $aSettingsRequest);
	$data="";
	// (cdx) return false when can't connect
	// I prefer throw a PEAR_Error. You decide ;)
	if (PEAR::isError($oError=$req->sendRequest())) {
	    $fp = fopen($url, "r");

	    if ($fp) {
		$data = '';
		while(!feof($fp)) {
		    $data .= fread($fp,4096);
		}
		fclose ($fp);
	    }
	    if ($data =="") return false;
	} else $data = $req->getResponseBody();
	return $data;
    }

    /*shared*/
    function get_dsn_by_name($name) {
	if ($name == 'local') {
	    return true;
	}
	return $this->getOne("select `dsn`  from `tiki_dsn` where `name`='$name'");
    }

    /* convert data to iso-8601 format */
    function iso_8601 ($timestamp) {
	$main_date = date("Y-m-d\TH:i:s", $timestamp);

	$tz = date("O", $timestamp);
	$tz = substr_replace ($tz, ':', 3, 0);

	$return = $main_date . $tz;

	return $return;
    }

    /*shared*/
    function check_rules($user, $section) {
	// Admin is never banned
	if ($user == 'admin')
	    return false;

	$ips = explode('.', $_SERVER["REMOTE_ADDR"]);
	$now = date("U");
	$query = "select tb.`message`,tb.`user`,tb.`ip1`,tb.`ip2`,tb.`ip3`,tb.`ip4`,tb.`mode` from `tiki_banning` tb, `tiki_banning_sections` tbs where tbs.`banId`=tb.`banId` and tbs.`section`=? and ( (tb.`use_dates` = ?) or (tb.`date_from` <= ? and tb.`date_to` >= ?))";
	$result = $this->query($query,array($section,'n',(int)$now,(int)$now));

	while ($res = $result->fetchRow()) {
	    if (!$res['message']) {
		$res['message'] = tra('You are banned from'). ':' . $section;
	    }

	    if ($user && $res['mode'] == 'user') {
		// check user
		$pattern = '/' . $res['user'] . '/';

		if (preg_match($pattern, $user)) {
		    return $res['message'];
		}
	    } else {
		// check ip
		if (count($ips) == 4) {
		    if (($ips[0] == $res['ip1'] || $res['ip1'] == '*') && ($ips[1] == $res['ip2'] || $res['ip2'] == '*')
			    && ($ips[2] == $res['ip3'] || $res['ip3'] == '*') && ($ips[3] == $res['ip4'] || $res['ip4'] == '*')) {
			return $res['message'];
		    }
		}
	    }
	}

	return false;
    }

    /*shared*/
    function replace_note($user, $noteId, $name, $data) {
	$now = date("U");
	$size = strlen($data);

	if ($noteId) {
	    $query = "update `tiki_user_notes` set `name` = ?, `data` = ?, `size` = ?, `lastModif` = ?  where `user`=? and `noteId`=?";
	    $this->query($query,array($name,$data,(int)$size,(int)$now,$user,(int)$noteId));
	    return $noteId;
	} else {
	    $query = "insert into `tiki_user_notes`(`user`,`noteId`,`name`,`data`,`created`,`lastModif`,`size`) values(?,?,?,?,?,?,?)";
	    $this->query($query,array($user,(int)$noteId,$name,$data,(int)$now,(int)$now,(int)$size));
	    $noteId = $this->getOne( "select max(`noteId`) from `tiki_user_notes` where `user`=? and `name`=? and `created`=?",array($user,$name,(int)$now));
	    return $noteId;
	}
    }

    /*shared*/
    function add_user_watch($user, $event, $object, $type, $title, $url) {
	global $userlib;

	$hash = md5(uniqid('.'));
	$email = $userlib->get_user_email($user);
	$query = "delete from `tiki_user_watches` where ".$this->convert_binary()." `user`=? and `event`=? and `object`=?";
	$this->query($query,array($user,$event,$object));
	$query = "insert into `tiki_user_watches`(`user`,`event`,`object`,`email`,`hash`,`type`,`title`,`url`) ";
	$query.= "values(?,?,?,?,?,?,?,?)";
	$this->query($query,array($user,$event,$object,$email,$hash,$type,$title,$url));
	return true;
    }

    /*shared*/
    function remove_user_watch_by_hash($hash) {
	$query = "delete from `tiki_user_watches` where `hash`=?";
	$this->query($query,array($hash));
    }

    /*shared*/
    function remove_user_watch($user, $event, $object) {
	$query = "delete from `tiki_user_watches` where ".$this->convert_binary()." `user`=? and `event`=? and `object`=?";
	$this->query($query,array($user,$event,$object));
    }

    /*shared*/
    function get_user_watches($user, $event = '') {
	$mid = '';
	$bindvars=array($user);
	if ($event) {
	    $mid = " and `event`=? ";
	    $bindvars[]=$event;
	}

	$query = "select * from `tiki_user_watches` where ".$this->convert_binary()." `user`=? $mid";
	$result = $this->query($query,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $ret[] = $res;
	}
	return $ret;
    }

    /*shared*/
    function get_watches_events() {
	$query = "select distinct `event` from `tiki_user_watches`";
	$result = $this->query($query,array());
	$ret = array();
	while ($res = $result->fetchRow()) {
	    $ret[] = $res['event'];
	}
	return $ret;
    }

    /*shared*/
    function get_user_event_watches($user, $event, $object) {
	$query = "select * from `tiki_user_watches` where `user`=? and `event`=? and `object`=?";
	$result = $this->query($query,array($user,$event,$object));
	if (!$result->numRows()) return false;
	$res = $result->fetchRow();
	return $res;
    }

    /*shared*/
    function get_event_watches($event, $object) {
	$ret = array();

	$query = "select * from `tiki_user_watches` where `event`=? and `object`=?";
	$result = $this->query($query,array($event,$object));

	if (!$result->numRows())
	    return $ret;

	while ($res = $result->fetchRow()) {
	    $ret[] = $res;
	}

	return $ret;
    }


    /*shared*/
    function replace_task($user, $taskId, $title, $description, $date, $status, $priority, $completed, $percentage) {
	if ($taskId) {
	    $query = "update `tiki_user_tasks` set `title` = ?, `description` = ?, `date` = ?, `status` = ?, `priority` = ?, ";
	    $query.= "`percentage` = ?, `completed` = ?  where `user`=? and `taskId`=?";
	    $this->query($query,array($title,$description,$date,$status,$priority,$percentage,$completed,$user,$taskId));
	    return $taskId;
	} else {
	    $query = "insert into `tiki_user_tasks`(`user`,`taskId`,`title`,`description`,`date`,`status`,`priority`,`completed`,`percentage`) ";
	    $query.= " values(?,?,?,?,?,?,?,?,?)";

	    $this->query($query,array($user,$taskId,$title,$description,$date,$status,$priority,$completed,$percentage));
	    $taskId = $this->getOne( "select  max(`taskId`) from `tiki_user_tasks` where `user`=? and `title`=? and `date`=?",array($user,$title,$date));
	    return $taskId;
	}
    }

    /*shared*/
    function complete_task($user, $taskId) {
	$now = date("U");
	$query = "update `tiki_user_tasks` set `completed`=?, `status`='c', `percentage`=100 where `user`=? and `taskId`=?";
	$this->query($query,array((int)$now,$user,(int)$taskId));
    }

    /*shared*/
    function remove_task($user, $taskId) {
	$query = "delete from `tiki_user_tasks` where `user`=? and `taskId`=?";
	$this->query($query,array($user,(int)$taskId));
    }

    /*shared*/
    function list_tasks($user, $offset, $maxRecords, $sort_mode, $find, $use_date, $pdate) {
	$now = date("U");
	$bindvars=array($user);
	if ($use_date == 'y') {
	    $prio = " and date<=? ";
	    $bindvars2=$pdate;
	} else {
	    $prio = '';
	}

	if ($find) {
	    $findesc = '%' . $find . '%';

	    $mid = " and (`title` like $findesc or `description` like $findesc)";
	    $bindvars[]=$findesc;
	    $bindvars[]=$findesc;
	} else {
	    $mid = "" ;
	}

	$mid.=$prio;
	if(isset($bindvars2)) $bindvars[]=$bindvars2;

	$query = "select * from `tiki_user_tasks` where `user`=? $mid order by ".$this->convert_sortmode($sort_mode).",`taskId` desc";
	$query_cant = "select count(*) from `tiki_user_tasks` where `user`=? $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $ret[] = $res;
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*shared*/
    function dir_stats() {
	$aux = array();
	$aux["valid"] = $this->db->getOne("select count(*) from `tiki_directory_sites` where `isValid`=?",array('y'));
	$aux["invalid"] = $this->db->getOne("select count(*) from `tiki_directory_sites` where `isValid`=?",array('n'));
	$aux["categs"] = $this->db->getOne("select count(*) from `tiki_directory_categories`",array());
	$aux["searches"] = $this->db->getOne("select sum(`hits`) from `tiki_directory_search`",array());
	$aux["visits"] = $this->db->getOne("select sum(`hits`) from `tiki_directory_sites`",array());
	return $aux;
    }

    /*shared*/
    function dir_list_all_valid_sites2($offset, $maxRecords, $sort_mode, $find) {

	if ($find) {
	    $mid = " where `isValid`=? and (`name` like ? or `description` like ?)";
	    $bindvars=array('y','%'.$find.'%','%'.$find.'%');
	} else {
	    $mid = " where `isValid`=? ";
	    $bindvars=array('y');
	}

	$query = "select * from `tiki_directory_sites` $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_directory_sites` $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $ret[] = $res;
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*shared*/
    function get_directory($categId) {
	$query = "select * from `tiki_directory_categories` where `categId`=?";
	$result = $this->query($query,array($categId));
	if (!$result->numRows()) return false;
	$res = $result->fetchRow();
	return $res;
    }

    /*shared*/
    function user_unread_messages($user) {
	$cant = $this->getOne("select count(*) from `messu_messages` where `user`=? and `isRead`=?",array($user,'n'));
	return $cant;
    }

    /*shared*/
    function get_online_users() {
	$query = "select `user` ,`timestamp` from `tiki_sessions` where `user`<>?";
	$result = $this->query($query,array(''));
	$ret = array();
	while ($res = $result->fetchRow()) {
	    $res['user_information'] = $this->get_user_preference($res['user'], 'user_information', 'public');
	    $ret[] = $res;
	}
	return $ret;
    }

    /*shared*/
    function get_user_items($user) {
	$items = array();

	$query = "select ttf.`trackerId`, tti.`itemId` from `tiki_tracker_fields` ttf, `tiki_tracker_items` tti, `tiki_tracker_item_fields` ttif";
	$query .= " where ttf.`fieldId`=ttif.`fieldId` and ttif.`itemId`=tti.`itemId` and `type`=? and tti.`status`=? and `value`=?";
	$result = $this->query($query,array('u','o',$user));
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $itemId = $res["itemId"];

	    $trackerId = $res["trackerId"];
	    // Now get the isMain field for this tracker
	    $fieldId = $this->getOne("select `fieldId`  from `tiki_tracker_fields` ttf where `isMain`=? and `trackerId`=?",array('y',(int)$trackerId));
	    // Now get the field value
	    $value = $this->getOne("select `value`  from `tiki_tracker_item_fields` where `fieldId`=? and `itemId`=?",array((int)$fieldId,(int)$itemId));
	    $tracker = $this->getOne("select `name`  from `tiki_trackers` where `trackerId`=?",array((int)$trackerId));
	    $aux["trackerId"] = $trackerId;
	    $aux["itemId"] = $itemId;
	    $aux["value"] = $value;
	    $aux["name"] = $tracker;

	    if (!in_array($itemId, $items)) {
		$ret[] = $aux;
		$items[] = $itemId;
	    }
	}

	$groups = $this->get_user_groups($user);

	foreach ($groups as $group) {
	    $query = "select ttf.`trackerId`, tti.`itemId` from `tiki_tracker_fields` ttf, `tiki_tracker_items` tti, `tiki_tracker_item_fields` ttif ";
	    $query .= " where ttf.`fieldId`=ttif.`fieldId` and ttif.`itemId`=tti.`itemId` and `type`=? and tti.`status`=? and value=?";
	    $result = $this->query($query,array('g','o',$group));

	    while ($res = $result->fetchRow()) {
		$itemId = $res["itemId"];

		$trackerId = $res["trackerId"];
		// Now get the isMain field for this tracker
		$fieldId = $this->getOne("select `fieldId`  from `tiki_tracker_fields` ttf where `isMain`=? and `trackerId`=?",array('y',(int)$trackerId));
		// Now get the field value
		$value = $this->getOne("select `value`  from `tiki_tracker_item_fields` where `fieldId`=? and `itemId`=?",array((int)$fieldId,(int)$itemId));
		$tracker = $this->getOne("select `name`  from `tiki_trackers` where `trackerId`=?",array((int)$trackerId));
		$aux["trackerId"] = $trackerId;
		$aux["itemId"] = $itemId;
		$aux["value"] = $value;
		$aux["name"] = $tracker;

		if (!in_array($itemId, $items)) {
		    $ret[] = $aux;
		    $items[] = $itemId;
		}
	    }
	}

	return $ret;
    }

    /*shared*/
    function get_actual_content($contentId) {
	$data = '';

	$now = date("U");
	$query = "select max(`publishDate`) from `tiki_programmed_content` where `contentId`=? and `publishDate`<=?";
	$res = $this->getOne($query,array((int)$contentId,$now));

	if (!$res)
	    return '';

	$query = "select `data`  from `tiki_programmed_content` where `contentId`=? and `publishDate`=?";
	$data = $this->getOne($query,array((int)$contentId,$res));
	return $data;
    }

    /*shared*/
    function list_surveys($offset, $maxRecords, $sort_mode, $find) {

	if ($find) {
	    $findesc = '%' . $find . '%';

	    $mid = " where (`name` like ? or `description` like ?)";
	    $bindvars=array($findesc,$findesc);
	} else {
	    $mid = " ";
	    $bindvars=array();
	}

	$query = "select * from `tiki_surveys` $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_surveys` $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {

	    $add = TRUE;
	    global $feature_categories;
	    global $userlib;
	    global $user;
	    global $tiki_p_admin;

	    if ($tiki_p_admin != 'y' && $userlib->object_has_one_permission($res['surveyId'], 'survey')) {
		// gallery permissions override category permissions
		if (!$userlib->object_has_permission($user, $res['surveyId'], 'survey', 'tiki_p_take_survey') &&
			!$userlib->object_has_permission($user, $res['surveyId'], 'survey', 'tiki_p_view_survey_stats')) {
		    $add = FALSE;
		}
	    } elseif ($tiki_p_admin != 'y' && $feature_categories == 'y') {
		// no forum permissions so now we check category permissions
		global $categlib;
		if (!is_object($categlib)) {
		    include_once('lib/categories/categlib.php');
		}
		unset($tiki_p_view_categories); // unset this var in case it was set previously
		$perms_array = $categlib->get_object_categories_perms($user, 'survey', $res['surveyId']);
		if ($perms_array) {
		    $is_categorized = TRUE;
		    foreach ($perms_array as $perm => $value) {
			$$perm = $value;
		    }
		} else {
		    $is_categorized = FALSE;
		}

		if ($is_categorized && isset($tiki_p_view_categories) && $tiki_p_view_categories != 'y') {
		    $add = FALSE;
		}
	    }

	    if ($add) {
		$res["questions"] = $this->getOne("select count(*) from `tiki_survey_questions` where `surveyId`=?",array((int) $res["surveyId"]));
		$ret[] = $res;
	    }
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*
     * Score methods begin
     */

    // All information about an event type
    // shared
    function get_event($event) {
	$query = "select * from `tiki_score` where `event`=?";
	$result = $this->query($query,array($event));
	return $result->fetchRow();
    }


    /* 
     * Checks if an event should be scored and grants points to proper user
     * $multiplier is for rating events, in which the score will
     * be multiplied by other user's rating. Not yet used
     *
     * shared
     */
    function score_event($user, $event_type, $id = '', $multiplier=false) {
    	global $scorelib;
		if (!is_object($scorelib)) {
			include_once("lib/score/scorelib.php");
		}
	if ($user == 'admin' || !$user) { return; }

	$event = $this->get_event($event_type);
	
	if (!$event || !$event['score']) {
	    return;
	}

	$score = $event['score'];
	if ($multiplier) {
	    $score *= $multiplier;
	}

	if ($id || $event['expiration']) {
	    $expire = $event['expiration'];
	    $event_id = $event_type . '_' . $id;

	    $query = "select count(*) from `tiki_users_score` where `user`=? and `event_id`=?";
	    $bindvars = array($user, $event_id);
	    if ($expire) {
		$query .= " and `expire` > ?";
		$bindvars[] = time();
	    }
	    if ($this->getOne($query, $bindvars)) {
		return;
	    }

	    $query = "delete from `tiki_users_score` where `user`=? and `event_id`=?";
	    $this->query($query, array($user, $event_id));

	    $query = "insert into `tiki_users_score` (`user`, `event_id`, `expire`) values (?, ?, ?)"; 
	    $this->query($query, array($user, $event_id, time() + ($expire*60)));
	}

	$query = "update `users_users` set `score` = `score` + ? where `login`=?";
	$event['id'] = $id; // just for debug

	$this->query($query, array($score, $user));
	return;	
    }

    // List users by best scoring
    // shared
    function rank_users($limit = 10, $start = 0) {
	if (!$start) {
	    $start = "0";
	}
	// admin doesn't go on ranking
	$query = "select `userId`, `login`, `score` from `users_users` where `login` <> 'admin' order by `score` desc limit $start, $limit";

	$result = $this->query($query);
	$ranking = array();

	while ($res = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
	    $res['position'] = ++$start;
	    $ranking[] = $res;
	}
	return $ranking;
    }

    // Returns html <img> tag to star corresponding to user's score
    // shared
    function get_star($score) {
	$star = '';

	$star_colors = array(0 => 'grey',
		100 => 'blue',
		500 => 'green',
		1000 => 'yellow',
		2500 => 'orange',
		5000 => 'red',
		10000 => 'purple');

	foreach ($star_colors as $boundary => $color) {
	    if ($score >= $boundary) {
		$star = 'star_'.$color.'.gif';
	    }
	}

	if (!empty($star)) {
	    $alt = sprintf(tra("%d points"), $score);
	    $star = "<img src=\"img/icons/$star\" height=\"11\" width=\"11\" alt=\"$alt\" />&nbsp;";
	}

	return $star;
    }

    /*
     * Score methods end
     */


    //shared
    // \todo remove all hardcoded html in get_user_avatar()
    function get_user_avatar($user, $float = "") {
	global $userlib;
	if (empty($user))
	    return '';

	if (!$userlib->user_exists($user)) {
	    return '';
	}

	$type = $userlib->get_user_details('avatarType', $user);
	$libname = $userlib->get_user_details('avatarLibName', $user);

	$ret = '';
	$style = '';

	if (strcasecmp($float, "left") == 0) {
	    $style = "style='float:left;margin-right:5px;'";
	} else if (strcasecmp($float, "right") == 0) {
	    $style = "style='float:right;margin-left:5px;'";
	}

	switch ($type) {
	    case 'n':
		$ret = '';
		break;

	    case 'l':
		$ret = "<img border='0' width='45' height='45' src='" . $libname . "' " . $style . " alt=\"$user\"/>";
		break;

	    case 'u':
		$ret = "<img border='0' width='45' height='45' src='tiki-show_user_avatar.php?user=$user' " . $style . " alt=\"$user\"/>";
		break;
	}

	return $ret;
    }

    /*shared*/
    function get_forum_sections() {
	$query = "select distinct `section` from `tiki_forums` where `section`<>?";
	$result = $this->query($query,array(''));
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $ret[] = $res["section"];
	}

	return $ret;
    }

    /* Referer stats */
    /*shared*/
    function register_referer($referer) {
	$now = date("U");
	$cant = $this->getOne("select count(*) from `tiki_referer_stats` where `referer`=?",array($referer));

	if ($cant) {
	    $query = "update `tiki_referer_stats` set `hits`=`hits`+1,`last`=? where `referer`=?";
	} else {
	    $query = "insert into `tiki_referer_stats`(`last`,`referer`,`hits`) values(?,?,1)";
	}

	$result = $this->query($query,array((int)$now,$referer));
    }

    // File attachments functions for the wiki ////
    /*shared*/
    function add_wiki_attachment_hit($id) {
	global $count_admin_pvs, $user;
	if ($count_admin_pvs == 'y' || $user != 'admin') {
	    $query = "update `tiki_wiki_attachments` set `downloads`=`downloads`+1 where `attId`=?";
	    $result = $this->query($query,array((int)$id));
	}
	return true;
    }

    /*shared*/
    function get_wiki_attachment($attId) {
	$query = "select * from `tiki_wiki_attachments` where `attId`=?";
	$result = $this->query($query,array((int)$attId));
	if (!$result->numRows()) return false;
	$res = $result->fetchRow();
	return $res;
    }

    // Last visit module ////
    /*shared*/
    function get_news_from_last_visit($user) {
	if (!$user) return false;

	$last = $this->getOne("select `lastLogin`  from `users_users` where `login`=?",array($user));
	$ret = array();

	if (!$last) {
	    $last = time();
	}
	$ret["lastVisit"] = $last;
	$ret["images"] = $this->getOne("select count(*) from `tiki_images` where `created`>?",array((int)$last));
	$ret["pages"] = $this->getOne("select count(*) from `tiki_pages` where `lastModif`>?",array((int)$last));
	$ret["files"] = $this->getOne("select count(*) from `tiki_files` where `created`>?",array((int)$last));
	$ret["comments"] = $this->getOne("select count(*) from `tiki_comments` where `commentDate`>?",array((int)$last));
	$ret["users"] = $this->getOne("select count(*) from `users_users` where `registrationDate`>?",array((int)$last));
	return $ret;
    }

    // Templates ////
    /*shared*/
    function list_templates($section, $offset, $maxRecords, $sort_mode, $find) {
	$bindvars = array($section);
	if ($find) {
	    $findesc = '%'.$find.'%';
	    $mid = " and (`content` like ?)";
	    $bindvars[] = $findesc;
	} else {
	    $mid = "";
	}

	$query = "select `name` ,`created`,tcts.`templateId` from `tiki_content_templates` tct, `tiki_content_templates_sections` tcts ";
	$query.= " where tcts.`templateId`=tct.`templateId` and `section`=? $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_content_templates` tct, `tiki_content_templates_sections` tcts ";
	$query_cant.= "where tcts.`templateId`=tct.`templateId` and `section`=? $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $query2 = "select `section`  from `tiki_content_templates_sections` where `templateId`=?";

	    $result2 = $this->query($query2,array((int)$res["templateId"]));
	    $sections = array();

	    while ($res2 = $result2->fetchRow()) {
		$sections[] = $res2["section"];
	    }

	    $res["sections"] = $sections;
	    $ret[] = $res;
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*shared*/
    function get_template($templateId) {
	$query = "select * from `tiki_content_templates` where `templateId`=?";
	$result = $this->query($query,array((int)$templateId));
	if (!$result->numRows()) return false;
	$res = $result->fetchRow();
	return $res;
    }
    // templates ////

    /*shared*/
    function list_games($offset, $maxRecords, $sort_mode, $find) {
	$bindvars = array();
	if ($find) {
	    $findesc = '%'.$find.'%';
	    $mid = " where (`gameName` like ?)";
	    $bindvars[] = $findesc;
	} else {
	    $mid = "";
	}

	$query = "select * from `tiki_games` $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_games` $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $parts = explode('.', $res["gameName"]);

	    $res["thumbName"] = $parts[0];
	    $ret[] = $res;
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*shared*/
    function pick_cookie() {
	$cant = $this->getOne("select count(*) from `tiki_cookies`",array());
	if (!$cant) return '';

	$bid = rand(0, $cant - 1);
	//$cookie = $this->getOne("select `cookie`  from `tiki_cookies` limit $bid,1"); getOne seems not to work with limit
	$result = $this->query("select `cookie`  from `tiki_cookies`",array(),1,$bid);
	if ($res = $result->fetchRow()) {
	    $cookie = str_replace("\n", "", $res['cookie']);
	    return preg_replace('/^(.+?)(\s*--.+)?$/','<i>"$1"</i>$2',$cookie);
	}
	else
	    return "";
    }

    function get_pv_chart_data($days) {
	$now = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
	$dfrom = 0;
	if ($days != 0) $dfrom = $now - ($days * 24 * 60 * 60);

	$query = "select `day`, `pageviews` from `tiki_pageviews` where `day`<=? and `day`>=?";
	$result = $this->query($query,array((int)$now,(int)$dfrom));
	$ret = array();
	$n = ceil($result->numRows() / 10);
	$i = 0;

	while ($res = $result->fetchRow()) {
	    if ($i % $n == 0) {
		$data = array(
			date("j M", $res["day"]),
			$res["pageviews"]
			);
	    } else {
		$data = array(
			"",
			$res["pageviews"]
			);
	    }

	    $i++;
	    $ret[] = $data;
	}

	return $ret;
    }

    function add_pageview() {
	$dayzero = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
	$cant = $this->getOne("select count(*) from `tiki_pageviews` where `day`=?",array((int)$dayzero));

	if ($cant) {
	    $query = "update `tiki_pageviews` set `pageviews`=`pageviews`+1 where `day`=?";
	} else {
	    $query = "insert into `tiki_pageviews`(`day`,`pageviews`) values(?,1)";
	}
	$result = $this->query($query,array((int)$dayzero),-1,-1,false);
    }

    function get_usage_chart_data() {
		global $quizlib;
		if (!is_object($quizlib)) {
			require_once('lib/quizzes/quizlib.php');
		}
	$quizlib->compute_quiz_stats();
	$data[] = array( "wiki",   $this->getOne("select sum(`hits`) from `tiki_pages`",array()));
	$data[] = array( "img-g",  $this->getOne("select sum(`hits`) from `tiki_galleries`",array()));
	$data[] = array( "file-g", $this->getOne("select sum(`hits`) from `tiki_file_galleries`",array()));
	$data[] = array( "faqs",   $this->getOne("select sum(`hits`) from `tiki_faqs`",array()));
	$data[] = array( "quizzes",$this->getOne("select sum(`timesTaken`) from `tiki_quiz_stats_sum`",array()));
	$data[] = array( "arts",   $this->getOne("select sum(`reads`) from `tiki_articles`",array()));
	$data[] = array( "blogs",  $this->getOne("select sum(`hits`) from `tiki_blogs`",array()));
	$data[] = array( "forums", $this->getOne("select sum(`hits`) from `tiki_forums`",array()));
	$data[] = array( "games",  $this->getOne("select sum(`hits`) from `tiki_games`",array()));
	return $data;
    }

    // User assigned modules ////
    /*shared*/
    function get_user_id($user) {
	$id = $this->getOne("select `userId` from `users_users` where `login`=?", array($user),-1,-1,false);
	return $id;
    }

    /*shared*/
    function get_groups_all($group) {
	$query = "select `groupName`  from `tiki_group_inclusion` where `includeGroup`=?";
	$result = $this->query($query, array($group));
	$ret = array();
	while ($res = $result->fetchRow()) {
	    $ret[] = $res["groupName"];
	    $ret2 = $this->get_groups_all($res["groupName"]);
	    $ret = array_merge($ret, $ret2);
	}
	return array_unique($ret);
    }

    /*shared*/
    function get_included_groups($group) {
	$query = "select `includeGroup`  from `tiki_group_inclusion` where `groupName`=?";
	$result = $this->query($query, array($group));
	$ret = array();
	while ($res = $result->fetchRow()) {
	    $ret[] = $res["includeGroup"];
	    $ret2 = $this->get_included_groups($res["includeGroup"]);
	    $ret = array_merge($ret, $ret2);
	}
	return array_unique($ret);
    }

    /*shared*/
    function get_user_groups($user) {
	if (!isset($this->usergroups_cache[$user])) {
	    $userid = $this->get_user_id($user);
	    $query = "select `groupName`  from `users_usergroups` where `userId`=?";
	    $result=$this->query($query,array((int) $userid));
	    $ret = array();
	    while ($res = $result->fetchRow()) {
		$ret[] = $res["groupName"];
		$included = $this->get_included_groups($res["groupName"]);
		$ret = array_merge($ret, $included);
	    }
	    $ret[] = "Anonymous";
	    $ret = array_unique($ret);
	    $this->usergroups_cache[$user] = $ret;
	    return $ret;
	} else {
	    return $this->usergroups_cache[$user];
	}
    }

    /*shared*/
    function genPass() {
	$length=8;
	$vocales = "aeiouAEIOU";
	$consonantes = "bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ0123456789_";
	$r = '';
	for ($i = 0; $i < $length; $i++) {
	    if ($i % 2) {
		$r .= $vocales{rand(0, strlen($vocales) - 1)};
	    } else {
		$r .= $consonantes{rand(0, strlen($consonantes) - 1)};
	    }
	}
	return $r;
    }

    // generate a random string (for unsubscription code etc.)
    function genRandomString($base="") {
	if ($base == "") $base = $this->genPass();
	$base .= date("U");
	return md5($base);
    }

    // This function calculates the pageRanks for the tiki_pages
    // it can be used to compute the most relevant pages
    // according to the number of links they have
    // this can be a very interesting ranking for the Wiki
    // More about this on version 1.3 when we add the pageRank
    // column to tiki_pages
    function pageRank($loops = 16) {
	$query = "select `pageName`  from `tiki_pages`";
	$result = $this->query($query,array());
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $ret[] = $res["pageName"];
	}

	// Now calculate the loop
	$pages = array();

	foreach ($ret as $page) {
	    $val = 1 / count($ret);

	    $pages[$page] = $val;
	    $query = "update `tiki_pages` set `pageRank`=? where `pageName`= ?";
	    $result = $this->query($query, array((int)$val, $page) );
	}

	for ($i = 0; $i < $loops; $i++) {
	    foreach ($pages as $pagename => $rank) {
		// Get all the pages linking to this one
		$query = "select `fromPage`  from `tiki_links` where `toPage` = ?";
		$result = $this->query($query, array( $pagename ) );
		$sum = 0;

		while ($res = $result->fetchRow()) {
		    $linking = $res["fromPage"];

		    if (isset($pages[$linking])) {
			$q2 = "select count(*) from `tiki_links` where `fromPage`= ?";
			$cant = $this->getOne($q2, array($linking) );
			if ($cant == 0) $cant = 1;
			$sum += $pages[$linking] / $cant;
		    }
		}

		$val = (1 - 0.85) + 0.85 * $sum;
		$pages[$pagename] = $val;
		$query = "update `tiki_pages` set `pageRank`=? where `pageName`=?";
		$result = $this->query($query, array((int)$val, $pagename) );

		// Update
	    }
	}

	arsort ($pages);
	return $pages;
    }

    // Spellchecking routine
    // Parameters:
    // what: what to spell check (a text)
    // where: where to replace (maybe the same text)
    // language: language to use
    // element: element where the text is going to be replaced (a textarea or similar)
    /*shared*/
    // \todo replace the hardcoded html by smarty template
    function spellcheckreplace($what, $where, $language, $element) {
	global $smarty;

	$trl = '';
	$words = preg_split("/\s/", $what);

	foreach ($words as $word) {
	    if (preg_match("/^[A-Z]?[a-z]+$/", $word) && strlen($word) > 1) {
		$result = $this->spellcheckword($word, $language);

		if (count($result) > 0) {
		    // Replace the word with a warning color in the edit_data
		    // Prepare the replacement
		    $sugs = $result[$word];

		    $first = 1;
		    $repl = '';

		    $popup_text = '';

		    //foreach($sugs as $sug=>$lev) {
		    //  if($first) {
		    //  $repl.=' <span style="color:red;">'.$word.'</span>'.'<a title="'.$sug.'" style="text-decoration: none; color:red;" href="javascript:replaceSome(\'editwiki\',\''.$word.'\',\''.$sug.'\');">.</a>';
		    //  $first = 0;
		    //  } else {
		    //  $repl.='<a title="'.$sug.'" style="text-decoration: none; color:red;" href="javascript:replaceSome(\'editwiki\',\''.$word.'\',\''.$sug.'\');">.</a>';
		    //  //$repl.='|'.'<a style="color:red;" href="javascript:replaceSome(\'editwiki\',\''.$word.'\',\''.$sug.'\');">'.$sug.'</a>';
		    //  }
		    //}
		    //if($repl) {
		    //  $repl.=' ';
		    //}
		    if (count($sugs) > 0) {
			$asugs = array_keys($sugs);

			$temp_max = count($asugs);
			for ($i = 0; $i < $temp_max && $i < 5; $i++) {
			    $sug = $asugs[$i];

			    // If you want to use the commented out line below, please remove the \ in <\/script>; it was breaking vim highlighting.  -rlpowell
			    // $repl.="<script language='Javascript' type='text/javascript'>param_${word}_$i = new Array(\\\"$element\\\",\\\"$word\\\",\\\"$sug\\\");<\/script><a href=\\\"javascript:replaceLimon(param_${word}_$i);\\"."\">$sug</a><br />";
			    $repl .= "<a href=\'javascript:param=doo_${word}_$i();replaceLimon(param);\'>".addslashes($sug)."</a><br />";
			    $trl .= "<script language='Javascript' type='text/javascript'>function doo_${word}_$i(){ aux = new Array(\"$element\",\"$word\",\"$sug\"); return aux;}</script>";
			}

			//$popup_text = " <a title=\"".$sug."\" style=\"text-decoration:none; color:red;\" onClick='"."return overlib(".'"'.$repl.'"'.",STICKY,CAPTION,".'"'."SpellChecker suggestions".'"'.");'>".$word.'</a> ';
			$popup_text = " <a title=\"Click for a list of spelling suggestions\" style=\"text-decoration: none; color:red;\" onClick=\"return overlib('$repl',STICKY,CAPTION,'Spellchecker suggestions');\">$word</a> ";
		    }

		    //print("popup: <pre>".htmlentities($popup_text)."</pre><br />");
		    if ($popup_text) {
			$where = preg_replace("/\s$word\s/", $popup_text, $where);
		    } else {
			$where = preg_replace("/\s$word\s/", ' <span style="color:red;">' . $word . '</span> ', $where);
		    }

		    $smarty->assign('trl', $trl);
		    //$parsed = preg_replace("/\s$word\s/",' <a style="color:red;">'.$word.'</a> ',$parsed);
		}
	    }
	}

	return $where;
    }

    /*shared*/
    function spellcheckword($word, $lang) {
	include_once ("bablotron.php");

	$b = new bablotron($this->db, $lang);
	$result = $b->spellcheck_word($word);
	return $result;
    }

    /*shared*/
    function list_all_forum_topics($offset, $maxRecords, $sort_mode, $find) {
	$bindvars = array("forum",0);
	if ($find) {
	    $findesc = '%'.$find.'%';
	    $mid = " and (`title` like ? or `data` like ?)";
	    $bindvars[] = $findesc;
	    $bindvars[] = $findesc;
	} else {
	    $mid = "";
	}

	$query = "select * from `tiki_comments`,`tiki_forums` ";
	$query.= " where `object`=`forumId` and `objectType`=? and `parentId`=? $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_comments`,`tiki_forums` ";
	$query_cant.= " where `object`=`forumId` and `objectType`=? and `parentId`=? $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$now = date("U");
	$ret = array();

	while ($res = $result->fetchRow()) {

	    $add = TRUE;
	    global $feature_categories;
	    global $userlib;
	    global $user;
	    global $tiki_p_admin;

	    if ($tiki_p_admin != 'y' && $userlib->object_has_one_permission($res['forumId'], 'forums')) {
	    // quiz permissions override category permissions
			if (!$userlib->object_has_permission($user, $res['forumId'], 'forums', 'tiki_p_forum_read'))
			{
			    $add = FALSE;
			}
	    } elseif ($tiki_p_admin != 'y' && $feature_categories == 'y') {
	    	// no quiz permissions so now we check category permissions
	    	global $categlib;
			if (!is_object($categlib)) {
				include_once('lib/categories/categlib.php');
			}
	    	unset($tiki_p_view_categories); // unset this var in case it was set previously
	    	$perms_array = $categlib->get_object_categories_perms($user, 'forums', $res['forumId']);
	    	if ($perms_array) {
	    		$is_categorized = TRUE;
		    	foreach ($perms_array as $perm => $value) {
		    		$$perm = $value;
		    	}
	    	} else {
	    		$is_categorized = FALSE;
	    	}

	    	if ($is_categorized && isset($tiki_p_view_categories) && $tiki_p_view_categories != 'y') {
	    		$add = FALSE;
	    	}
	    }

		if ($add) {
		    $ret[] = $res;
		}
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*shared*/
    function list_forum_topics($forumId, $offset, $maxRecords, $sort_mode, $find) {
	$bindvars = array($forumId,$forumId,'forum',0);
	if ($find) {
	    $findesc = '%'.$find.'%';
	    $mid = " and (`title` like ? or `data` like ?)";
	    $bindvars[] = $findesc;
	    $bindvars[] = $findesc;
	} else {
	    $mid = "";
	}

	$query = "select * from `tiki_comments`,`tiki_forums` where ";
	$query.= " `forumId`=? and `object`=? and `objectType`=? and `parentId`=? $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_comments`,`tiki_forums` where ";
	$query_cant.= " `forumId`=? and `object`=? and `objectType`=? and `parentId`=? $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$now = date("U");
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $ret[] = $res;
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*shared*/
    function remove_object($type, $id) {
	global $categlib, $dbTiki;

	if (!is_object($categlib)) {
	    require_once ("lib/categories/categlib.php");
	}
	$categlib->uncategorize_object($type, $id);
	// Now remove comments
	$object = $type . $id;
	$query = "delete from `tiki_comments` where `object`=?  and `objectType`=?";
	$result = $this->query($query, array( $id, $type ));
	// Remove individual permissions for this object if they exist
	$query = "delete from `users_objectpermissions` where `objectId`=? and `objectType`=?";
	$result = $this->query($query,array(md5($object),$type));
	return true;
    }

    /* get_categorypath_array() doesn't seem to be used anywhere
       function get_categorypath_array($cats,$focus=0) {
       global $dbTiki, $smarty, $tikilib, $feature_categories, $categlib;
       if (!is_object($categlib)) {
       require_once ("lib/categories/categlib.php");
       }
       foreach ($cats as $categId) {
       $focused = false;
       $info = $categlib->get_category($categId);
       $out[$categId][] = array('id'=>$info["categId"],'name'=>$info["name"]);
       while ($info["parentId"] != 0) {
       $info = $categlib->get_category($info["parentId"]);
       if ($focus and $info["categId"] == $focus) {
       $focused = $categId;
       break;
       } else {
       $out[$categId][] = array('id'=>$info["categId"],'name'=>$info["name"]);
       }
       }
       }
       if ($focused) {
       return $out[$focused];
       } else {
       return $out;
       }
       }
     */

    /*shared*/
    // function enhancing php in_array() function
    function in_multi_array($needle, $haystack) {
	$in_multi_array = false;

	if (in_array($needle, $haystack)) {
	    $in_multi_array = true;
	} else {
	    while (list($tmpkey, $tmpval) = each($haystack)) {
		if (is_array($haystack[$tmpkey])) {
		    if ($this->in_multi_array($needle, $haystack[$tmpkey])) {
			$in_multi_array = true;
			break;
		    }
		}
	    }
	}
	return $in_multi_array;
    }

    /*shared*/
    function list_received_pages($offset, $maxRecords, $sort_mode = 'pageName_asc', $find) {
	$bindvars = array();
	if ($find) {
	    $findesc = '%'.$find.'%';
	    $mid = " where (`pagename` like ? or `data` like ?)";
	    $bindvbars[] = $findesc;
	    $bindvbars[] = $findesc;
	} else {
	    $mid = "";
	}

	$query = "select * from `tiki_received_pages` $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_received_pages` $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {
	    if ($this->page_exists($res["pageName"])) {
		$res["exists"] = 'y';
	    } else {
		$res["exists"] = 'n';
	    }

	    $ret[] = $res;
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    // Functions for polls ////
    /*shared*/
    function get_poll($pollId) {
	$query = "select * from `tiki_polls` where `pollId`=?";
	$result = $this->query($query,array((int)$pollId));
	if (!$result->numRows()) return false;
	$res = $result->fetchRow();
	return $res;
    }

    //This should be moved to a poll module (currently in tiki-setup.php
    /*shared*/
    function poll_vote($user, $pollId, $optionId) 
    {
	$query = "select `optionId` from `tiki_user_votings` where `user` = ? and `id` = ?";
	$previous_vote = $this->getOne($query,array( $user, "poll" . $pollId));

	// Only need to increase vote numbers if the user hasn't voted before.
	if( !$previous_vote || $previous_vote == 0 )
	{
	    if( $optionId != 'WithdrawVote' )
	    {
		$query = "update `tiki_polls` set `votes`=`votes`+1 where `pollId`=?";
		$result = $this->query($query,array((int)$pollId));

		$query = "update `tiki_poll_options` set `votes`=`votes`+1 where `optionId`=?";
		$result = $this->query($query,array((int)$optionId));
	    }
	}
	else
	{
	    if( $previous_vote != $optionId)
	    {
		// Decrement old vote.
		$query = "update `tiki_poll_options` set `votes`=`votes`-1 where `optionId`=?";
		$result = $this->query($query,array((int)$previous_vote));

		if( $optionId != 'WithdrawVote' )
		{
		    $query = "update `tiki_poll_options` set `votes`=`votes`+1 where `optionId`=?";
		    $result = $this->query($query,array((int)$optionId));
		}
	    }
	}

	// If the user is un-voting.
	if( $optionId == 'WithdrawVote' )
	{
	    $query = "update `tiki_polls` set `votes`=`votes`-1 where `pollId`=?";
	    $result = $this->query($query,array((int)$pollId));

	    $query = "delete from `tiki_user_votings` where `user` = ? and `id` = ?";
	    $result = $this->query($query,array( $user, (string) "poll" . $pollId));
	}
    }

    // end polls ////

    // Functions for the menubuilder and polls////
    /*Shared*/
    function get_menu($menuId) {
	$query = "select * from `tiki_menus` where `menuId`=?";
	$result = $this->query($query,array((int)$menuId));
	if (!$result->numRows()) return false;
	$res = $result->fetchRow();
	return $res;
    }

    /*shared*/
    function list_menu_options($menuId, $offset, $maxRecords, $sort_mode, $find, $full=false) {
	global $smarty,$user;
	$ret = array();
	$retval = array();
	$bindvars = array((int)$menuId);
	$usergroups = $this->get_user_groups($user);
	if ($find) {
	    $mid = " where `menuId`=? and (`name` like ? or `url` like ?)";
	    $bindvars[] = '%'. $find . '%';
	    $bindvars[] = '%'. $find . '%';
	} else {
	    $mid = " where `menuId`=? ";
	}
	$query = "select * from `tiki_menu_options` $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_menu_options` $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	while ($res = $result->fetchRow()) {
	    if (!$full) {
		$display = true;
		if (isset($res['section']) and $res['section']) {
		    $sections = split(",",$res['section']);
		    foreach ($sections as $sec) {
			if (!isset($smarty->_tpl_vars["$sec"]) or $smarty->_tpl_vars["$sec"] != 'y') {
			    $display = false;
			    break;
			}
		    }
		}
		if ($display) {
		    if (isset($res['perm']) and $res['perm']) {
			$sections = split(",",$res['perm']);
			foreach ($sections as $sec) {
			    if (!isset($smarty->_tpl_vars["$sec"]) or $smarty->_tpl_vars["$sec"] != 'y') {
				$display = false;
				break;
			    }
			}
		    }
		}
		if ($display) {
		    if (isset($res['groupname']) and $res['groupname']) {
			$sections = split(",",$res['groupname']);
			foreach ($sections as $sec) {
			    if ($sec and !in_array($sec,$usergroups)) {
				$display = false;
			    }
			}
		    }
		}
		if ($display) {
		    $pos = $res['position'];
		    $ret["$pos"] = $res;
		}
	    } else {
		$ret[] = $res;
	    }
	}
	$retval["data"] = array_values($ret);
	$retval["cant"] = $cant;
	return $retval;
    }

    /* shared 
     * gets result from list_menu_options and sorts "sorted section" sections.
     */
    function sort_menu_options($channels) {

	$sorted_channels = array();

	if (isset($channels['data'])) {
	    $cant = $channels['cant'];
	    $channels = $channels['data'];
	}

	$temp_max = sizeof($channels);
	for ($i=0; $i < $temp_max; $i++) {
	    $sorted_channels[$i] = $channels[$i];
	    if ($sorted_channels[$i]['type'] == 'r') { // sorted section
		$sorted_channels[$i]['type'] = 's'; // common section, let's make it transparent
		$i++;
		$section = array();
		while ($i < sizeof($channels) && $channels[$i]['type'] == 'o') {
		    $section[] = $channels[$i];
		    $i++;
		}
		$i--;
		usort($section, "compare_menu_options");
		$sorted_channels = array_merge($sorted_channels, $section);
	    }
	}

	if (isset($cant)) {
	    $sorted_channels = array ('data' => $sorted_channels,
		    'cant' => $cant);
	}

	return $sorted_channels;
    }

    // Menubuilder ends ////

    // User voting system ////
    // Used to vote everything (polls,comments,files,submissions,etc) ////
    // Checks if a user has voted
    /*shared*/
    function user_has_voted($user, $id) {
	// If user is not logged in then check the session
	if (!$user) {
	    $votes = $_SESSION["votes"];

	    if (in_array($id, $votes)) {
		$ret = true;
	    } else {
		$ret = false;
	    }
	} else {
	    $query = "select count(*) from `tiki_user_votings` where `user`=? and `id`=?";
	    $result = $this->getOne($query,array($user,(string) $id));
	    if ($result) {
		$ret = true;
	    } else {
		$ret = false;
	    }
	}
	return $ret;
    }

    // Registers a user vote
    /*shared*/
    function register_user_vote($user, $id, $optionId = 0) {
	// If user is not logged in then register in the session
	if (!$user) {
	    $_SESSION["votes"][] = $id;
	} else {
	    if( $optionId != 'WithdrawVote' )
	    {
		if( $optionId != 0 )
		{
		    $query = "delete from `tiki_user_votings` where `user`=? and `id`=?";
		    $result = $this->query($query,array($user,(string) $id));
		    $query = "insert into `tiki_user_votings`(`user`,`id`, `optionId` ) values(?,?,?)";
		    $result = $this->query($query,array($user,(string) $id, $optionId));
		} else {
		    $query = "delete from `tiki_user_votings` where `user`=? and `id`=?";
		    $result = $this->query($query,array($user,(string) $id));
		    $query = "insert into `tiki_user_votings`(`user`,`id` ) values(?,?)";
		    $result = $this->query($query,array($user,(string) $id));
		}
	    }
	}
    }

    // FILE GALLERIES ////
    /*shared*/
    function list_files($offset, $maxRecords, $sort_mode, $find) {
	$bindvars = array();
	if ($find) {
	    $findesc = '%' . $find . '%';
	    $mid = " where (`name` like ? or `description` like ?)";
	    $bindvars[] = '%'. $find . '%';
	    $bindvars[] = '%'. $find . '%';
	} else {
	    $mid = "";
	}
	$query = "select `fileId` ,`name`,`description`,`created`,`filename`,`filesize`,`user`,`downloads` ";
	$query.= " from `tiki_files` $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_files` $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $ret[] = $res;
	}
	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*shared*/
    function get_file($id) {
	$query = "select `path` ,`galleryId`,`filename`,`filetype`,`data`,`filesize` from `tiki_files` where `fileId`=?";
	$result = $this->query($query,array((int) $id));
	$res = $result->fetchRow();
	return $res;
    }

    /*Shared*/
    function get_files($offset, $maxRecords, $sort_mode, $find, $galleryId) {

	if ($find) {
	    $findesc='%' . $find . '%';
	    $mid = " where `galleryId`=? and (`name` like ? or `description` like ?)";
	    $bindvars=array((int) $galleryId,$findesc,$findesc);
	} else {
	    $mid = "where `galleryId`=?";
	    $bindvars=array((int) $galleryId);
	}

	$query = "select `fileId` ,`name`,`description`,`created`,`filename`,`filesize`,`user`,`downloads` from `tiki_files` $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_files` $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {
	    $ret[] = $res;
	}

	$retval = array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    /*shared*/
    function add_file_hit($id) {
	global $count_admin_pvs, $user;
	if ($count_admin_pvs == 'y' || $user != 'admin') {
	    $query = "update `tiki_files` set `downloads`=`downloads`+1 where `fileId`=?";
	    $result = $this->query($query,array((int) $id));
	}

	global $feature_score;
	if ($feature_score == 'y') {
	    $this->score_event($user, 'fgallery_download', $id);
	    $query = "select `user` from `tiki_files` where `fileId`=?";
	    $owner = $this->getOne($query, array((int)$id));
	    $this->score_event($owner, 'fgallery_is_downloaded', "$user:$id");
	}
	
	return true;
    }

    // \todo that function is used ?
    function logui($line) {
	$fw = fopen("log.txt", "a+");
	fputs($fw, $line . "\n");
	fclose ($fw);
    }

    // Semaphore functions ////
    function get_semaphore_user($semName) {
	return $this->getOne("select `user` from `tiki_semaphores` where `semName`=?",array($semName));
    }

    function semaphore_is_set($semName, $limit) {
	$now = date("U");
	$lim = $now - $limit;
	$query = "delete from `tiki_semaphores` where `semName`=? and `timestamp`<?";
	$result = $this->query($query,array($semName,(int)$lim));
	$query = "select `semName`  from `tiki_semaphores` where `semName`=?";
	$result = $this->query($query,array($semName));
	return $result->numRows();
    }

    function semaphore_set($semName) {
	global $user;

	if ($user == '') {
	    $user = 'anonymous';
	}

	$now = date("U");
	//  $cant=$this->getOne("select count(*) from `tiki_semaphores` where `semName`='$semName'");
	$query = "delete from `tiki_semaphores` where `semName`=?";
	$this->query($query,array($semName));
	$query = "insert into `tiki_semaphores`(`semName`,`timestamp`,`user`) values(?,?,?)";
	$result = $this->query($query,array($semName,(int)$now,$user));
	return $now;
    }

    function semaphore_unset($semName, $lock) {
	$query = "delete from `tiki_semaphores` where `semName`=? and `timestamp`=?";
	$result = $this->query($query,array($semName,(int)$lock));
    }

    // Hot words methods ////
    /*shared*/
    function get_hotwords() {
	static $cache_hotwords;
	if ( isset($cache_hotwords) ) {
	    return $cache_hotwords;
	}
	$query = "select * from `tiki_hotwords`";
	$result = $this->query($query, array(),-1,-1, false);
	$ret = array();
	while ($res = $result->fetchRow()) {
	    $ret[$res["word"]] = $res["url"];
	}
	$cache_hotwords = $ret;
	return $ret;
    }

    // FRIENDS METHODS //
    function list_user_friends($user, $offset = 0, $maxRecords = -1, $sort_mode = 'login_asc', $find = '')
    {
	global $userlib;

	$sort_mode = $this->convert_sortmode($sort_mode);

	if($find) {
	    $findesc = $this->qstr('%'.$find.'%');
	    $mid=" and (u.login like $findesc or p.value like $findesc) ";
	} else {
	    $mid='';
	}

	$user = addslashes($user);

	// TODO: same as list_users
	$query = "select u.*, p.value as realName from tiki_friends as f, users_users as u left join tiki_user_preferences p on u.login=p.user and p.prefName = 'realName' where u.login=f.friend and f.user='$user' and f.user <> f.friend $mid order by $sort_mode limit $offset, $maxRecords";
	$query_cant = "select count(*) from tiki_friends as f, users_users as u left join tiki_user_preferences p on u.login=p.user and p.prefName = 'realName' where u.login=f.friend and f.user='$user' $mid";
	$result = $this->query($query);
	$cant = $this->getOne($query_cant);
	$ret = Array();
	while ($res = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
	    $res['realname'] = $this->get_user_preference($res['login'], 'realName');
	    $ret[] = $res;
	}
	$retval = Array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;

    }

    function verify_friendship($user, $friend)
    {
	global $userlib;

	if ($user == $friend) {
	    return 0;
	}

	$user = addslashes($user);
	$friend = addslashes($friend);

	$query = "select count(*) from tiki_friends where user='$user' and friend='$friend'";
	return $this->getOne($query);
    }


    function list_users($offset = 0, $maxRecords = -1, $sort_mode = 'realName', $find = '')
    {
	global $user;

	if($find) {
	    $findesc = $this->qstr('%'.$find.'%');
	    $mid=" where (login like $findesc or p.value like $findesc) ";
	} else {
	    $mid='';
	}

	$sort_mode = $this->convert_sortmode($sort_mode);

	// TODO: This is lousy, later we have to configure what fields would be fetched
	// but how to get preferences avoiding the join, sort by any field and paginate without
	// loading all user list in memory?
	$query = "select u.*, f.user is not null as friend, p.value as realName from users_users as u left join tiki_friends as f on u.login=f.friend and f.user='".addslashes($user)."' left join tiki_user_preferences p on u.login=p.user and p.prefName='realName' $mid order by $sort_mode limit $offset, $maxRecords";
	$query_cant = "select count(*) from users_users u left join tiki_user_preferences p on u.login=p.user and p.prefName='realName' $mid";
	$result = $this->query($query);
	$cant = $this->getOne($query_cant);
	$ret = Array();
	while ($res = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
	    $ret[] = $res;
	}
	$retval = Array();
	$retval["data"] = $ret;
	$retval["cant"] = $cant;
	return $retval;
    }

    // CMS functions -ARTICLES- & -SUBMISSIONS- ////

    /*shared*/
# Returns a topicname from passed topicid
    function fetchtopicId($topic) {
	$topicId = '';
	$query = "select `topicId`  from `tiki_topics` where `name` = ?";
	$topicId = $this->getOne($query, array($topic) );
	return $topicId;
    }

    /*shared*/
    function list_articles($offset = 0, $maxRecords = -1, $sort_mode = 'publishDate_desc', $find = '', $date = '', $user, $type = '', $topicId = '', $visible_only = 'y') {
	global $userlib;

	$mid = " where `tiki_articles`.`type` = `tiki_article_types`.`type` and `tiki_articles`.`author` = `users_users`.`login` ";
	$bindvars=array();
	if ($find) {
	    $findesc = '%' . $find . '%';
	    $mid .= " and (`title` like ? or `heading` like ? or `body` like ?) ";
	    $bindvars=array($findesc,$findesc,$findesc);
	}
	if ($type) {
	    $bindvars[]=$type;
	    if ($mid) {
		$mid .= " and `tiki_articles`.`type`=? ";
	    } else {
		$mid = " where `tiki_articles`.`type`=? ";
	    }
	}

	if ($topicId) {
	    $bindvars[] = (int) $topicId;
	    if ($mid) {
		$mid .= " and `topicId`=? ";
	    } else {
		$mid = " where `topicId`=? ";
	    }

	}


	if (($visible_only) && ($visible_only <> 'n')) {
	    $now = date('U');
	    $bindvars[]=(int) $now;
	    $bindvars[]=(int) $now;
	    if ($mid) {
		$mid .= " and (`tiki_articles`.`publishDate`<? or `tiki_article_types`.`show_pre_publ`='y') and (`tiki_articles`.`expireDate`>? or `tiki_article_types`.`show_post_expire`='y')";
	    } else {
		$mid .= " where (`tiki_articles`.`publishDate`<? or `tiki_article_types`.`show_pre_publ`='y') and (`tiki_articles`.`expireDate`>? or `tiki_article_types`.`show_post_expire`='y')";
	    }
	}
	$query = "select `tiki_articles`.*,
	`users_users`.`avatarLibName`,
	`tiki_article_types`.`use_ratings`,
	`tiki_article_types`.`show_pre_publ`,
	`tiki_article_types`.`show_post_expire`,
	`tiki_article_types`.`heading_only`,
	`tiki_article_types`.`allow_comments`,
	`tiki_article_types`.`show_image`,
	`tiki_article_types`.`show_avatar`,
	`tiki_article_types`.`show_author`,
	`tiki_article_types`.`show_pubdate`,
	`tiki_article_types`.`show_expdate`,
	`tiki_article_types`.`show_reads`,
	`tiki_article_types`.`show_size`,
	`tiki_article_types`.`show_topline`,
	`tiki_article_types`.`show_subtitle`,
	`tiki_article_types`.`show_linkto`,
	`tiki_article_types`.`show_image_caption`,
	`tiki_article_types`.`show_lang`,
	`tiki_article_types`.`creator_edit`
	    from `tiki_articles`, `tiki_article_types`, `users_users` $mid order by ".$this->convert_sortmode($sort_mode);
	$query_cant = "select count(*) from `tiki_articles`, `tiki_article_types`, `users_users` $mid";
	$result = $this->query($query,$bindvars,$maxRecords,$offset);
	$cant = $this->getOne($query_cant,$bindvars);
	$ret = array();

	while ($res = $result->fetchRow()) {

	    $add = TRUE;
	    global $feature_categories;
	    global $tiki_p_admin;

	    if ($tiki_p_admin != 'y' && $userlib->object_has_one_permission($res["topicId"], 'topic')) {
		// topic permissions override category permissions
		if (!$userlib->object_has_permission($user, $res["topicId"], 'topic', 'tiki_p_topic_read')) {
		    $add = FALSE;
		}
	    } elseif ($tiki_p_admin != 'y' && $feature_categories == 'y') {
		// no topic permissions so now we check category permissions
		global $categlib;
		if (!is_object($categlib)) {
		    include_once('lib/categories/categlib.php');
		}
		unset($tiki_p_view_categories); // unset this var in case it was set previously
		$perms_array = $categlib->get_object_categories_perms($user, 'article', $res['articleId']);
		if ($perms_array) {
		    $is_categorized = TRUE;
		    foreach ($perms_array as $perm => $value) {
			$$perm = $value;
		    }
		} else {
		    $is_categorized = FALSE;
		}

		if ($is_categorized && isset($tiki_p_view_categories) && $tiki_p_view_categories != 'y') {
		    $add = FALSE;
		}
	    }
	    // no need to do all of the following if we are not adding this article to the array
	    if ($add) {
		$res["entrating"] = floor($res["rating"]);
		if (empty($res["body"])) {
		    $res["isEmpty"] = 'y';
		} else {
		    $res["isEmpty"] = 'n';
		}
		if (strlen($res["image_data"]) > 0) {
		    $res["hasImage"] = 'y';
		} else {
		    $res["hasImage"] = 'n';
		}
		$res['count_comments'] = 0;

		// Determine if the article would be displayed in the view page
		$res["disp_article"] = 'y';
		$now = date("U");
		//if ($date) {
		if (($res["show_pre_publ"] != 'y') and ($now < $res["publishDate"])) {
		    $res["disp_article"] = 'n';
		}
		if (($res["show_post_expire"] != 'y') and ($now > $res["expireDate"])) {
		    $res["disp_article"] = 'n';
		}
		//}
		//	    if ($add) { // moved this check to the top
		$ret[] = $res;
	    }
	    }
	    $retval = array();
	    $retval["data"] = $ret;
	    $retval["cant"] = $cant;
	    return $retval;
	}

	/*shared*/
	function list_submissions($offset = 0, $maxRecords = -1, $sort_mode = 'publishDate_desc', $find = '', $date = '') {

	    if ($find) {
		$findesc = $this->qstr('%' . $find . '%');
		$mid = " where (`title` like ? or `heading` like ? or `body` like ?) ";
		$bindvars = array($findesc,$findesc,$findesc);
	    } else {
		$mid = '';
		$bindvars = array();
	    }

	    if ($date) {
		if ($mid) {
		    $mid .= " and `publishDate` <= ? ";
		} else {
		    $mid = " where `publishDate` <= ? ";
		}
		$bindvars[] = $date;
	    }

	    $query = "select * from `tiki_submissions` $mid order by ".$this->convert_sortmode($sort_mode);
	    $query_cant = "select count(*) from `tiki_submissions` $mid";
	    $result = $this->query($query,$bindvars,$maxRecords,$offset);
	    $cant = $this->getOne($query_cant,$bindvars);
	    $ret = array();

	    while ($res = $result->fetchRow()) {
		$res["entrating"] = floor($res["rating"]);

		if (empty($res["body"])) {
		    $res["isEmpty"] = 'y';
		} else {
		    $res["isEmpty"] = 'n';
		}

		if (strlen($res["image_data"]) > 0) {
		    $res["hasImage"] = 'y';
		} else {
		    $res["hasImage"] = 'n';
		}

		$ret[] = $res;
	    }

	    $retval = array();
	    $retval["data"] = $ret;
	    $retval["cant"] = $cant;
	    return $retval;
	}

	function get_article($articleId) {
	    $mid = " where `tiki_articles`.`type` = `tiki_article_types`.`type` and `tiki_articles`.`author` = `users_users`.`login` ";
	    $query = "select `tiki_articles`.*,
	    `users_users`.`avatarLibName`,
	    `tiki_article_types`.`use_ratings`,
	    `tiki_article_types`.`show_pre_publ`,
	    `tiki_article_types`.`show_post_expire`,
	    `tiki_article_types`.`heading_only`,
	    `tiki_article_types`.`allow_comments`,
	    `tiki_article_types`.`comment_can_rate_article`,
	    `tiki_article_types`.`show_image`,
	    `tiki_article_types`.`show_avatar`,
	    `tiki_article_types`.`show_author`,
	    `tiki_article_types`.`show_pubdate`,
	    `tiki_article_types`.`show_expdate`,
	    `tiki_article_types`.`show_reads`,
	    `tiki_article_types`.`show_size`,
	    `tiki_article_types`.`show_topline`,
	    `tiki_article_types`.`show_subtitle`,
	    `tiki_article_types`.`show_linkto`,
	    `tiki_article_types`.`show_image_caption`,
	    `tiki_article_types`.`show_lang`,
	    `tiki_article_types`.`creator_edit`
		from `tiki_articles`, `tiki_article_types`, `users_users` $mid and `tiki_articles`.`articleId`=?";
	    //$query = "select * from `tiki_articles` where `articleId`=?";
	    $result = $this->query($query,array((int)$articleId));
	    if ($result->numRows()) {
		$res = $result->fetchRow();
		$res["entrating"] = floor($res["rating"]);
	    } else {
		return false;
	    }
	    return $res;
	}

	function get_submission($subId) {
	    $query = "select * from `tiki_submissions` where `subId`=?";
	    $result = $this->query($query,array((int) $subId));
	    if ($result->numRows()) {
		$res = $result->fetchRow();
		$res["entrating"] = floor($res["rating"]);
	    } else {
		return false;
	    }
	    return $res;
	}

	/*shared*/
	function get_topic_image($topicId) {
	    $query = "select `image_name` ,`image_size`,`image_type`, `image_data` from `tiki_topics` where `topicId`=?";
	    $result = $this->query($query, array((int) $topicId));
	    $res = $result->fetchRow();
	    return $res;
	}

	/*shared*/
	function get_article_image($id) {
	    $query = "select `image_name` ,`image_size`,`image_type`, `image_data` from `tiki_articles` where `articleId`=?";
	    $result = $this->query($query, array((int) $id));
	    $res = $result->fetchRow();
	    return $res;
	}

	/*shared*/
	function get_featured_links($max = 10) {
	    $query = "select * from `tiki_featured_links` where `position` > ? order by ".$this->convert_sortmode("position_asc");
	    $result = $this->query($query, array(0), (int)$max, 0 );
	    $ret = array();
	    while ($res = $result->fetchRow()) {
		$ret[] = $res;
	    }
	    return $ret;
	}

	function update_session($sessionId) {
	    global $user;
	    if ($user === false) $user = '';
	    $now = date("U");
	    $oldy = $now - (5 * 60);
	    $query = "delete from `tiki_sessions` where `sessionId`=? or `timestamp`<?";
	    $bindvars = array($sessionId, $oldy);
	    if ($user) {
		$query .= " or `user`=?";
		$bindvars[] = $user;
	    }
	    $this->query($query, $bindvars, -1, -1, false);
	    $query = "insert into `tiki_sessions`(`sessionId`,`timestamp`,`user`) values(?,?,?)";
	    $result = $this->query($query, array($sessionId, (int)$now, $user));
	    return true;
	}

	function count_sessions() {
	    $query = "select count(*) from `tiki_sessions`";
	    $cant = $this->getOne($query,array());
	    return $cant;
	}

	/*shared*/
	function get_assigned_modules($position, $displayed="n") {
	    $filter = '';
	    if ($displayed != 'n') {
		$filter = " and (`type` is null or `type` !='h')";
	    }
	    $query = "select `params`,`name`,`title`,`position`,`ord`,`cache_time`,`rows`,`groups` from `tiki_modules` ";
	    $query.= " where `position`= ? $filter order by ".$this->convert_sortmode("ord_asc");

	    $result = $this->query($query, array($position));
	    $ret = array();

	    while ($res = $result->fetchRow()) {
		if ($res["groups"] && strlen($res["groups"]) > 1) {
		    $grps = @unserialize($res["groups"]);

		    $res["module_groups"] = '';
		    if (is_array($grps)) {
			foreach ($grps as $grp) {
			    $res["module_groups"] .= " $grp ";
			}
		    }
		} else {
		    $res["module_groups"] = '&nbsp;';
		}
		$ret[] = $res;
	    }
	    return $ret;
	}

	/*shared*/
	function is_user_module($name) {
	    $query = "select `name`  from `tiki_user_modules` where `name`=?";
	    $result = $this->query($query,array($name));
	    return $result->numRows();
	}

	/*shared*/
	function get_user_module($name) {
	    $query = "select * from `tiki_user_modules` where `name`=?";
	    $result = $this->query($query,array($name));
	    $res = $result->fetchRow();
	    return $res;
	}

	function cache_links($links) {
	    $cachepages = $this->get_preference("cachepages", 'y');
	    if ($cachepages != 'y') return false;
	    foreach ($links as $link) {
		if (!$this->is_cached($link)) {
		    $this->cache_url($link);
		}
	    }
	}

	function get_links($data) {
	    $links = array();

	    // Match things like [...], but ignore things like [[foo].
	    // -Robin
	    if (preg_match_all("/(?<!\[)\[([^\[\|\]]+)(\||\])/", $data, $r1)) {
		$res = $r1[1];
		$links = array_unique($res);
	    }

	    return $links;
	}

	function get_links_nocache($data) {
	    $links = array();

	    if (preg_match_all("/\[([^\]]+)/", $data, $r1)) {
		$res = array();

		foreach ($r1[1] as $alink) {
		    $parts = explode('|', $alink);

		    if (isset($parts[1]) && $parts[1] == 'nocache') {
			$res[] = $parts[0];
		    } else {
			if (isset($parts[2]) && $parts[2] == 'nocache') {
			    $res[] = $parts[0];
			}
		    }
		    // avoid caching URLs with common binary file extensions
		    $extension = substr($parts[0], -4);
		    $binary = array(
			    '.arj',
			    '.asf',
			    '.avi',
			    '.bz2',
			    '.dat',
			    '.doc',
			    '.exe',
			    '.hqx',
			    '.mov',
			    '.mp3',
			    '.mpg',
			    '.ogg',
			    '.pdf',
			    '.ram',
			    '.rar',
			    '.rpm',
			    '.rtf',
			    '.sea',
			    '.sit',
			    '.tar',
			    '.tgz',
			    '.wav',
			    '.wmv',
			    '.xls',
			    '.zip',
			    'ar.Z', // .tar.Z
			    'r.gz'  // .tar.gz
				);
			    if (in_array($extension, $binary)) {
				$res[] = $parts[0];
			    }

		}

		$links = array_unique($res);
	    }

	    return $links;
	}

	function is_cacheable($url) {
	    // simple implementation: future versions should analyse
	    // if this is a link to the local machine
	    if (strstr($url, 'tiki-')) {
		return false;
	    }

	    if (strstr($url, 'messu-')) {
		return false;
	    }

	    return true;
	}

	function is_cached($url) {
	    $query = "select `cacheId`  from `tiki_link_cache` where `url`=?";
	    $result = $this->query($query, array($url) );
	    $cant = $result->numRows();
	    return $cant;
	}

	function list_cache($offset, $maxRecords, $sort_mode, $find) {

	    if ($find) {
		$findesc = '%' . $find . '%';

		$mid = " where (`url` like ?) ";
		$bindvars=array($findesc);
	    } else {
		$mid = "";
		$bindvars=array();
	    }

	    $query = "select `cacheId` ,`url`,`refresh` from `tiki_link_cache` $mid order by ".$this->convert_sortmode($sort_mode);
	    $query_cant = "select count(*) from `tiki_link_cache` $mid";
	    $result = $this->query($query,$bindvars,$maxRecords,$offset);
	    $cant = $this->getOne($query_cant,$bindvars);
	    $ret = array();

	    while ($res = $result->fetchRow()) {
		$ret[] = $res;
	    }

	    $retval = array();
	    $retval["data"] = $ret;
	    $retval["cant"] = $cant;
	    return $retval;
	}

	function refresh_cache($cacheId) {
	    $query = "select `url`  from `tiki_link_cache`
		where `cacheId`=?";

	    $url = $this->getOne($query, array( $cacheId ) );
	    $data = $this->httprequest($url);
	    $refresh = date("U");
	    $query = "update `tiki_link_cache`
		set `data`=?, `refresh`=?
		where `cacheId`=? ";
	    $result = $this->query($query, array( $data, $refresh, $cacheId) );
	    return true;
	}

	function remove_cache($cacheId) {
	    $query = "delete from `tiki_link_cache` where `cacheId`=?";

	    $result = $this->query($query, array( $cacheId ) );
	    return true;
	}

	function get_cache($cacheId) {
	    $query = "select * from `tiki_link_cache`
		where `cacheId`=?";

	    $result = $this->query($query, array( $cacheId ) );
	    $res = $result->fetchRow();
	    return $res;
	}

	function get_cache_id($url) {
	    if (!$this->is_cached($url))
		return false;

	    $query = "select `cacheId`  from `tiki_link_cache`
		where `url`=?";
	    $id = $this->getOne($query, array( $url ) );
	    return $id;
	}

	function vote_page($page, $points) {
	    $query = "update `pages`
		set `points`=`points`+$points, `votes`=`votes`+1
		where `pageName`=?";
	    $result = $this->query($query, array( $page ));
	}

	function get_votes($page) {
	    $query = "select `points` ,`votes`
		from `pages` where `pageName`=?";
	    $result = $this->query($query, array( $page ));
	    $res = $result->fetchRow();
	    return $res;
	}

	// This funcion return the $limit most accessed pages
	// it returns pageName and hits for each page
	function get_top_pages($limit) {
	    $query = "select `pageName` , `hits`
		from `tiki_pages`
		order by `hits` desc";

	    $result = $this->query($query, array(),$limit);
	    $ret = array();

	    while ($res = $result->fetchRow()) {
		$aux["pageName"] = $res["pageName"];

		$aux["hits"] = $res["hits"];
		$ret[] = $aux;
	    }

	    return $ret;
	}

	// Returns the name of "n" random pages
	function get_random_pages($n) {
	    $query = "select count(*) from `tiki_pages`";

	    $cant = $this->getOne($query,array());

	    // Adjust the limit if there are not enough pages
	    if ($cant < $n)
		$n = $cant;

	    // Now that we know the number of pages to pick select `n`  random positions from `0` to cant
	    $positions = array();

	    for ($i = 0; $i < $n; $i++) {
		$pick = rand(0, $cant - 1);

		if (!in_array($pick, $positions))
		    $positions[] = $pick;
	    }

	    // Now that we have the positions we just build the data
	    $ret = array();

	    $temp_max = count($positions);
	    for ($i = 0; $i < $temp_max; $i++) {
		$index = $positions[$i];

		$query = "select `pageName`  from `tiki_pages`";
		$name = $this->getOne($query,array(),1,$index);
		$ret[] = $name;
	    }

	    return $ret;
	}
	
    // Returns the name of all pages
    function get_all_pages() {
    	
		$query = "select `pageName` from `tiki_pages`";
		$result = $this->query($query,array());
		$ret = array();

		while ($res = $result->fetchRow()) {
			$ret[] = $res;
		}

		return $ret;
    }
    
	/**
	 * \brief Cache given url
	 * If \c $data present (passed) it is just associated \c $url and \c $data.
	 * Else it will request data for given URL and store it in DB.
	 * Actualy (currently) data may be proviced by TIkiIntegrator only.
	 */
	function cache_url($url, $data = '') {
	    // Avoid caching internal references... (only if $data not present)
	    // (cdx) And avoid other protocols than http...
	    // 03-Nov-2003, by zaufi
	    // preg_match("_^(mailto:|ftp:|gopher:|file:|smb:|news:|telnet:|javascript:|nntp:|nfs:)_",$url)
	    // was removed (replaced to explicit http[s]:// detection) bcouse
	    // I now (and actualy use in my production Tiki) another bunch of protocols
	    // available in my konqueror... (like ldap://, ldaps://, nfs://, fish://...)
	    // ... seems like it is better to enum that allowed explicitly than all
	    // noncacheable protocols.
	    if (((strstr($url, 'tiki-') || strstr($url, 'messu-')) && $data == '')
		    || (substr($url, 0, 7) != 'http://' && substr($url, 0, 8) != 'https://'))
		return false;
	    // Request data for URL if nothing given in parameters
	    // (reuse $data var)
	    if ($data == '') $data = $this->httprequest($url);

	    // If stuff inside [] is *really* malformatted, $data
	    // will be empty.  -rlpowell
	    if ($data)
	    {
		$refresh = date("U");
		$query = "insert into `tiki_link_cache`(`url`,`data`,`refresh`) values(?,?,?)";
		$result = $this->queryError($query, $error, array($url,$data,$refresh) );
		return !isset($error);
	    }
	    else return false;
	}

	// Removes all the versions of a page and the page itself
	/*shared*/
	function remove_all_versions($page, $comment = '') {
	    global $dbTiki;
	    $this->invalidate_cache($page);
	    //Delete structure references before we delete the page
	    $query  = "select `page_ref_id` ";
	    $query .= "from `tiki_structures` ts, `tiki_pages` tp ";
	    $query .= "where ts.`page_id`=tp.`page_id` and `pageName`=?";
	    $result = $this->query($query, array( $page ) );
	    while ($res = $result->fetchRow()) {
		$this->remove_from_structure($res["page_ref_id"]);
	    }
	    $query = "delete from `tiki_pages` where `pageName` = ?";
	    $result = $this->query($query, array( $page ) );
	    $query = "delete from `tiki_history` where `pageName` = ?";
	    $result = $this->query($query, array( $page ) );
	    $query = "delete from `tiki_links` where `fromPage` = ?";
	    $result = $this->query($query, array( $page ) );
	    global $multilinguallib;
	    if (!is_object($multilinguallib)) {
		    include_once('lib/multilingual/multilinguallib.php');// must be done even in feature_multilingual not set
	    }
	    $multilinguallib->detachTranslation('wiki page', $multilinguallib->get_page_id_from_name($page));
	    $action = "Removed"; //get_strings tra("Removed");
	    $t = date("U");
	    $query = "insert into ";
	    $query .= "`tiki_actionlog`(`action`,`pageName`,`lastModif`,`user`,`ip`,`comment`) ";
	    $query .= "values(?,?,?,?,?,?)";
	    $result = $this->query($query, array(
			$action,$page,(int) $t,'admin',$_SERVER["REMOTE_ADDR"],$comment
			) );
	    $query = "update `users_groups` set `groupHome`=? where `groupHome`=?";
	    $this->query($query, array(NULL, $page));

	    $this->remove_object('wiki page', $page);

	    $query = "delete from `tiki_user_watches` where `event`=? and `object`=?";
	    $this->query($query,array('wiki_page_changed', $page));

		// remove static html page if necessary
		global $feature_wiki_realtime_static;
		if ($feature_wiki_realtime_static == 'y') {
			global $staticlib;
			if (!is_object($staticlib)) {
				require_once('lib/static/staticlib.php');
			}
			$staticlib->remove_page($page);
		}

	    return true;
	}

	/*shared*/
	function remove_from_structure($page_ref_id) {
	    // Now recursively remove
	    $query  = "select `page_ref_id` ";
	    $query .= "from `tiki_structures` as ts, `tiki_pages` as tp ";
	    $query .= "where ts.`page_id`=tp.`page_id` and `parent_id`=?";
	    $result = $this->query($query, array( $page_ref_id ) );

	    while ($res = $result->fetchRow()) {
		$this->remove_from_structure($res["page_ref_id"]);
	    }

	    $query = "delete from `tiki_structures` where `page_ref_id`=?";
	    $result = $this->query($query, array( $page_ref_id ) );
	    return true;
	}

	function last_pages($maxRecords = -1) {
	    $query = "select `pageName`,`lastModif`,`user` from `tiki_pages` order by ".$this->convert_sortmode('lastModif_desc');
	    $result = $this->query($query,array(),$maxRecords,0);
	    $ret = array();
	    while ($res = $result->fetchRow()) {
		$ret[] = $res;
	    }
	    return $ret;
	}


	function last_major_pages($maxRecords = -1) {
	    $query = "select tp.`pageName`,tp.`lastModif`,tp.`user` from `tiki_pages` tp left join `tiki_actionlog` ta
		on tp.`pageName`=ta.`pageName` and tp.`lastModif`=ta.`lastModif` where ta.`action`!='' order by tp.".$this->convert_sortmode('lastModif_desc');
	    $result = $this->query($query,array(),$maxRecords,0);
	    $ret = array();
	    while ($res = $result->fetchRow()) {
		$ret[] = $res;
	    }
	    return $ret;
	}

	function list_pages($offset = 0, $maxRecords = -1, $sort_mode = 'pageName_desc', $find = '') {

	    if ($sort_mode == 'size_desc') {
		$sort_mode = 'page_size_desc';
	    }

	    if ($sort_mode == 'size_asc') {
		$sort_mode = 'page_size_asc';
	    }

	    $old_sort_mode = '';

	    if (in_array($sort_mode, array(
			    'versions_desc',
			    'versions_asc',
			    'links_asc',
			    'links_desc',
			    'backlinks_asc',
			    'backlinks_desc'
			    ))) {
		$old_offset = $offset;

		$old_maxRecords = $maxRecords;
		$old_sort_mode = $sort_mode;
		$sort_mode = 'user_desc';
		$offset = 0;
		$maxRecords = -1;
	    }

	    if (is_array($find)) { // you can use an array of pages
		$mid = " where `pageName` IN (".implode(',',array_fill(0,count($find),'?')).")";
		$bindvars = $find;
	    } elseif (is_string($find)) { // or a string
		$mid = " where `pageName` like ? ";
		$bindvars = array('%' . $find . '%');
	    } else {
		$mid = "";
		$bindvars = array();
	    }

	    // If sort mode is versions then offset is 0, maxRecords is -1 (again) and sort_mode is nil
	    // If sort mode is links then offset is 0, maxRecords is -1 (again) and sort_mode is nil
	    // If sort mode is backlinks then offset is 0, maxRecords is -1 (again) and sort_mode is nil
	    $query = "select `creator` ,`pageName`, `hits`, `page_size` as `len`, `lastModif`, `user`, `ip`, `comment`, `version`, `flag` ";
	    $query.= " from `tiki_pages` $mid order by ".$this->convert_sortmode($sort_mode);
	    $query_cant = "select count(*) from `tiki_pages` $mid";
	    $result = $this->query($query,$bindvars,$maxRecords,$offset);
	    $cant = $this->getOne($query_cant,$bindvars);
	    $ret = array();

	    while ($res = $result->fetchRow()) {
		$aux = array();

		$aux["pageName"] = $res["pageName"];
		$page = $aux["pageName"];
		$aux["hits"] = $res["hits"];
		$aux["lastModif"] = $res["lastModif"];
		$aux["user"] = $res["user"];
		$aux["ip"] = $res["ip"];
		$aux["len"] = $res["len"];
		$aux["comment"] = $res["comment"];
		$aux["creator"] = $res["creator"];
		$aux["version"] = $res["version"];
		$aux["flag"] = $res["flag"] == 'L' ? 'locked' : 'unlocked';
		$aux["versions"] = $this->getOne("select count(*) from `tiki_history` where `pageName`=?",array($page));
		$aux["links"] = $this->getOne("select count(*) from `tiki_links` where `fromPage`=?",array($page));
		$aux["backlinks"] = $this->getOne("select count(*) from `tiki_links` where `toPage`=?",array($page));
		$ret[] = $aux;
	    }

	    // If sortmode is versions, links or backlinks sort using the ad-hoc function and reduce using old_offse and old_maxRecords
	    if ($old_sort_mode == 'versions_asc') {
		usort($ret, 'compare_versions');
	    }

	    if ($old_sort_mode == 'versions_desc') {
		usort($ret, 'r_compare_versions');
	    }

	    if ($old_sort_mode == 'links_desc') {
		usort($ret, 'compare_links');
	    }

	    if ($old_sort_mode == 'links_asc') {
		usort($ret, 'r_compare_links');
	    }

	    if ($old_sort_mode == 'backlinks_desc') {
		usort($ret, 'compare_backlinks');
	    }

	    if ($old_sort_mode == 'backlinks_asc') {
		usort($ret, 'r_compare_backlinks');
	    }

	    if (in_array($old_sort_mode, array(
			    'versions_desc',
			    'versions_asc',
			    'links_asc',
			    'links_desc',
			    'backlinks_asc',
			    'backlinks_desc'
			    ))) {
		$ret = array_slice($ret, $old_offset, $old_maxRecords);
	    }

	    $retval = array();
	    $retval["data"] = $ret;
	    $retval["cant"] = $cant;
	    return $retval;
	}

	function get_all_preferences() {
	    global $preferences;
	    if (empty($preferences)) {
		$query = "select `name` ,`value` from `tiki_preferences`";

		$result = $this->query($query,array());
		$preferences = array();

		while ($res = $result->fetchRow()) {
		    $preferences[$res["name"]] = $res["value"];
		}
	    }

	    return $preferences;
	}

	function get_preference($name, $default = '') {
	    global $preferences;

	    if (empty($preferences)) {
		$preferences = $this->get_all_preferences();
	    }

	    if (!isset($preferences[$name])) {
		$preferences[$name] = $default;
	    }

	    return $preferences[$name];
	}

	function set_preference($name, $value) {
	    global $preferences;
	    global $tikidomain;

	    @unlink ("templates_c/$tikidomain/preferences.php");

	    //refresh cache
	    if (isset($preferences[$name])) {
		unset ($preferences[$name]);

		$preferences[$name] = $value;
	    }

	    $query = "delete from `tiki_preferences` where `name`=?";
	    $result = $this->query($query,array($name),-1,-1,false);
	    $query = "insert into `tiki_preferences`(`name`,`value`) values(?,?)";
	    $result = $this->query($query,array($name,$value));
	    return true;
	}

	function load_user_cache($login, $all=false) {
	    global $user_details;
	    global $user_preferences;

	    if ( !is_array($login) ) {
		$login = array($login);
	    }

	    $query  = 'SELECT `login` , `userId` , `email` , `lastLogin` , `currentLogin` , `registrationDate` , `created` ,  `avatarName` , `avatarSize` , `avatarFileType` , `avatarLibName` , `avatarType` FROM `users_users`';
	    $query .= ' WHERE ' . str_repeat('`login` = ? OR ', count($login)-1) . '`login` = ?';
	    $result = $this->query($query, $login);

	    while ( $row = $result->fetchRow() ) {
		$alogin = $row['login'];
		unset($row['login']);
		$user_details[$alogin] = $row;
	    }

	    $query  = 'SELECT `user` , `prefName` , `value` FROM `tiki_user_preferences`';
	    if ( $all ) {
		$query .= ' WHERE ' . str_repeat('`user` = ? OR ', count($login)-1) . '`user` = ?';
		$result = $this->query($query, $login);
	    } else {
		$query .= ' WHERE ( `prefName` = ? OR `prefName` = ? ) AND ( ' . str_repeat('`user` = ? OR ', count($login)-1) . '`user` = ? )';
		$result = $this->query($query, array_merge( array('user_information', 'country'), $login));
	    }

	    while ( $row = $result->fetchRow() ) {
		$alogin = $row['user'];
		unset($row['login']);
		$user_preferences[$row['user']][$row['prefName']] = $row['value'];
	    }
	}

	function get_user_preference($user, $name, $default = '') {
	    global $user_preferences;

	    if (!isset($user_preferences[$user][$name])) {
		$query = "select `value` from `tiki_user_preferences` where `prefName`=? and `user`=?";

		$result = $this->query($query, array( "$name", "$user"));

		if ($result->numRows()) {
		    $res = $result->fetchRow();

		    $user_preferences[$user][$name] = $res["value"];
		} else {
		    $user_preferences[$user][$name] = $default;
		}
	    }

	    return $user_preferences[$user][$name];
	}

	function set_user_preference($user, $name, $value) {
	    global $user_preferences;
	    $user_preferences[$user][$name] = $value;
	    $query = "delete from `tiki_user_preferences` where `user`=? and `prefName`=?";
	    $bindvars=array($user,$name);
	    $result = $this->query($query, $bindvars, -1,-1,false);
	    $query = "insert into `tiki_user_preferences`(`user`,`prefName`,`value`) values(?, ?, ?)";
	    $bindvars[]=$value;
	    $result = $this->query($query, $bindvars);
	    return true;
	}

	// This implements all the functions needed to use Tiki
	/*shared*/
	function page_exists($pageName, $casesensitive=false) {
	    $query = "select `pageName` from `tiki_pages` where `pageName` = ?";
	    $result = $this->query($query, array($pageName));

	    // if casesensitive, check the name of the returned page:
	    if ( ($casesensitive) && ($result->numRows()) ) {
		$res = $result->fetchRow();
		if ($res["pageName"] <> $pageName) return 0;
	    }

	    return $result->numRows();
	}

	function page_exists_desc($pageName) {
	    $query = "select `description`  from `tiki_pages`
		where `pageName` = ?";
	    $result = $this->query($query, array( $pageName ));

	    if (!$result->numRows())
		return false;

	    $res = $result->fetchRow();

	    if (!$res["description"])
		$res["description"] = tra('no description');

	    return $res["description"];
	}

	function page_exists_modtime($pageName) {
	    $query = "select `lastModif`  from `tiki_pages`
		where `pageName` = ?";
	    $result = $this->query($query, array( $pageName ));

	    if (!$result->numRows())
		return false;

	    $res = $result->fetchRow();

	    if (!$res["lastModif"])
		$res["lastModif"] = 0;

	    return $res["lastModif"];
	}

	function add_hit($pageName) {
	    $query = "update `tiki_pages` set `hits`=`hits`+1 where `pageName` = ?";
	    $result = $this->query($query, array($pageName));
	    return true;
	}

	function create_page($name, $hits, $data, $lastModif, $comment, $user = 'system', $ip = '0.0.0.0', $description = '', $lang='') {
	    if ($this->page_exists($name))
		return false;

	    // Collect pages before modifying data
	    $pages = $this->get_pages($data);

	    if (!isset($_SERVER["SERVER_NAME"])) {
		$_SERVER["SERVER_NAME"] = $_SERVER["HTTP_HOST"];
	    }

	    if ($lang) {	// not sure it is necessary
		$query = "insert into `tiki_pages`(`pageName`,`hits`,`data`,`lastModif`,`comment`,`version`,`user`,`ip`,`description`,`creator`,`page_size`,`lang`) values(?,?,?,?,?,?,?,?,?,?,?,?)";
		$result = $this->query($query, array($name, (int)$hits, $data, (int)$lastModif, $comment, 1, $user, $ip, $description, $user, (int)strlen($data), $lang ));
	    }
	    else  {
		$query = "insert into `tiki_pages`(`pageName`,`hits`,`data`,`lastModif`,`comment`,`version`,`user`,`ip`,`description`,`creator`,`page_size`) values(?,?,?,?,?,?,?,?,?,?,?)";	
		$result = $this->query($query, array($name, (int)$hits, $data, (int)$lastModif, $comment, 1, $user, $ip, $description, $user, (int)strlen($data)));
	    }

	    $this->clear_links($name);

	    // Pages are collected before adding slashes
	    foreach ($pages as $a_page) {
		$this->replace_link($name, $a_page);
	    }

	    // Update the log
	    if ($name != 'SandBox') {
		$action = "Created";//get_strings tra("Created");

		$query = "insert into `tiki_actionlog`(`action`,`pageName`,`lastModif`,`user`,`ip`,`comment`) values(?,?,?,?,?,?)";
		$result = $this->query($query, array(
			    $action,
			    $name,
			    (int)$lastModif,
			    $user,
			    $ip,
			    $comment
			    ));

		//  Deal with mail notifications.
		include_once('lib/notifications/notificationemaillib.php');
		$foo = parse_url($_SERVER["REQUEST_URI"]);
		$machine = httpPrefix(). dirname( $foo["path"] );
		sendWikiEmailNotification('wiki_page_created', $name, $user, $comment, 1, $data, $machine);
	    }

	    global $feature_score;
	    if ($feature_score == 'y') {
		$this->score_event($user, 'wiki_new');
	    }

	    return true;
	}

	function get_user_pages($user, $max, $who='user') {
	    $query = "select `pageName` from `tiki_pages` where `$who`=?";

	    $result = $this->query($query,array($user),$max);
	    $ret = array();

	    while ($res = $result->fetchRow()) {
		$ret[] = $res;
	    }

	    return $ret;
	}

	function get_user_galleries($user, $max) {
	    $query = "select `name` ,`galleryId`  from `tiki_galleries` where `user`=?";

	    $result = $this->query($query,array($user),$max);
	    $ret = array();

	    while ($res = $result->fetchRow()) {
		$ret[] = $res;
	    }

	    return $ret;
	}

	function get_page_info($pageName) {
	    $query = "select * from `tiki_pages` where `pageName`=?";

	    $result = $this->query($query, array($pageName));

	    if (!$result->numRows())
		return false;
	    else
		return $result->fetchRow();
	}
	function get_page_info_from_id($page_id) {
	    $query = "select * from `tiki_pages` where `page_id`=?";

	    $result = $this->query($query, array($page_id));

	    if (!$result->numRows())
		return false;
	    else
		return $result->fetchRow();
	}


	function get_page_name_from_id($page_id) {
	    $query = "select `pageName`  from `tiki_pages` where `page_id`=?";
	    return $this->getOne($query, array((int)$page_id));
	}

	function get_page_id_from_name($page) {
	    $query = "select `page_id` from `tiki_pages` where `pageName`=?";
	    return $this->getOne($query, array($page));
	}

	function how_many_at_start($str, $car) {
	    $cant = 0;
	    $i = 0;

	    while (($i < strlen($str)) && (isset($str{$i})) && ($str{$i}== $car)) {
		$i++;

		$cant++;
	    }

	    return $cant;
	}

	function parse_data_raw($data) {
	    $data = $this->parse_data($data);

	    $data = str_replace("tiki-index", "tiki-index_raw", $data);
	    return $data;
	}

	function add_pre_handler($name) {
	    if (!in_array($name, $this->pre_handlers)) {
		$this->pre_handlers[] = $name;
	    }
	}

	function add_pos_handler($name) {
	    if (!in_array($name, $this->pos_handlers)) {
		$this->pos_handlers[] = $name;
	    }
	}

	// add a post edit filter which is called when a wiki page is edited and before
	// it is committed to the database (see tiki-handlers.php on its usage)
	function add_postedit_handler($name)
	{
	    if(!in_array($name,$this->postedit_handlers)) {
		$this->postedit_handlers[]=$name;
	    }
	}

	// apply all the post edit handlers to the wiki page data
	function apply_postedit_handlers($data) {
	    // Process editpage_handlers here
	    foreach($this->postedit_handlers as $handler) {
		$data = $handler($data);
	    }
	    return $data;
	}

    // This function handles wiki codes for those special HTML characters
    // that textarea won't leave alone.
    function parse_htmlchar(&$data) {
	// cleaning some user input
	$data = preg_replace("/&(?!([a-z]{1,7};))/", "&amp;", $data);

	// oft-used characters (case insensitive)
	$data = preg_replace("/~bs~/i", "&#92;", $data);
	$data = preg_replace("/~hs~/i", "&nbsp;", $data);
	$data = preg_replace("/~amp~/i", "&amp;", $data);
	$data = preg_replace("/~ldq~/i", "&ldquo;", $data);
	$data = preg_replace("/~rdq~/i", "&rdquo;", $data);
	$data = preg_replace("/~lsq~/i", "&lsquo;", $data);
	$data = preg_replace("/~rsq~/i", "&rsquo;", $data);
	$data = preg_replace("/~c~/i", "&copy;", $data);
	$data = preg_replace("/~--~/", "&mdash;", $data);
	$data = preg_replace("/ -- /", " &mdash; ", $data);
	$data = preg_replace("/~lt~/i", "&lt;", $data);
	$data = preg_replace("/~gt~/i", "&gt;", $data);

	// HTML numeric character entities
	$data = preg_replace("/~([0-9]+)~/", "&#$1;", $data);
    }

    // Reverses parse_pp_np.
    function replace_preparse(&$data, &$preparsed, &$noparsed)
    {
	$data1 = $data;
	$data2 = "";

	// Cook until done.  Handles nested cases.
	while( $data1 != $data2 )
	{
	    $data1 = $data;
	    if (isset($noparsed["key"]) and count($noparsed["key"]) and count($noparsed["key"]) == count($noparsed["data"]))
	    { 
		$data = preg_replace($noparsed["key"], $noparsed["data"], $data);
	    }

	    if (isset($preparsed["key"]) and count($preparsed["key"]) and count($preparsed["key"]) == count($preparsed["data"]))
	    {
		$data = preg_replace($preparsed["key"], $preparsed["data"], $data);
	    }
	    $data2 = $data;
	}
    }

    function plugin_match(&$data, &$plugins)
    {
    global $feature_wiki_plugins_allcaps;
    if (!empty($feature_wiki_plugins_allcaps) && $feature_wiki_plugins_allcaps == 'y') {
	$matcher = "/\{([A-Z]+)\(|~pp~|~np~|&lt;[pP][rR][eE]&gt;/";
    } else {
    	$matcher = "/\{([A-Z]+)\(|~pp~|~np~|&lt;[pP][rR][eE]&gt;/i";
    }

	preg_match( $matcher, $data, $plugins );

	/*
	   print "<pre>Plugin match begin:";
	   print_r( $plugins );
	   print "</pre>";
	 */

	// Check to make sure there was a match.
	if(
		count( $plugins ) > 0 &&
		count( $plugins[0] )  > 0
	  )
	{
	    // If it is a true plugin
	    if( $plugins[0][0] == "{" )
	    {
		$pos = strpos( $data, $plugins[0] ); // where plugin starts

		$pos_end = $pos+strlen($plugins[0]); // where character after ( is

		// Here we're going to look for the end of the arguments for the plugin.

		$i = $pos_end;
		$last_data = strlen($data);

		// We start with one open curly brace, and one open paren.
		$curlies = 1;
		$parens = 1;

		// While we're not at the end of the string, and we still haven't found both closers
		while( $i < $last_data )
		{
		    //print "<pre>Data char: $data[$i], $curlies, $parens\n.</pre>\n";
		    if( $data[$i] == "{" )
		    {
			$curlies++;
		    } else if( $data[$i] == "(" ) {
			$parens++;
		    } else if( $data[$i] == "}" ) {
			$curlies--;
		    } else if( $data[$i] == ")" ) {
			$parens--;
		    }

		    // If we found the end of the match...
		    if( $curlies == 0 && $parens == 0 )
		    {
			break;
		    }

		    $i++;
		}

		if( $curlies == 0 && $parens == 0 )
		{
		    $plugins[2] = (string) substr($data, $pos_end, $i - $pos_end - 1);
		    $plugins[0] = $plugins[0] . (string) substr($data, $pos_end, $i - $pos_end + 1);
		    /*
		       print "<pre>Match found: ";
		       print( $plugins[2] );
		       print "</pre>";
		     */
		}
	    } else {
		$plugins[1] = $plugins[0];
		$plugins[2] = "";
	    }
	}

	/*
	   print "<pre>Plugin match end:";
	   print_r( $plugins );
	   print "</pre>";
	 */

    }

    // This recursive function handles pre- and no-parse sections and plugins
    function parse_first(&$data, &$preparsed, &$noparsed) {
	global $dbTiki;

	if( strlen( $data ) <= 1 )
	{
	    return;
	}

	// Handle pre- and no-parse sections
	//$this->parse_pp_np($data, $preparsed, $noparsed);

	// Find the plugins
	$this->plugin_match( $data, $plugins );

	$data1 = $data;
	$data2 = "";

	// Cook until done.
	while( count($plugins) > 0 && ( $data1 != $data2 ) )
	{
	    $data1 = $data;
	    $plugin_start = $plugins[0];

	    /*
	       print "<pre>real data: :".htmlspecialchars( $data ) .":</pre>";

	       print "<pre>plugins:";
	       print_r( $plugins );
	       print "</pre>";
	       print "<pre>start: :".htmlspecialchars( $plugin_start ) .":</pre>";
	     */

	    if( count($plugins) > 1 )
	    {
		$plugin = $plugins[1];
		$plugin_start_base = '{' . $plugins[1] . '(';
	    }

	    // print "<pre>plugin: :".htmlspecialchars( $plugin ) .":</pre>";

	    $pos = strpos( $data, $plugins[0] ); // where the plugin starts

	    if( $plugins[2] )
	    {
		// where the part after the plugin arguments starts
		$pos_middle = strpos( $data, $plugins[2] ) + strlen( $plugins[2] ) ;
	    } else {
		$pos_middle = $pos + strlen( $plugins[0] );
	    }	

	    // print "<pre>pos's: :$pos, $pos_middle:</pre>";

	    // process "short" plugins here: {PLUGIN(par1=>val1)/} - melmut
	    if( preg_match("/\/ *\}$/",$plugin_start) )
	    {
		$plugin_end='';
		$pos_end=$pos_start+strlen($plugin_start);
	    } else if( preg_match( "/^ *~pp~|^ *~np~/", $plugin_start ) ) {
		$plugin_end = preg_replace( '/^(.)/', '$1/', $plugin_start );
		$pos_end = strpos($data, $plugin_end, $pos); // where plugin data ends
	    } else if( preg_match( "/^ *&lt;[pP][rR][eE]&gt;/", $plugin_start ) ) {
		preg_match("/&lt;\/[pP][rR][eE]&gt;/", $data, $plugin_ends, 0, $pos); // where plugin data ends
		$plugin_end = $plugin_ends[0];
		$pos_end = strpos($data, $plugin_end, $pos); // where plugin data ends
	    } else {
		$plugin_end = '{' . $plugin . '}';
		$pos_end = strpos($data, $plugin_end, $pos_middle); // where plugin data ends
	    }

	    /*
	       print "<pre>pos's2: :$pos, $pos_middle, $pos_end:</pre>";
	       print "<pre>plugin_end: :".htmlspecialchars( $plugin_end ) .":</pre>";
	     */

	    // Extract the plugin data
	    $plugin_data_len = $pos_end - $pos - strlen($plugins[0]);
	    $plugin_data = substr($data, $pos + strlen($plugin_start), $plugin_data_len);

	    /*
	       print "<pre>data: :".htmlspecialchars( $plugin_data ) .":</pre>";
	       print "<pre>end: :".htmlspecialchars( $plugin_end ) .":</pre>";
	     */

	    if( $plugin_data && preg_match( "/^ *&lt;[pP][rR][eE]&gt;|^ *~pp~|^ *~np~/", $plugin_start ) )
		// ~pp~ type "plugins"
	    {
		$key = md5($this->genPass());
		$noparsed["key"][] = "/". preg_quote($key)."/";

		if( $plugin_start == "~pp~" )
		{
		    $noparsed["data"][] = "<pre>" . $plugin_data . "</pre>";
		} else if( preg_match( "/^ *&lt;[pP][rR][eE]&gt;/", $plugin_start ) ) {
		    preg_match( "/^ *&lt;([pP][rR][eE])&gt;/", $plugin_start, $plugins );
		    $plugin_start2 = $plugins[1];
		    preg_match( "/^ *&lt;\/([pP][rR][eE])&gt;/", $plugin_end, $plugins );
		    $plugin_end2 = $plugins[1];
		    $noparsed["data"][] = "<" . $plugin_start2 . ">" . $plugin_data . "</" . $plugin_end2 . ">";
		} else {
		    $noparsed["data"][] = $plugin_data;
		}

		// Replace plugin section with its output in data
		$data = substr_replace($data, $key, $pos, $pos_end - $pos + strlen($plugin_end));
	    } else {
		// print "<pre>args1: :".htmlspecialchars( $plugins[2] ) .":</pre>";
		// Handle nested plugins in the arguments.
		$this->parse_first($plugins[2], $preparsed, $noparsed);
		// print "<pre>args2: :".htmlspecialchars( $plugins[2] ) .":</pre>";

		// Normal plugins

		// Construct plugin file pathname
		$php_name = 'lib/wiki-plugins/wikiplugin_';
		$php_name .= strtolower($plugins[1]). '.php';

		// Construct plugin function name
		$func_name = 'wikiplugin_' . strtolower($plugins[1]);

		// Construct argument list array
		$params = split(',', trim($plugins[2]));
		$arguments = array();

		foreach ($params as $param) {
		    // the following str_replace line is to decode the &gt; char when html is turned off
		    // perhaps the plugin syntax should be changed in 1.8 not to use any html special chars
		    $decoded_param = str_replace('&gt;', '>', $param);
		    $parts = split( '=>?', $decoded_param );

		    if (isset($parts[0]) && isset($parts[1])) {
			$name = trim($parts[0]);
			$argument = trim($parts[1]);
			// the following preg_replace removes more unwanted css attributes passed after ";" (including)
			$arguments[$name] = preg_replace('/([^\;]+)\;.*/','$1;',$argument);
		    }
		}

		if (file_exists($php_name)) {
		    include_once ($php_name);

		    // We store CODE stuff out of the way too, but then process it as a plugin as well.
		    if( preg_match( '/^ *\{CODE\(/', $plugin_start ) )
		    {
			$ret = $func_name($plugin_data, $arguments);

			// Pull the np out.
			preg_match( "/~np~(.*)~\/np~/s", $ret, $stuff );

			$key = md5($this->genPass());
			$noparsed["key"][] = "/". preg_quote($key)."/";
			$noparsed["data"][] = $stuff[1];

			$ret = preg_replace( "/~np~.*~\/np~/s", $key, $ret );

		    } else {
			// Handle nested plugins.
			$this->parse_first($plugin_data, $preparsed, $noparsed);

			$ret = $func_name($plugin_data, $arguments);
		    }
		} else {
		    // Handle nested plugins.
		    $this->parse_first($plugin_data, $preparsed, $noparsed);

		    $ret = tra( "__WARNING__: No such module $plugin! " ) . $plugin_data;
		}

		// Handle pre- & no-parse sections and plugins inserted by this plugin
		$this->parse_first($ret, $preparsed, $noparsed);
		//$ret = $this->parse_data($ret);

		// Replace plugin section with its output in data
		$data = substr_replace($data, $ret, $pos, $pos_end - $pos + strlen($plugin_end));

	    }

	    // Find the plugins
	    // note: [1] is plugin name, [2] is plugin arguments
	    $this->plugin_match( $data, $plugins );

	    $data2 = $data;

	} // while

	// print "<pre>real done data: :".htmlspecialchars( $data ) .":</pre>";

	// Handle pre- and no-parse sections
	//$this->parse_pp_np($data, $preparsed, $noparsed);
    }

    // Replace hotwords in given line
    function replace_hotwords($line, $words) {
	global $feature_hotwords;
	global $feature_hotwords_nw;
	$hotw_nw = ($feature_hotwords_nw == 'y') ? "target='_blank'" : '';

	// Replace Hotwords
	if ($feature_hotwords == 'y') {
	    foreach ($words as $word => $url) {
		// \b is a word boundary, \s is a space char
		$line = preg_replace("/(=(\"|')[^\"']*[ \n\t\r\,\;])$word([ \n\t\r\,\;][^\"']*(\"|'))/i","$1:::::$word,:::::$3",$line);
		$line = preg_replace("/([ \n\t\r\,\;]|^)$word($|[ \n\t\r\,\;])/i","$1<a class=\"wiki\" href=\"$url\" $hotw_nw>$word</a>$2",$line);
		$line = preg_replace("/:::::$word,:::::/i","$word",$line);
	    }
	}
	return $line;
    }

    // Make plain text URIs in text into clickable hyperlinks
    function autolinks($text) {
	//	check to see if autolinks is enabled before calling this function
	//		global $feature_autolinks;

	//		if ($feature_autolinks == "y") {

	// add a space so we can match links starting at the beginning of the first line
	$text = " " . $text;
	// match prefix://suffix, www.prefix.suffix/optionalpath, prefix@suffix
	$patterns = array("#([\n ])([a-z0-9]+?)://([^, \n\r]+)#i", "#([\n ])www\.([a-z0-9\-]+)\.([a-z0-9\-.\~]+)((?:/[^, \n\r]*)?)#i", "#([\n ])([a-z0-9\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "#([\n ])magnet\:\?([^, \n\r]+)#i");
	$replacements = array("\\1<a class='wiki' href=\"\\2://\\3\">\\2://\\3</a>", "\\1<a class='wiki' href=\"http://www.\\2.\\3\\4\">www.\\2.\\3\\4</a>", "\\1<a class='wiki' href=\"mailto:\\2@\\3\">\\2@\\3</a>", "\\1<a class='wiki' href=\"magnet:?\\2\">magnet:?\\2</a>");
	$text = preg_replace($patterns, $replacements, $text);
	// strip the space we added
	$text = substr($text, 1);
	return $text;

	//		} else {
	//			return $text;
	//		}
    }


    //Updates a dynamic variable found in some object
    /*Shared*/ function update_dynamic_variable($name,$value) {
	$query = "delete from `tiki_dynamic_variables` where `name`=?";
	$this->query($query,array($name),-1,-1,false);
	$query = "insert into `tiki_dynamic_variables`(`name`,`data`) values(?,?)";
	$this->query($query,Array($name,$value));
	return true;
    }


    // split string into a list of
    function split_tag($string) {
	$_splts = split('&quot;', $string);
	$inside = FALSE;
	$cleanup= TRUE;  // @todo: make this an option for other code
	$parts = array();
	$index=0;

	foreach ($_splts as $i)  {
	    if ($cleanup) {
		$i = str_replace('}', '', $i);
		$i = str_replace('{', '', $i);
		$i = str_replace('\'', '', $i);
	    }

	    if ($inside) {  // inside "foo bar" - append
		if ($index>0) {
		    $parts[$index-1] .= $i;
		} else {    // else: first element (should never happen)
		    $parts[] = $i;
		}
	    } else {        //
		$_spl = split(" ", $i);
		foreach($_spl as $j) {
		    $parts[$index++] = $j;
		}
	    }
	    $inside = ! $inside;
	}
	return $parts;
    }

    function split_assoc_array($parts, $assoc) {
	//$assoc = array();
	foreach($parts as $part) {
	    $res=array();
	    $assoc[$part] = '';
	    preg_match("/(\w+)\s*=\s*(.*)/", $part, $res);
	    if ($res) {
		$assoc[$res[1]] = $res[2];
	    }
	}
	return $assoc;
    }

    /**
     * close_blocks - Close out open paragraph, lists, and div's
     *
     * During parse_data, information is kept on blocks of text (paragraphs, lists, divs)
     * that need to be closed out. This function does that, rather than duplicating the
     * code inline.
     *
     * @param	$data			- Output data
     * @param	$in_paragraph		- TRUE if there is an open paragraph
     * @param	$listbeg		- array of open list terminators
     * @param	$divdepth		- array indicating how many div's are open
     * @param	$close_paragraph	- TRUE if open paragraph should be closed.
     * @param	$close_lists		- TRUE if open lists should be closed.
     * @param	$close_divs		- TRUE if open div's should be closed.
     */
    /* private */
    function close_blocks(&$data,
	    &$in_paragraph,
	    &$listbeg,
	    &$divdepth,
	    $close_paragraph,
	    $close_lists,
	    $close_divs)
    {
	$closed = 0;	// Set to non-zero if something has been closed out
	// Close the paragraph if inside one.
	if ($close_paragraph && $in_paragraph) {
	    $data .= "</p>";	
	    $in_paragraph = 0;
	    $closed++;
	}
	// Close open lists
	if ($close_lists) {
	    while (count($listbeg)) {
		$data .= array_shift($listbeg);
		$closed++;
	    }
	}

	// Close open divs
	if ($close_divs) {
	    $temp_max = count($divdepth);
	    for ($i = 1; $i <= $temp_max; $i++) {
		$data .= '</div>';
		$closed++;
	    }
	}

	if ($closed) {
	    $data .= "\n";
	}

	return $closed;
    }

    //PARSEDATA
    function parse_data($data) {
	global $page_regex;

	global $slidemode;
//	global $feature_hotwords; // doesn't seem to be used in this function
	global $feature_autolinks;
	global $cachepages;
	global $ownurl_father;
	global $feature_drawings;
	global $tiki_p_admin_drawings;
	global $tiki_p_edit_drawings;
	global $tiki_p_edit_dynvar;
	global $feature_wiki_pictures;
	global $tiki_p_upload_picture;
	global $feature_wiki_plurals;
	global $feature_wiki_tables;
	global $page;
	global $page_ref_id;
	global $rsslib;
	global $dbTiki;
	global $structlib;
	global $user;
	global $tikidomain;
	global $feature_wikiwords;
	global $feature_wiki_paragraph_formatting;
	global $feature_wikiwords_usedash;
	global $feature_multilingual;
	global $feature_best_language;

	// Process pre_handlers here
	if (is_array($this->pre_handlers)) {
	    foreach ($this->pre_handlers as $handler) {
		$data = $handler($data);
	    }
	}

	// Handle pre- and no-parse sections and plugins
	$preparsed = array('data'=>array(),'key'=>array());
	$noparsed = array('data'=>array(),'key'=>array());
	$this->parse_first($data, $preparsed, $noparsed);

	// Extract [link] sections (to be re-inserted later)
	$noparsedlinks = array();

	// This section matches [...].
	// Added handling for [[foo] sections.  -rlpowell
	preg_match_all("/(?<!\[)\[([^\[][^\]]+)\]/", $data, $noparseurl);

	    foreach (array_unique($noparseurl[1])as $np) {
		$key = md5($this->genPass());

		$aux["key"] = $key;
		$aux["data"] = $np;
		$noparsedlinks[] = $aux;
		$data = str_replace("$np", $key, $data);
	    }

	    // Replace special characters
	    //done after url catching because otherwise urls of dyn. sites will be modified
	    $this->parse_htmlchar($data);

	    // Now replace a TOC
	    preg_match_all("/\{toc\s?(order=(desc|asc))?\s?(showdesc=(0|1))?\s?(shownum=(0|1))?\s?\}/i", $data, $tocs);

	    //If there are instances of {toc} on this page
	    if (count($tocs[0]) > 0) {
		$order = 'asc';
		$showdesc = false;
		$shownum = false;
		if ($tocs[2][0] == 'desc') {
		    $order = 'desc';
		}
		if ($tocs[4][0] == 1) {
		    $showdesc = true;
		}
		if ($tocs[6][0] == 1) {
		    $shownum = true;
		}
		include_once ("lib/structures/structlib.php");
		//And we are currently viewing a structure
		$page_info = $structlib->s_get_page_info($page_ref_id);
		if (isset($page_info)) {
		    $html = $structlib->get_toc($page_ref_id,$order,$showdesc,$shownum);

		    // Loop over all the case-specific versions of {toc} used
		    // (if the user is consistent, this is a loop of count 1)
		    $temp_max = count($tocs[0]);
		    for ($i = 0; $i < $temp_max; $i++) {
			$data = str_replace($tocs[0], $html, $data);
		    }
		}
		//Dont display the {toc} string for non structure pages
		else {
		    $temp_max = count($tocs[0]);
		    for ($i = 0; $i < $temp_max; $i++) {
			$data = str_replace($tocs[0], '', $data);
		    }
		}
	    }

	    // Now search for images uploaded by users
	    if ($feature_wiki_pictures == 'y') {
		preg_match_all("/\{picture file=([^\}]+)\}/", $data, $pics);

		$temp_max = count($pics[0]);
		for ($i = 0; $i < $temp_max; $i++) {
		    // Check if the image exists
		    $name = $pics[1][$i];
		    if ($tikidomain) {
			$name = preg_replace("~img/wiki_up/~","img/wiki_up/$tikidomain/",$name);
		    }
		    if (file_exists($name)) {
			// Replace by the img tag to show the image
			$repl = "<img src='$name' alt='$name' />";
		    } else {
			$repl = tra('picture not found')." $name";
		    }

		    // Replace by $repl
		    $data = str_replace($pics[0][$i], $repl, $data);
		}
	    }

	    //$data = strip_tags($data);
	    // BiDi markers
	    $bidiCount = 0;
	    $bidiCount = preg_match_all("/(\{l2r\})/", $data, $pages);
	    $bidiCount += preg_match_all("/(\{r2l\})/", $data, $pages);

	    $data = preg_replace("/\{l2r\}/", "<div dir='ltr'>", $data);
	    $data = preg_replace("/\{r2l\}/", "<div dir='rtl'>", $data);
	    $data = preg_replace("/\{lm\}/", "&lrm;", $data);
	    $data = preg_replace("/\{rm\}/", "&rlm;", $data);
	    // smileys
	    $data = $this->parse_smileys($data);

	    // Replace links to slideshows
	    if ($feature_drawings == 'y') {
		// Replace drawings
		// Replace rss modules
		$pars = parse_url($_SERVER["REQUEST_URI"]);

		$pars_parts = split('/', $pars["path"]);
		$pars = array();

		$temp_max = count($pars_parts) - 1;
		for ($i = 0; $i < $temp_max; $i++) {
		    $pars[] = $pars_parts[$i];
		}

		$pars = join('/', $pars);

		if (preg_match_all("/\{draw +name=([A-Za-z_\-0-9]+) *\}/", $data, $draws)) {
		    //$this->invalidate_cache($page);
		    $temp_max = count($draws[0]);
		    for ($i = 0; $i < $temp_max; $i++) {
			$id = $draws[1][$i];

			$repl = '';
			if ($tikidomain) {
			    $name = $tikidomain.'/'.$id . '.gif';
			} else {
			    $name = $id . '.gif';
			}
			if ($tikidomain) {
			    $name = $tikidomain.'/'.$name;
			}
			if (file_exists("img/wiki/$name")) {
			    if ($tiki_p_edit_drawings == 'y' || $tiki_p_admin_drawings == 'y') {
				$repl = "<a href='#' onClick=\"javascript:window.open('tiki-editdrawing.php?page=" . urlencode($page). "&amp;path=$pars&amp;drawing={$id}','','menubar=no,width=252,height=25');\"><img border='0' src='img/wiki/$name' alt='click to edit' /></a>";
			    } else {
				$repl = "<img border='0' src='img/wiki/$name' alt='a drawing' />";
			    }
			} else {
			    if ($tiki_p_edit_drawings == 'y' || $tiki_p_admin_drawings == 'y') {
				$repl = "<a class='wiki' href='#' onClick=\"javascript:window.open('tiki-editdrawing.php?page=" . urlencode($page). "&amp;path=$pars&amp;drawing={$id}','','menubar=no,width=252,height=25');\">click here to create draw $id</a>";
			    } else {
				$repl = tra('drawing not found');
			    }
			}

			$data = str_replace($draws[0][$i], $repl, $data);
		    }
		}
	    }

	    // Replace cookies
	    if (preg_match_all("/\{cookie\}/", $data, $rsss)) {
		$temp_max = count($rsss[0]);
		for ($i = 0; $i < $temp_max; $i++) {
		    $cookie = $this->pick_cookie();

		    $data = str_replace($rsss[0][$i], $cookie, $data);
		}
	    }

	    // Replace dynamic variables
	    // Dynamic variables are similar to dynamic content but they are editable
	    // from the page directly, intended for short data, not long text but text
	    // will work too
	    //     Now won't match HTML-style '%nn' letter codes.
	    if (preg_match_all("/%([^% 0-9][^% 0-9][^% ]*)%/",$data,$dvars)) {
		// remove repeated elements
		$dvars = array_unique($dvars[1]);
		// Now replace each dynamic variable by a pair composed of the
		// variable value and a text field to edit the variable. Each
		foreach($dvars as $dvar) {
		    $query = "select `data` from `tiki_dynamic_variables` where `name`=?";
		    $result = $this->query($query,Array($dvar));
		    if($result->numRows()) {
			$value = $result->fetchRow();
			$value = $value["data"];
		    } else {
			//Default value is NULL
			$value = "NaV";
		    }
		    // Now build 2 divs
		    $id = 'dyn_'.$dvar;

		    if(isset($tiki_p_edit_dynvar)&& $tiki_p_edit_dynvar=='y') {
			$span1 = "<span  style='display:inline;' id='dyn_".$dvar."_display'><a class='dynavar' onClick='javascript:toggle_dynamic_var(\"$dvar\");' title='".tra('Click to edit dynamic variable').": $dvar'>$value</a></span>";
			$span2 = "<span style='display:none;' id='dyn_".$dvar."_edit'><input type='text' name='dyn_".$dvar."' value='".$value."' /></span>";
		    } else {
			$span1 = "<span class='dynavar' style='display:inline;' id='dyn_".$dvar."_display'>$value</span>";
			$span2 = '';
		    }
		    $html = $span1.$span2;
		    //It's important to replace only once
		    $dvar_preg = preg_quote( $dvar );
		    $data = preg_replace("+%$dvar_preg%+",$html,$data,1);
		    //Further replacements only with the value
		    $data = str_replace("%$dvar%",$value,$data);

		}
		//At the end put an update button
		//<br /><div align="center"><input type="submit" name="dyn_update" value="'.tra('Update variables').'"/></div>
		$data='<form method="post" name="dyn_vars">'.$data.'<div style="display:none;"><input type="submit" name="_dyn_update" value="'.tra('Update variables').'"/></div></form>';
	    }

	    // Replace dynamic content occurrences
	    if (preg_match_all("/\{content +id=([0-9]+)\}/", $data, $dcs)) {
		$temp_max = count($dcs[0]);
		for ($i = 0; $i < $temp_max; $i++) {
		    $repl = $this->get_actual_content($dcs[1][$i]);

		    $data = str_replace($dcs[0][$i], $repl, $data);
		}
	    }

	    // Replace Dynamic content with random selection
	    if (preg_match_all("/\{rcontent +id=([0-9]+)\}/", $data, $dcs)) {
		include_once("dcs/dcslib.php");
		$temp_max = count($dcs[0]);
		for ($i = 0; $i < $temp_max; $i++) {
		    $repl = $dcslib->get_random_content($dcs[1][$i]);

		    $data = str_replace($dcs[0][$i], $repl, $data);
		}
	    }

	    // Replace boxes
	    $data = preg_replace("/\^([^\^]+)\^/", "<div class=\"simplebox\">$1</div>", $data);
	    // Replace colors ~~color:text~~
	    $data = preg_replace("/\~\~([^\:]+):([^\~]+)\~\~/", "<span style=\"color:$1;\">$2</span>", $data);
	    // Underlined text
	    $data = preg_replace("/===([^\=]+)===/", "<span style=\"text-decoration:underline;\">$1</span>", $data);
	    // Center text
	    $data = preg_replace("/::(.+?)::/", "<div align=\"center\">$1</div>", $data);

	    // New syntax for wiki pages ((name|desc)) Where desc can be anything
	    // preg_match_all("/\(\(($page_regex)\|(.+?)\)\)/", $data, $pages);
	    // match ((name|desc)) as well as ((name|))
	    preg_match_all("/\(\(($page_regex)\|(.*?)\)\)/", $data, $pages);

	    $temp_max = count($pages[1]);
	    for ($i = 0; $i < $temp_max; $i++) {
		$pattern = $pages[0][$i];

		$pattern = preg_quote($pattern, "/");

		$pattern = "/" . $pattern . "/";

		// Replace links to external wikis
		$repl2 = true;

		if (strstr($pages[1][$i], ':')) {
		    $wexs = explode(':', $pages[1][$i]);

		    if (count($wexs) == 2) {
			$wkname = $wexs[0];

			if ($this->db->getOne("select count(*) from `tiki_extwiki` where `name`=?",array($wkname)) == 1) {
			    $wkurl = $this->db->getOne("select `extwiki`  from `tiki_extwiki` where `name`=?",array($wkname));

			    $wkurl = '<a href="' . str_replace('$page', urlencode($wexs[1]), $wkurl). '" class="wiki external">' . $wexs[1] . '</a>';
			    $data = preg_replace($pattern, "$wkurl", $data);
			    $repl2 = false;
			}
		    }
		}

		if ($repl2) {
		    // 24-Jun-2003, by zaufi
		    // TODO: future optimize: get page description and modification time at once.

		    // text[0] = link description (previous format)
		    // text[1] = timeout in seconds (new field)
		    // text[2..N] = drop
		    $text = explode("|", $pages[5][$i]);

		    if ($desc = $this->page_exists_desc($pages[1][$i])) {
			$desc = preg_replace("/([ \n\t\r\,\;]|^)([A-Z][a-z0-9_\-]+[A-Z][a-z0-9_\-]+[A-Za-z0-9\-_]*)($|[ \n\t\r\,\;\.])/s", "$1))$2(($3", $desc);
			$uri_ref = "tiki-index.php?page=" . urlencode($pages[1][$i]);

			// check to see if desc is blank in ((page|desc))
			if (strlen(trim($text[0])) > 0) {
				$linktext = $text[0];
			} elseif ($desc != tra('no description')) {
				// desc is blank; use the page description instead
				$linktext = $pages[1][$i] . ': ' . $desc;
			} else {
				// there is no page description
				$linktext = $pages[1][$i];
			}
		    global $feature_wiki_jstooltips;
		    if ($desc != tra('no description')) {
			if (!empty($feature_wiki_jstooltips) && $feature_wiki_jstooltips == 'y') {
			$repl = '<a href="'.$uri_ref.'" class="wiki" onmouseover="return overlib(\''.htmlspecialchars($desc).'\',WIDTH,-1);" onmouseout="nd();">' . $linktext . '</a>';
			} else {
			$repl = '<a title="'.$desc.'" href="'.$uri_ref.'" class="wiki">' . $linktext . '</a>';
			}
		    } else {
		    	$repl = '<a href="'.$uri_ref.'" class="wiki">' . $linktext . '</a>';
		    }

			// Check is timeout expired?
			if (isset($text[1]) && (time() - intval($this->page_exists_modtime($pages[1][$i]))) < intval($text[1]))
			    // Append small 'new' image. TODO: possible 'updated' image more suitable...
			    $repl .= '&nbsp;<img src="img/icons/new.gif" border="0" alt="'.tra("new").'" />';
		    } else {
			$uri_ref = "tiki-editpage.php?page=" . urlencode($pages[1][$i]);
			
			global $tiki_p_edit;
			if ($tiki_p_edit == 'y') {
				$create_page_link = '<a href="'.$uri_ref.'" title="'.tra("Create page:")." ".urlencode($pages[1][$i]).'" class="wiki wikinew">?</a>';
			} else {
				$create_page_link = '';
			}

		    $repl = (strlen(trim($text[0])) > 0 ? $text[0] : $pages[1][$i]) . $create_page_link;
		    }

		    $data = preg_replace($pattern, "$repl", $data);
		}
	    }

	    // New syntax for wiki pages ((name)) Where name can be anything
	    preg_match_all("/\(\(($page_regex)\)\)/", $data, $pages);

	    foreach (array_unique($pages[1])as $page_parse) {
		$repl2 = true;

		if (strstr($page_parse, ':')) {
		    $wexs = explode(':', $page_parse);

		    if (count($wexs) == 2) {
			$wkname = $wexs[0];

			if ($this->db->getOne("select count(*) from `tiki_extwiki` where `name`=?",array($wkname)) == 1) {
			    $wkurl = $this->db->getOne("select `extwiki`  from `tiki_extwiki` where `name`=?",array($wkname));

			    $wkurl = '<a href="' . str_replace('$page', urlencode($wexs[1]), $wkurl). '" class="wiki external">' . $wexs[1] . '</a>';
			    $data = preg_replace("/\(\($page_parse\)\)/", "$wkurl", $data);
			    $repl2 = false;
			}
		    }
		}

	    if ($repl2) {
		if ($desc = $this->page_exists_desc($page_parse)) {
		    $desc = preg_replace("/([ \n\t\r\,\;]|^)([A-Z][a-z0-9_\-]+[A-Z][a-z0-9_\-]+[A-Za-z0-9\-_]*)($|[ \n\t\r\,\;\.])/s", "$1))$2(($3", $desc);
		    global $feature_wiki_jstooltips;
		    $bestLang = ($feature_multilingual == 'y' && $feature_best_language == 'y')? "&amp;bl" : ""; // to choose the best page language
		    if ($desc != tra('no description')) {
		    	if (!empty($feature_wiki_jstooltips) && $feature_wiki_jstooltips == 'y') {
		    		$repl = '<a href="tiki-index.php?page=' . urlencode($page_parse).$bestLang. '" class="wiki"  onmouseover="return overlib(\''.htmlspecialchars($desc).'\',WIDTH,-1);" onmouseout="nd();">' . $page_parse. '</a>';
		    	} else {
		    $repl = "<a title=\"$desc\" href='tiki-index.php?page=" . urlencode($page_parse).$bestLang. "' class='wiki'>$page_parse</a>";
		    	}
		    } else {
		    	$repl = "<a href='tiki-index.php?page=" . urlencode($page_parse).$bestLang. "' class='wiki'>$page_parse</a>";
		    }
		} else {
		    global $tiki_p_edit;
		    if ($tiki_p_edit == 'y') {
		    $repl = $page_parse.'<a href="tiki-editpage.php?page=' . urlencode($page_parse). '" title="'.tra("Create page:").' '.urlencode($page_parse).'"  class="wiki wikinew">?</a>';
		    } else {
		    	$create_page_link = '';
		    }
		}
	    }
	}

	// Links to internal pages
	// If they are parenthesized then don't treat as links
	// Prevent ))PageName(( from being expanded \"\'
	//[A-Z][a-z0-9_\-]+[A-Z][a-z0-9_\-]+[A-Za-z0-9\-_]*
	if ($feature_wikiwords == 'y') {
	    // The first part is now mandatory to prevent [Foo|MyPage] from being converted!
	    if ($feature_wikiwords_usedash == 'y') {
		preg_match_all("/([ \n\t\r\,\;]|^)([A-Z][a-z0-9_\-]+[A-Z][a-z0-9_\-]+[A-Za-z0-9\-_]*)($|[ \n\t\r\,\;\.])/", $data, $pages);
	    } else {
		preg_match_all("/([ \n\t\r\,\;]|^)([A-Z][a-z0-9]+[A-Z][a-z0-9]+[A-Za-z0-9]*)($|[ \n\t\r\,\;\.])/", $data, $pages);
	    }
		$words = $this->get_hotwords();
		foreach (array_unique($pages[2])as $page_parse) {
		    if (!array_key_exists($page_parse, $words)) {
			if ($desc = $this->page_exists_desc($page_parse)) {
			    //$desc = preg_replace("/([ \n\t\r\,\;]|^)([A-Z][a-z0-9_\-]+[A-Z][a-z0-9_\-]+[A-Za-z0-9\-_]*)($|[ \n\t\r\,\;\.])/s", "$1))$2(($3", $desc);
			    global $feature_wiki_jstooltips;
			    if ($desc != tra('no description')) {
				if (!empty($feature_wiki_jstooltips) && $feature_wiki_jstooltips == 'y') {
				    $repl = '<a href="tiki-index.php?page=' . urlencode($page_parse). '" class="wiki" onmouseover="return overlib(\''.htmlspecialchars($desc).'\',WIDTH,-1);" onmouseout="nd();">' . $page_parse . '</a>';
				} else {
				    $repl = '<a title="' . htmlspecialchars($desc) . '" href="tiki-index.php?page=' . urlencode($page_parse). '" class="wiki">' . $page_parse . '</a>';
				}
			    } else {
			    	$repl = '<a href="tiki-index.php?page=' . urlencode($page_parse). '" class="wiki">' . $page_parse . '</a>';
			    }
			} elseif ($feature_wiki_plurals == 'y' && $this->get_locale() == 'en_US') {
# Link plural topic names to singular topic names if the plural
# doesn't exist, and the language is english
			    $plural_tmp = $page_parse;
# Plurals like policy / policies
			    $plural_tmp = preg_replace("/ies$/", "y", $plural_tmp);
# Plurals like address / addresses
			    $plural_tmp = preg_replace("/sses$/", "ss", $plural_tmp);
# Plurals like box / boxes
			    $plural_tmp = preg_replace("/([Xx])es$/", "$1", $plural_tmp);
# Others, excluding ending ss like address(es)
			    $plural_tmp = preg_replace("/([A-Za-rt-z])s$/", "$1", $plural_tmp);
			    if($desc = $this->page_exists_desc($plural_tmp)) {
				// $desc = preg_replace("/([ \n\t\r\,\;]|^)([A-Z][a-z0-9_\-]+[A-Z][a-z0-9_\-]+[A-Za-z0-9\-_]*)($|[ \n\t\r\,\;\.])/s", "$1))$2(($3", $desc);
				// $repl = "<a title=\"".$desc."\" href=\"tiki-index.php?page=$plural_tmp\" class=\"wiki\" title=\"spanner\">$page_parse</a>";
				$repl = "<a title='".$desc."' href='tiki-index.php?page=$plural_tmp' class='wiki'>$page_parse</a>";
			    } else {
					global $tiki_p_edit;
					if ($tiki_p_edit == 'y') {
						$create_page_link = '<a href="tiki-editpage.php?page='.urlencode($page_parse).'" title="'.tra("Create page:").' '.urlencode($page_parse).'" class="wiki wikinew">?</a>';
					} else {
						$create_page_link = '';
					}
			    $repl = $page_parse.$create_page_link;
			    }
			} else {
				global $tiki_p_edit;
				if ($tiki_p_edit == 'y') {
					$create_page_link = '<a href="tiki-editpage.php?page='.urlencode($page_parse).'" title="'.tra("Create page:").' '.urlencode($page_parse).'" class="wiki wikinew">?</a>';
				} else {
					$create_page_link = '';
				}
			    $repl = $page_parse.$create_page_link;
			}

			$data = preg_replace("/([ \n\t\r\,\;]|^)$page_parse($|[ \n\t\r\,\;\.])/", "$1" . "$repl" . "$2", $data);
			//$data = str_replace($page_parse,$repl,$data);
			}
		}
	}

	    // This protects ))word((, I think?
	    $data = preg_replace("/([ \n\t\r\,\;]|^)\)\)([^\(]+)\(\(($|[ \n\t\r\,\;\.])/", "$1" . "$2" . "$3", $data);

	    // reinsert hash-replaced links into page
	    foreach ($noparsedlinks as $np) {
		$data = str_replace($np["key"], $np["data"], $data);
	    }

	    // TODO: I think this is 1. just wrong and 2. not needed here? remove it?
	    // Replace ))Words((
	    $data = preg_replace("/\(\(([^\)]+)\)\)/", "$1", $data);

	    // Images
	    preg_match_all("/(\{img [^\}]+})/", $data, $pages);

	    foreach (array_unique($pages[1])as $page_parse) {
		$parts = $this->split_tag( $page_parse);

		$imgdata = array();      // pre-set preferences
		$imgdata["src"] = '';
		$imgdata["height"] = '';
		$imgdata["width"] = '';
		$imgdata["link"] = '';
		$imgdata["align"] = '';
		$imgdata["desc"] = '';
		$imgdata["imalign"] = '';
		$imgdata = $this->split_assoc_array( $parts, $imgdata);

		if ($tikidomain) {
		    $imgdata["src"] = preg_replace("~img/wiki_up/~","img/wiki_up/$tikidomain/",$imgdata["src"]);
		}
		$repl = '<img alt="' . tra('Image') . '" src="'.$imgdata["src"].'" border="0" ';

		if ($imgdata["width"])
		    $repl .= ' width="' . $imgdata["width"] . '"';

		if ($imgdata["height"])
		    $repl .= ' height="' . $imgdata["height"] . '"';

		if ($imgdata["imalign"]) {
		    $repl .= ' align="' . $imgdata["imalign"] . '"';
		}

		$repl .= ' />';

		if ($imgdata["link"]) {
		    $imgtarget= '';
		    if ($this->get_preference('popupLinks', 'n') == 'y')
		    {
			$imgtarget = ' target="_blank"';
		    }
		    $repl = '<a href="' . $imgdata["link"] .'"' . $imgtarget . '>' . $repl . '</a>';
		}

		if ($imgdata["desc"]) {
		    $repl = '<table cellpadding="0" cellspacing="0"><tr><td>' . $repl . '</td></tr><tr><td class="mini">' . $imgdata["desc"] . '</td></tr></table>';
		}

		if ($imgdata["align"]) {
		    $repl = '<div align="' . $imgdata["align"] . '">' . $repl . "</div>";
		}

		$data = str_replace($page_parse, $repl, $data);
	    }

	    $links = $this->get_links($data);

	    $notcachedlinks = $this->get_links_nocache($data);

	    $cachedlinks = array_diff($links, $notcachedlinks);

	    $this->cache_links($cachedlinks);

	    // Note that there're links that are replaced
	    foreach ($links as $link)
	    {
		$target = '';
		$class = 'class="wiki"';
		$ext_icon = '';

		if ($this->get_preference('popupLinks', 'n') == 'y')
		{
		    $target = 'target="_blank"';
		}

		if (isset($_SERVER['SERVER_NAME'])) {
		    $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
		}
		if (strstr($link, $_SERVER["SERVER_NAME"]))
		{
		    $target = '';
		} else {
		    global $feature_wiki_ext_icon;
		    if ($feature_wiki_ext_icon == 'y') {
			$class = 'class="wiki external"';
			$ext_icon = "<img border=\"0\" hspace=\"2\" src=\"img/icons/external_link.gif\" alt=\"external link\" />";
		    }
		}

	    if (!strstr($link, '//'))
	    {
		$target = '';
	    }
	
	    // The (?<!\[) stuff below is to give users an easy way to
	    // enter square brackets in their output; things like [[foo]
	    // get rendered as [foo]. -rlpowell

	if ($cachepages == 'y' && $this->is_cached($link))
		{
		    //use of urlencode for using cached versions of dynamic sites
		    $cosa = "<a class=\"wikicache\" target=\"_blank\" href=\"tiki-view_cache.php?url=".urlencode($link)."\">(cache)</a>";

		    //$link2 = str_replace("/","\/",$link);
		    //$link2 = str_replace("?","\?",$link2);
		    //$link2 = str_replace("&","\&",$link2);
		    $link2 = str_replace("/", "\/", preg_quote($link));
		    $pattern = "/(?<!\[)\[$link2\|([^\]\|]+)\|([^\]]+)\]/";
		    $data = preg_replace($pattern, "<a $class $target href='$link'>$1</a>$ext_icon", $data);
		    $pattern = "/(?<!\[)\[$link2\|([^\]\|]+)\]/";
		    $data = preg_replace($pattern, "<a $class $target href='$link'>$1</a>$ext_icon $cosa", $data);
		    $pattern = "/(?<!\[)\[$link2\]/";
		    $data = preg_replace($pattern, "<a $class $target href='$link'>$link</a>$ext_icon $cosa", $data);
		} else {
		    //$link2 = str_replace("/","\/",$link);
		    //$link2 = str_replace("?","\?",$link2);
		    //$link2 = str_replace("&","\&",$link2);
		    $link2 = str_replace("/", "\/", preg_quote($link));

		    $pattern = "/(?<!\[)\[$link2\|([^\]\|]+)([^\]])*\]/";
		    $data = preg_replace($pattern, "<a $class $target href='$link'>$1</a>$ext_icon", $data);
		    $pattern = "/(?<!\[)\[$link2\]/";
		    $data = preg_replace($pattern, "<a $class $target href='$link'>$link</a>$ext_icon", $data);
		}

	    }

	    // Handle double square brackets.  -rlpowell
	    $data = str_replace( "[[", "[", $data );

	    if ($feature_wiki_tables != 'new') {
	    // New syntax for tables
	    if (preg_match_all("/\|\|(.*)\|\|/", $data, $tables)) {
		$maxcols = 1;

		$cols = array();

		$temp_max = count($tables[0]);
		for ($i = 0; $i < $temp_max; $i++) {
		    $rows = explode('||', $tables[0][$i]);

	    // If the first character is space then
	    // change spaces for &nbsp;
	    $line = '<font face="courier">' . str_replace(' ', '&nbsp;', substr($line, 1)). '</font>';
	}

	// Replace Hotwords before begin
	$line = $this->replace_hotwords($line, $words);

	// Replace monospaced text
	$line = preg_replace("/-\+(.*?)\+-/", "<code>$1</code>", $line);
	// Replace bold text
	$line = preg_replace("/__(.*?)__/", "<b>$1</b>", $line);
	$line = preg_replace("/\'\'(.*?)\'\'/", "<i>$1</i>", $line);
	// Replace definition lists
	$line = preg_replace("/^;(.*):([^\/\/].*)/", "<dl><dt>$1</dt><dd>$2</dd></dl>", $line);

	if (0) {
	    $line = preg_replace("/\[([^\|]+)\|([^\]]+)\]/", "<a class='wiki' $target href='$1'>$2</a>", $line);

	    // Segundo intento reemplazar los [link] comunes
	    $line = preg_replace("/\[([^\]]+)\]/", "<a class='wiki' $target href='$1'>$1</a>", $line);
	    $line = preg_replace("/\-\=([^=]+)\=\-/", "<div class='wikihead'>$1</div>", $line);
	}

	// This line is parseable then we have to see what we have
	if (substr($line, 0, 3) == '---') {
	    // This is not list item -- must close lists currently opened
	    while (count($listbeg))
		$data .= array_shift($listbeg);

		    $temp_max2 = count($rows);
		    for ($j = 0; $j < $temp_max2; $j++) {
			$cols[$i][$j] = explode('|', $rows[$j]);

			if (count($cols[$i][$j]) > $maxcols)
			    $maxcols = count($cols[$i][$j]);
		    }
		}

		$temp_max3 = count($tables[0]);
		for ($i = 0; $i < $temp_max3; $i++) {
		    $repl = '<table class="wikitable">';

		    $temp_max4 = count($cols[$i]);
		    for ($j = 0; $j < $temp_max4; $j++) {
			$ncols = count($cols[$i][$j]);

			if ($ncols == 1 && !$cols[$i][$j][0])
			    continue;

			$repl .= '<tr>';

			for ($k = 0; $k < $ncols; $k++) {
			    $repl .= '<td class="wikicell" ';

			    if ($k == $ncols - 1 && $ncols < $maxcols)
				$repl .= ' colspan="' . ($maxcols - $k).'"';

			    $repl .= '>' . $cols[$i][$j][$k] . '</td>';
			}

			$repl .= '</tr>';
		    }

		    $repl .= '</table>';
		    $data = str_replace($tables[0][$i], $repl, $data);
		}
	    }
	    } else {
		// New syntax for tables
		// REWRITE THIS CODE
		if (preg_match_all("/\|\|(.*?)\|\|/s", $data, $tables)) {
		    $maxcols = 1;

		    $cols = array();

		    $temp_max5 = count($tables[0]);
		    for ($i = 0; $i < $temp_max5; $i++) {
			$rows = split("\n|\<br\/\>", $tables[0][$i]);

			$col[$i] = array();

			$temp_max6 = count($rows);
			for ($j = 0; $j < $temp_max6; $j++) {
			    $rows[$j] = str_replace('||', '', $rows[$j]);

			    $cols[$i][$j] = explode('|', $rows[$j]);

			    if (count($cols[$i][$j]) > $maxcols)
				$maxcols = count($cols[$i][$j]);
			}
		    }

		    $temp_max7 = count($tables[0]);
		    for ($i = 0; $i < $temp_max7; $i++) {
			$repl = '<table class="wikitable">';

			$temp_max8 = count($cols[$i]);
			for ($j = 0; $j < $temp_max8; $j++) {
			    $ncols = count($cols[$i][$j]);

			    if ($ncols == 1 && !$cols[$i][$j][0])
				continue;

			    $repl .= '<tr>';

			    for ($k = 0; $k < $ncols; $k++) {
				$repl .= '<td class="wikicell" ';

				if ($k == $ncols - 1 && $ncols < $maxcols)
				    $repl .= ' colspan="' . ($maxcols - $k).'"';

				$repl .= '>' . $cols[$i][$j][$k] . '</td>';
			    }

			    $repl .= '</tr>';
			}

			$repl .= '</table>';
			$data = str_replace($tables[0][$i], $repl, $data);
		    }
		}
	    }


	    // 26-Jun-2003, by zaufi
	    //
	    // {maketoc} --> create TOC from '!', '!!', '!!!' in current document
	    //
	    preg_match_all("/\{maketoc\}/", $data, $tocs);
	    $anch = array();

	    // 08-Jul-2003, by zaufi
	    // HotWords will be replace only in ordinal text
	    // It looks __realy__ goofy in Headers or Titles

	    // Get list of HotWords
	    $words = $this->get_hotwords();

	    // Now tokenize the expression and process the tokens
	    // Use tab and newline as tokenizing characters as well  ////
	    $lines = explode("\n", $data);
	    $data = '';
	    $listbeg = array();
	    $divdepth = array();
	    $inTable = 0;

	    // loop: process all lines
	    $in_paragraph = 0;
	    foreach ($lines as $line) {
		$line = rtrim($line); // Trim off trailing white space
		// Check for titlebars...
		// NOTE: that title bar should start at the beginning of the line and
		//	   be alone on that line to be autoaligned... otherwise, it is an old 
		//	   styled title bar...
		if (substr(ltrim($line), 0, 2) == '-=' && substr($line, -2, 2) == '=-') {
		    // Close open paragraph and lists, but not div's
		    $this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 1, 0);
		    //
		    $align_len = strlen($line) - strlen(ltrim($line));

		    // My textarea size is about 120 space chars.
		    //define('TEXTAREA_SZ', 120);

		    // NOTE: That strict math formula (split into 3 areas) gives
		    //	   bad visual effects...
		    // $align = ($align_len < (TEXTAREA_SZ / 3)) ? "left"
		    //		: (($align_len > (2 * TEXTAREA_SZ / 3)) ? "right" : "center");
		    //
		    // Going to introduce some heuristic here :)
		    // Visualy (remember that space char is thin) center starts at 25 pos
		    // and 'right' from 60 (HALF of full width!) -- thats all :)
		    //
		    // NOTE: Guess align only if more than 10 spaces before -=title=-
		    if ($align_len > 10) {
			$align = ($align_len < 25) ? "left" : (($align_len > 60) ? "right" : "center");

			$align = ' style="text-align: ' . $align . ';"';
		    } else
			$align = '';

		    //
		    $line = trim($line);
		    $line = '<div class="titlebar"' . $align . '>' . substr($line, 2, strlen($line) - 4). '</div>';
		    $data .= $line . "\n";
		    // TODO: Case is handled ...  no need to check other conditions
		    //	   (it is apriori known that they are all false, moreover sometimes
		    //	   check procedure need > O(0) of compexity)
		    //	   -- continue to next line...
		    //	   MUST replace all remaining parse blocks to the same logic...
		    continue;
		}

		// Replace old styled titlebars
		if (strlen($line) != strlen($line = preg_replace("/-=(.+?)=-/", "<div class='titlebar'>$1</div>", $line))) {
		    // Close open paragraph, but not lists (why not?) or div's
		    $this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 0, 0);
		    $data .= $line . "\n";

		    continue;
		}

		// check if we are inside a table, if so, ignore monospaced and do
		// not insert <br/>
		$inTable += substr_count(strtolower($line), "<table");
		$inTable -= substr_count(strtolower($line), "</table");

		// If the first character is ' ' and we are not in pre then we are in pre
		global $feature_wiki_monosp;

		if (substr($line, 0, 1) == ' ' && $feature_wiki_monosp == 'y' && $inTable == 0) {
		    // Close open paragraph and lists, but not div's
		    $this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 1, 0);

		    // If the first character is space then make font monospaced.
		    // For fixed formatting, use ~pp~...~/pp~
		    $line = '<tt>' . $line . '</tt>';
		}

		// Replace Hotwords before begin
		$line = $this->replace_hotwords($line, $words);

		// Make plain URLs clickable hyperlinks
		if ($feature_autolinks == 'y') {
		    $line = $this->autolinks($line);
		}

	    // Replace monospaced text
	    $line = preg_replace("/-\+(.*?)\+-/", "<code>$1</code>", $line);
	    // Replace bold text
	    $line = preg_replace("/__(.*?)__/", "<b>$1</b>", $line);
	    $line = preg_replace("/\'\'(.*?)\'\'/", "<i>$1</i>", $line);
	    // Replace definition lists
	    $line = preg_replace("/^;([^:]*):([^\/\/].*)/", "<dl><dt>$1</dt><dd>$2</dd></dl>", $line);

		/* this code following if (0) is never executed, right?
		   if (0) {
		   $line = preg_replace("/\[([^\|]+)\|([^\]]+)\]/", "<a class='wiki' $target href='$1'>$2</a>", $line);

		// Segundo intento reemplazar los [link] comunes
		$line = preg_replace("/\[([^\]]+)\]/", "<a class='wiki' $target href='$1'>$1</a>", $line);
		$line = preg_replace("/\-\=([^=]+)\=\-/", "<div class='wikihead'>$1</div>", $line);
		}
		 */

		// This line is parseable then we have to see what we have
		if (substr($line, 0, 3) == '---') {
		    // This is not a list item --- close open paragraph and lists, but not div's
		    $this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 1, 0);
		    $line = '<hr />';
		} else {
		    $litype = substr($line, 0, 1);
		    if ($litype == '*' || $litype == '#') {
			// Close open paragraph, but not lists or div's
			$this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 0, 0);
			$listlevel = $this->how_many_at_start($line, $litype);
			$liclose = '</li>';
			$addremove = 0;
			if ($listlevel < count($listbeg)) {
			    while ($listlevel != count($listbeg)) $data .= array_shift($listbeg);
			    if (substr(current($listbeg), 0, 5) != '</li>') $liclose = '';
			} elseif ($listlevel > count($listbeg)) {
			    $listyle = '';
			    while ($listlevel != count($listbeg)) {
				array_unshift($listbeg, ($litype == '*' ? '</ul>' : '</ol>'));
				if ($listlevel == count($listbeg)) {
				    $listate = substr($line, $listlevel, 1);
				    if (($listate == '+' || $listate == '-') && !($litype == '*' && !strstr(current($listbeg), '</ul>') || $litype == '#' && !strstr(current($listbeg), '</ol>'))) {
					$thisid = 'id' . microtime() * 1000000;
					$data .= '<br /><a id="flipper' . $thisid . '" class="link" href="javascript:flipWithSign(\'' . $thisid . '\')">[' . ($listate == '-' ? '+' : '-') . ']</a>';
					$listyle = ' id="' . $thisid . '" style="display:' . ($listate == '+' ? 'block' : 'none') . ';"';
					$addremove = 1;
				    }
				}
				$data.=($litype=='*'?"<ul$listyle>":"<ol$listyle>");
			    }
			    $liclose='';
			}
		    if ($litype == '*' && !strstr(current($listbeg), '</ul>') || $litype == '#' && !strstr(current($listbeg), '</ol>')) {
			$data .= array_shift($listbeg);
			$listyle = '';
			$listate = substr($line, $listlevel, 1);
			if (($listate == '+' || $listate == '-')) {
			    $thisid = 'id' . microtime() * 1000000;
			    $data .= '<br /><a id="flipper' . $thisid . '" class="link" href="javascript:flipWithSign(\'' . $thisid . '\')">[' . ($listate == '-' ? '+' : '-') . ']</a>';
			    $listyle = ' id="' . $thisid . '" style="display:' . ($listate == '+' ? 'block' : 'none') . ';"';
			    $addremove = 1;
			}
			$data .= ($litype == '*' ? "<ul$listyle>" : "<ol$listyle>");
			$liclose = '';
			array_unshift($listbeg, ($litype == '*' ? '</li></ul>' : '</li></ol>'));
		    }
		    $line = $liclose . '<li>' . substr($line, $listlevel + $addremove);
		    if (substr(current($listbeg), 0, 5) != '</li>') array_unshift($listbeg, '</li>' . array_shift($listbeg));
		} elseif ($litype == '+') {
		    // Close open paragraph, but not list or div's
		    $this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 0, 0);
		    $listlevel = $this->how_many_at_start($line, $litype);
		    // Close lists down to requested level
		    while ($listlevel < count($listbeg)) $data .= array_shift($listbeg);

		    // Must append paragraph for list item of given depth...
		    $listlevel = $this->how_many_at_start($line, $litype);
		    if (count($listbeg)) {
			if (substr(current($listbeg), 0, 5) != '</li>') {
			    array_unshift($listbeg, '</li>' . array_shift($listbeg));
			    $liclose = '<li>';
			} else $liclose = '<br />';
		    } else $liclose = '';
		    $line = $liclose . substr($line, count($listbeg));
		} else {
		    // This is not a list item - close open lists,
		    // but not paragraph or div's. If we are
		    // closing a list, there really shouldn't be a
		    // paragraph open anyway.
		    $this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 0, 1, 0);
		    // Get count of (possible) header signs at start
		    $hdrlevel = $this->how_many_at_start($line, '!');
		    // If 1st char on line is '!' and its count less than 6 (max in HTML)
		    if ($litype == '!' && $hdrlevel > 0 && $hdrlevel <= 6) {
			// Close open paragraph (lists already closed above)
			$this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 0, 0);
			// Close lower level divs if opened
			for (;current($divdepth) >= $hdrlevel; array_shift($divdepth)) $data .= '</div>';

			// Remove possible hotwords replaced :)
			//   Umm, *why*?  Taking this out lets page
			//   links in headers work, which can be nice.
			//   -rlpowell
			// $line = strip_tags($line);

			// OK. Parse headers here...
			$anchor = '';
			$aclose = '';
			$aclosediv = '';
			$addremove = 0;

			// May be special signs present after '!'s?
			$divstate = substr($line, $hdrlevel, 1);
			if ($divstate == '+' || $divstate == '-') {
			    // OK. Must insert flipper before HEADER, and then open new div after HEADER...
			    $thisid = 'id' . microtime() * 1000000;
			    $aclose = '<a id="flipper' . $thisid . '" class="link" style="text-decoration : none;" href="javascript:flipWithSign(\'' . $thisid . '\')">[' . ($divstate == '-' ? '+' : '-') . ']</a>';
			    $aclosediv = '<div id="' . $thisid . '" style="display:' . ($divstate == '+' ? 'block' : 'none') . ';">';
			    array_unshift($divdepth, $hdrlevel);
			    $addremove = 1;
			}
			// Is any {maketoc} present on page?
			if (count($tocs[0]) > 0) {
			    // OK. Must insert <a id=...> before HEADER and collect TOC entry
			    $thisid = 'id' . microtime() * 1000000;
			    array_push($anch, str_repeat("*", $hdrlevel). " <a href='#$thisid' class='link'>" . substr($line, $hdrlevel + $addremove). '</a>');
			    $anchor = "<a id='$thisid'>";
			    $aclose = '</a>' . $aclose;
			}
			$line = $anchor . "<h$hdrlevel>" . $aclose . " " . substr($line, $hdrlevel + $addremove). "</h$hdrlevel>" . $aclosediv;
		    } elseif (!strcmp($line, "...page...")) {
			// Close open paragraph, lists, and div's
			$this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 1, 1);
			// Leave line unchanged... tiki-index.php will split wiki here
			$line = "...page...";
		    } else {
			    /** Usual paragraph.  
			     *
			     * If the
			     * $feature_wiki_paragraph_formatting
			     * is on, then consecutive lines of
			     * text will be gathered into a block
			     * that is surrounded by HTML
			     * paragraph tags. One or more blank
			     * lines, or another special Wiki line
			     * (e.g., heading, titlebar, etc.)
			     * signifies the end of the
			     * paragraph. If the paragraph
			     * formatting feature is off, the
			     * original TikiWiki behavior is used,
			     * in which each line in the source is
			     * terminated by an explicit line
			     * break (br tag).
			     *
			     * @since Version 1.9
			     */
			    if ($inTable == 0) {
				if ($feature_wiki_paragraph_formatting == 'y') {
				    if ($in_paragraph && (0 == strcmp("", trim($line)))) {
					// Blank line; end the paragraph
					$this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 0, 0);
				    } elseif (!$in_paragraph && (0 != strcmp("", trim($line)))) {
					// First non-blank line; start a paragraph
					$data .= "<p>";
					$in_paragraph = 1;
				    } else {
					// A normal in-paragraph line or a consecutive blank line.
					// Leave it as is.
				    }
				} else {
				    $line .= '<br />';
				}
			    }
			}
		    }
		}
		$data .= $line . "\n";
	    }

	    // Close open paragraph, lists, and div's
	    $this->close_blocks($data, $in_paragraph, $listbeg, $divdepth, 1, 1, 1);

	    // 26-Jun-2003, by zaufi
	    // Replace {maketoc} from collected list of headers
	    $html = '';

	    foreach ($anch as $tocentry) {
		$html .= $tocentry . "\n";
	    }

	    if (count($anch))
		$html = $this->parse_data($html);

	$data = str_replace("{maketoc}", $html, $data);

	// Replace rss modules
	if (preg_match_all("/\{rss +id=([0-9]+) *(max=([0-9]+))? *\}/", $data, $rsss)) {
	    if (!isset($rsslib)) {
		include ('lib/rss/rsslib.php');
	    }

	    $temp_max = count($rsss[0]);
	    for ($i = 0; $i < $temp_max; $i++) {
		$id = $rsss[1][$i];

		$max = $rsss[3][$i];

		if (empty($max))
		    $max = 99;

		$rssdata = $rsslib->get_rss_module_content($id);
		$items = $rsslib->parse_rss_data($rssdata, $id);

		$repl="";		
		if ($items[0]["isTitle"]=="y") {
			$repl .= '<div class="wiki"><a target="_blank" href="'.$items[0]["link"].'">'.$items[0]["title"].'</a></div>'; 
			$items = array_slice ($items, 1);
		}

		$repl .= '<ul class="rsslist">';
		$temp_max2 = count($items);
		for ($j = 0; $j < $temp_max2 && $j < $max; $j++) {
		    $repl .= '<li class="rssitem"><a target="_blank" href="' . $items[$j]["link"] . '" class="rsslink">' . $items[$j]["title"] . '</a>';
		    if (isset($items[$j]["pubDate"]) && $items[$j]["pubDate"] <> '') { $repl .= ' <span class="rssdate">('.$items[$j]["pubDate"].')</span>'; }
		    $repl .= '</li>';
		}

		$repl .= '</ul>';
		$data = str_replace($rsss[0][$i], $repl, $data);
	    }
	}

	// linebreaks using %%%
	$data = str_replace("%%%", "<br />", $data);

	// Close BiDi DIVs if any
	for ($i = 0; $i < $bidiCount; $i++) {
	    $data .= "</div>";
	}

	// Put removed strings back.
	$this->replace_preparse($data, $preparsed, $noparsed);

	// Process pos_handlers here
	foreach ($this->pos_handlers as $handler) {
	    $data = $handler($data);
	}

	return $data;
    }

	function parse_smileys($data) {
	    global $feature_smileys;

	    if ($feature_smileys == 'y') {
		$data = preg_replace("/\(:([^:]+):\)/", "<img alt=\"$1\" src=\"img/smiles/icon_$1.gif\" />", $data);
	    }

	    return $data;
	}

	function parse_comment_data($data) {
	    $data = preg_replace("/\[([^\|\]]+)\|([^\]]+)\]/", "<a class=\"commentslink\" href=\"$1\">$2</a>", $data);

	    // Segundo intento reemplazar los [link] comunes
	    $data = preg_replace("/\[([^\]\|]+)\]/", "<a class=\"commentslink\" href=\"$1\">$1</a>", $data);
	    // Llamar aqui a parse smileys
	    $data = $this->parse_smileys($data);
	    $data = preg_replace("/---/", "<hr />", $data);
	    // Reemplazar --- por <hr />
	    return $data;
	}

	function get_pages($data) {
	    global $page_regex;

	    global $feature_wikiwords;

	    if ($feature_wikiwords == 'y') {
		preg_match_all("/\(\(($page_regex)\)\)/", $data, $pages2);
		preg_match_all("/\(\(($page_regex)\|(.+?)\)\)/", $data, $pages3);

		preg_match_all("/([ \n\t\r\,\;]|^)?([A-Z][a-z0-9_\-]+[A-Z][a-z0-9_\-]+[A-Za-z0-9\-_]*)($|[ \n\t\r\,\;\.])/", $data, $pages);
		$pages = array_unique(array_merge($pages[2], $pages2[1], $pages3[1]));
	    } else {
		preg_match_all("/\(\(($page_regex)\)\)/", $data, $pages);

		preg_match_all("/\(\(($page_regex)\|(.+?)\)\)/", $data, $pages2);
		$pages = array_unique(array_merge($pages[1], $pages2[1]));
	    }

	    return $pages;
	}

	function clear_links($page) {
	    $query = "delete from `tiki_links` where `fromPage`=?";
	    $result = $this->query($query, array($page));
	}

	function replace_link($pageFrom, $pageTo) {
	    $query = "delete from `tiki_links` where `fromPage`=? and `toPage`=?";
	    $result = $this->query($query, array($pageFrom,$pageTo));
	    $query = "insert into `tiki_links`(`fromPage`,`toPage`) values(?, ?)";
	    $result = $this->query($query, array($pageFrom,$pageTo));
	}

	function invalidate_cache($page) {
	    $query = "update `tiki_pages` set `cache_timestamp`=? where `pageName`=?";
	    $this->query($query, array(0,$page) );
	}

	function update_page($pageName, $edit_data, $edit_comment, $edit_user, $edit_ip, $edit_description = '', $minor = false, $lang='') {
	    $this->invalidate_cache($pageName);
	    // Collect pages before modifying edit_data (see update of links below)
	    $pages = $this->get_pages($edit_data);

	    if (!$this->page_exists($pageName))
		return false;

	    $t = date("U");
	    
	    // Use largest version +1 in history table rather than tiki_page because versions used to be bugged
	    //    $old_version = $info["version"];
	    include_once ("lib/wiki/histlib.php");
	    $old_version = $histlib->get_page_latest_version($pageName);
	   
	    if (!$minor && $pageName != 'SandBox') {
		// Archive current version
		$info = $this->get_page_info($pageName);
		$lastModif = $info["lastModif"];
		$user = $info["user"];
		$ip = $info["ip"];
		$comment = $info["comment"];
		$data = $info["data"];
		$description = $info["description"];
		$query = "insert into `tiki_history`(`pageName`, `version`, `lastModif`, `user`, `ip`, `comment`, `data`, `description`)
		values(?,?,?,?,?,?,?,?)";
		$result = $this->query($query,array($pageName,(int) $old_version,(int) $lastModif,$user,$ip,$comment,$data,$description));
	    }
	    // WARNING: POTENTIAL BUG
	    // The line below is not consistent with the rest of Tiki
	    // (I commented it out so it can be further examined by CVS change control)
	    //$pageName=addslashes($pageName);
	    // But this should work (comment added by redflo):
	    $version = $old_version + 1;

	    if ($lang) {// not sure it is necessary
		$query = "update `tiki_pages` set `description`=?, `data`=?, `comment`=?, `lastModif`=?, `version`=?, `user`=?, `ip`=?, `page_size`=?, `lang`=? 
		where `pageName`=?";
		$result = $this->query($query,array($edit_description,$edit_data,$edit_comment,(int) $t,$version,$edit_user,$edit_ip,(int)strlen($edit_data),$lang,$pageName));
	    } else {
		$query = "update `tiki_pages` set `description`=?, `data`=?, `comment`=?, `lastModif`=?, `version`=?, `user`=?, `ip`=?, `page_size`=? 
		where `pageName`=?";
		$result = $this->query($query,array($edit_description,$edit_data,$edit_comment,(int) $t,$version,$edit_user,$edit_ip,(int)strlen($edit_data),$pageName));
	    }
	    // Parse edit_data updating the list of links from this page
	    $this->clear_links($pageName);
	    // Pages collected above
	    foreach ($pages as $page) {
		$this->replace_link($pageName, $page);
	    }
	    
	    if (!$minor) {
		if ($pageName != 'SandBox') {
			// Update the log
			$action = "Updated"; //get_strings tra("Updated")

			$query = "insert into `tiki_actionlog`(`action`,`pageName`,`lastModif`,`user`,`ip`,`comment`) values(?,?,?,?,?,?)";
			$result = $this->query($query,array($action,$pageName,(int) $t,$edit_user,$edit_ip,$edit_comment));

			$maxversions = $this->get_preference("maxVersions", 0);
			if ($maxversions) {
				// Delete outdated versions from history
				$keep = $this->get_preference('keep_versions', 0);
				$oktodel = $t - ($keep * 24 * 3600);
				$query = "select `version` from `tiki_history` where `pageName`=? and `lastModif`<=? order by `lastModif` desc";
				$result = $this->query($query,array($pageName,$oktodel),-1,$maxversions);
				$query = "delete from `tiki_history` where `pageName`=? and `version`=?";
				while ($res = $result->fetchRow()) {
					$this->query($query,array($pageName,$res["version"]));
				}
			}
		}
		global $feature_user_watches;
		if ($feature_user_watches == 'y') {
			//  Deal with mail notifications.
			include_once('lib/notifications/notificationemaillib.php');
			$foo = parse_url($_SERVER["REQUEST_URI"]);
			$machine = httpPrefix(). dirname( $foo["path"] );
			sendWikiEmailNotification('wiki_page_changed', $pageName, $edit_user, $edit_comment, $version, $edit_data, $machine);
		}

		global $feature_score;
		if ($feature_score == 'y') {
			$this->score_event($user, 'wiki_edit');
	        }
	    }
	}

# TODO move all of these date/time functions to a static class: TikiDate
	function get_timezone_list($use_default = false) {
	    static $timezone_options;

	    if (!$timezone_options) {
		$timezone_options = array();

		if ($use_default)
		    $timezone_options['default'] = '-- Use Default Time Zone --';

		foreach ($GLOBALS['_DATE_TIMEZONE_DATA'] as $tz_key => $tz) {
		    $offset = $tz['offset'];

		    $absoffset = abs($offset /= 60000);
		    $plusminus = $offset < 0 ? '-' : '+';
		    $gmtoff = sprintf("GMT%1s%02d:%02d", $plusminus, $absoffset / 60, $absoffset - (intval($absoffset / 60) * 60));
		    $tzlongshort = $tz['longname'] . ' (' . $tz['shortname'] . ')';
			    $timezone_options[$tz_key] = sprintf('%-28.28s: %-36.36s %s', $tz_key, $tzlongshort, $gmtoff);
			    }
			    }

			    return $timezone_options;
			    }

			    function get_server_timezone() {
			    static $server_timezone;

			    if (!$server_timezone) {
			    $server_time = new Date();

			    $server_timezone = $server_time->tz->getID();
			    }

			    return $server_timezone;
			    }

# TODO rename get_site_timezone()
			    function get_display_timezone($user = false) {
				static $display_timezone = false;

				if (!$display_timezone) {
				    $server_time = $this->get_server_timezone();

				    if ($user) {
					$display_timezone = $this->get_user_preference($user, 'display_timezone');

					if (!$display_timezone || $display_timezone == 'default') {
					    $display_timezone = $this->get_preference('display_timezone', $server_time);
					}
				    } else {
					$display_timezone = $this->get_preference('display_timezone', $server_time);
				    }
				}

				return $display_timezone;
			    }

			    /**
			     * Retrieves the user's preferred offset for displaying dates.
			     *
			     * $user: the logged-in user.
			     * returns: the preferred offset to UTC.
			     */
			    function get_display_offset($_user = false) {

				// Cache preference from DB
				$display_tz = "UTC";

				// Default to UTCget_display_offset
				$display_offset = 0;

				// Load pref from DB is cache is empty
				if ($_user)
				    $display_tz = $this->get_display_timezone($_user);

				// Recompute offset each request in case DST kicked in
				if ($display_tz != "UTC" && isset($_COOKIE["tz_offset"]))
				    $display_offset = intval($_COOKIE["tz_offset"]);

				return $display_offset;
			    }

			    /**
			     * Retrieves a TikiDate object for converting to/from display/UTC timezones
			     *
			     * $user: the logged-in user
			     * returns: reference to a TikiDate instance with the appropriate offsets
			     */
			    function &get_date_converter($_user = false) {
				static $date_converter;

				if (!$date_converter) {
				    $display_offset = $this->get_display_offset($_user);

				    $date_converter = &new TikiDate($display_offset);
				}

				return $date_converter;
			    }

			    function get_long_date_format() {
				static $long_date_format = false;

				if (!$long_date_format)
				    $long_date_format = $this->get_preference('long_date_format', '%A %d of %B, %Y');

				return $long_date_format;
			    }

			    function get_short_date_format() {
				static $short_date_format = false;

				if (!$short_date_format)
				    $short_date_format = $this->get_preference('short_date_format', '%a %d of %b, %Y');

				return $short_date_format;
			    }

			    function get_long_time_format() {
				static $long_time_format = false;

				if (!$long_time_format)
				    $long_time_format = $this->get_preference('long_time_format', '%H:%M:%S %Z');

				return $long_time_format;
			    }

			    function get_short_time_format() {
				static $short_time_format = false;

				if (!$short_time_format)
				    $short_time_format = $this->get_preference('short_time_format', '%H:%M %Z');

				return $short_time_format;
			    }

			    function get_long_datetime_format() {
				static $long_datetime_format = false;

				if (!$long_datetime_format)
				    $long_datetime_format = $this->get_long_date_format(). ' [' . $this->get_long_time_format(). ']';

				return $long_datetime_format;
			    }

			    function get_short_datetime_format() {
				static $short_datetime_format = false;

				if (!$short_datetime_format)
				    $short_datetime_format = $this->get_short_date_format(). ' [' . $this->get_short_time_format(). ']';

				return $short_datetime_format;
			    }

			    function server_time_to_site_time($timestamp, $user = false) {
				$date = new Date($timestamp);

				$date->setTZbyID($this->get_server_timezone());
				$date->convertTZbyID($this->get_display_timezone($user));
				return $date->getTime();
			    }

			    /**

			     */
			    function get_site_date($timestamp, $user = false) {
				static $localed = false;

				if (!$localed) {
				    $this->set_locale($user);

				    $localed = true;
				}

				$original_tz = date('T', $timestamp);

				$format = '%b %e, %Y';
				$rv = strftime($format, $timestamp);
				$rv .= " =timestamp\n";
				$rv .= strftime('%Z', $timestamp);
				$rv .= " =strftime('%Z')\n";
				$rv .= date('T', $timestamp);
				$rv .= " =date('T')\n";

				$date = &new Date($timestamp);

# Calling new Date() changes the timezone of the $timestamp var!
# so we only change the timezone to UTC if the original TZ wasn't UTC
# to begin with.
# This seems really buggy, but I don't have time to delve into right now.
				$rv .= date('T', $timestamp);
				$rv .= " =date('T')\n";

				$rv .= $date->format($format);
				$rv .= " =new Date()\n";

				$rv .= date('T', $timestamp);
				$rv .= " =date('T')\n";

				if ($original_tz == 'UTC') {
				    $date->setTZbyID('UTC');

				    $rv .= $date->format($format);
				    $rv .= " =setTZbyID('UTC')\n";
				}

				$tz_id = $this->get_display_timezone($user);

				if ($date->tz->getID() != $tz_id) {
# let's convert to the displayed timezone
				    $date->convertTZbyID($tz_id);

				    $rv .= $date->format($format);
				    $rv .= " =convertTZbyID($tz_id)\n";
				}

#return $rv;

# if ($format == "%b %e, %Y")
#   $format = $tikilib->get_short_date_format();
				return $date;
			    }

# TODO rename to server_time_to_site_time()
			    function get_site_time($timestamp, $user = false) {
#print "<pre>get_site_time()</pre>";
				$date = $this->get_site_date($timestamp, $user);

				return $date->getTime();
			    }

			    function date_format($format, $timestamp, $user = false) {
				//$date = $this->get_site_date($timestamp, $user);
				// JJ - ignore conversion - we have no idea what TZ they're using

				// strftime doesn't do translations correctly
				// return strftime($format,$timestamp);
				$date = new Date($timestamp);

				return $date->format($format);
			    }

			    function get_long_date($timestamp, $user = false) {
				return $this->date_format($this->get_long_date_format(), $timestamp, $user);
			    }

			    function get_short_date($timestamp, $user = false) {
				return $this->date_format($this->get_short_date_format(), $timestamp, $user);
			    }

			    function get_long_time($timestamp, $user = false) {
				return $this->date_format($this->get_long_time_format(), $timestamp, $user);
			    }

			    function get_short_time($timestamp, $user = false) {
				return $this->date_format($this->get_short_time_format(), $timestamp, $user);
			    }

			    function get_long_datetime($timestamp, $user = false) {
				return $this->date_format($this->get_long_datetime_format(), $timestamp, $user);
			    }

			    function get_short_datetime($timestamp, $user = false) {
				return $this->date_format($this->get_short_datetime_format(), $timestamp, $user);
			    }

			    function get_site_timezone_shortname($user = false) {
				// UTC, or blank for local
				$dc = &$this->get_date_converter($user);

				return $dc->getTzName();
			    }

			    function get_server_timezone_shortname($user = false) {
				// Site time is always UTC, from the user's perspective.
				return "UTC";
			    }

			    /**
			      get_site_time_difference - Return the number of seconds needed to add to a
			      'system' time to return a 'site' time.
			     */
			    function get_site_time_difference($user = false) {
				$dc = &$this->get_date_converter($user);

				$display_offset = $dc->display_offset;
				$server_offset = $dc->server_offset;
				return $display_offset - $server_offset;
			    }

			    /**
			      Timezone saavy replacement for mktime()
			     */
			    function make_time($hour, $minute, $second, $month, $day, $year, $timezone_id = false) {
				global $user; # ugh!

				    if ($year <= 69)
					$year += 2000;

				if ($year <= 99)
				    $year += 1900;

				$date = new Date();
				$date->setHour($hour);
				$date->setMinute($minute);
				$date->setSecond($second);
				$date->setMonth($month);
				$date->setDay($day);
				$date->setYear($year);

#$rv = sprintf("make_time(): $date->format(%D %T %Z)=%s<br />\n", $date->format('%D %T %Z'));
#print "<pre> make_time() start";
#print_r($date);
				if ($timezone_id)
				    $date->setTZbyID($timezone_id);

#print_r($date);
#$rv .= sprintf("make_time(): $date->format(%D %T %Z)=%s<br />\n", $date->format('%D %T %Z'));
#print $rv;
				return $date->getTime();
			    }

			    /**
			      Timezone saavy replacement for mktime()
			     */
			    function make_server_time($hour, $minute, $second, $month, $day, $year, $timezone_id = false) {
				global $user; # ugh!

				    if ($year <= 69)
					$year += 2000;

				if ($year <= 99)
				    $year += 1900;

				$date = new Date();
				$date->setHour($hour);
				$date->setMinute($minute);
				$date->setSecond($second);
				$date->setMonth($month);
				$date->setDay($day);
				$date->setYear($year);

#print "<pre> make_server_time() start\n";
#print_r($date);
				if ($timezone_id)
				    $date->setTZbyID($timezone_id);

#print_r($date);
				$date->convertTZbyID($this->get_server_timezone());
#print_r($date);
#print "make_server_time() end\n</pre>";
				return $date->getTime();
			    }

			    /**
			      Per http://www.w3.org/TR/NOTE-datetime
			     */
			    function get_iso8601_datetime($timestamp, $user = false) {
				return $this->date_format('%Y-%m-%dT%H:%M:%S%O', $timestamp, $user);
			    }

			    function get_rfc2822_datetime($timestamp = false, $user = false) {
				if (!$timestamp)
				    $timestamp = time();

# rfc2822 requires dates to be en formatted
				$saved_locale = @setlocale(0);
				@setlocale ('en_US');
#was return date('D, j M Y H:i:s ', $time) . $this->timezone_offset($time, 'no colon');
				$rv = $this->date_format('%a, %e %b %Y %H:%M:%S', $timestamp, $user). $this->get_rfc2822_timezone_offset($timestamp, $user);

# switch back to the 'saved' locale
				if ($saved_locale)
				    @setlocale ($saved_locale);

				return $rv;
			    }

			    function get_rfc2822_timezone_offset($time = false, $no_colon = false, $user = false) {
				if ($time === false)
				    $time = time();

				$secs = $this->date_format('%Z', $time, $user);

				if ($secs < 0) {
				    $sign = '-';

				    $secs = -$secs;
				} else {
				    $sign = '+';
				}

				$colon = $no_colon ? '' : ':';
				$mins = intval(($secs + 30) / 60);

				return sprintf("%s%02d%s%02d", $sign, $mins / 60, $colon, $mins % 60);
			    }

			    function list_languages($path = false, $short=null) {
				$languages = array();

				if (!$path)
				    $path = "lang";

				if (!is_dir($path))
				    return array();

				$h = opendir($path);

				while ($file = readdir($h)) {
				    if ($file != '.' && $file != '..' && $file != 'CVS' && $file != 'index.php' && is_dir("$path/$file") ) {
					$languages[] = $file;
				    }
				}

				closedir ($h);

				// Format and return the list
				return $this->format_language_list($languages, $short);
			    }

			    function list_styles() {
				global $tikidomain;

			    $sty = array();
			    $h = opendir("styles/");
			    while ($file = readdir($h)) {
				if (ereg("\.css$", $file)) {
				    $sty[$file] = 1;
				}
			    }
				closedir($h);

				/* What is this $tikidomain section?
				 * Some files that call this method used to list styles without considering
				 * $tikidomain, now they do. They're listed below:
				 *  
				 *  tiki-theme_control.php
				 *  tiki-theme_control_objects.php
				 *  tiki-theme_control_sections.php
				 *  tiki-my_tiki.php
				 *  modules/mod-switch_theme.php
				 *
				 *  lfagundes
				 */ 

				if ($tikidomain) {
				    $h = opendir("styles/$tikidomain");
				    while ($file = readdir($h)) {
					if (strstr($file, ".css") and substr($file,0,1) != '.') {
					    $sty["$file"] = 1;
					} 
				    } 
				    closedir($h);				
				}


				$styles = array_keys($sty);
				sort($styles);
				return $styles;
			    }

			    // Comparison function used to sort languages by their name in the
			    // current locale.
			    function formatted_language_compare($a, $b) {
				return strcmp($a['name'], $b['name']);
			    }
			    // Returns a list of languages formatted as a twodimensionel array
			    // with 'value' being the language code and 'name' being the name of
			    // the language.
			    // if $short is 'y' returns only the localized language names array
			    function format_language_list($languages, $short=null) {
				// The list of available languages so far with both English and
				// translated names.
				global $langmapping;
				include_once("lang/langmapping.php");
				$formatted = array();

				// run through all the language codes:
				if (isset($short) && $short == "y") {
				    foreach ($languages as $lc) {
					if (isset($langmapping[$lc]))
					    $formatted[] = array('value' => $lc, 'name' => $langmapping[$lc][0]);
					else
					    $formatted[] = array('value' => $lc, 'name' => $lc);
				    }
				    return $formatted;
				}
				foreach ($languages as $lc) {
				    if (isset($langmapping[$lc])) {
					// known language
					if ($langmapping[$lc][0] == $langmapping[$lc][1]) {
					    // Skip repeated text, 'English (English, en)' looks silly.
					    $formatted[] = array(
						    'value' => $lc,
						    'name' => $langmapping[$lc][0] . " ($lc)"
						    );
					} else {
					    $formatted[] = array(
						    'value' => $lc,
						    'name' => $langmapping[$lc][1] . ' (' . $langmapping[$lc][0] . ', ' . $lc . ')'
							);
						    }
						    } else {
						    // unknown language
						    $formatted[] = array(
							'value' => $lc,
							'name' => tra("Unknown language"). " ($lc)"
							);
						    }
						    }

						    // Sort the languages by their name in the current locale
						    usort($formatted, array('TikiLib', 'formatted_language_compare'));
						    return $formatted;
						    }

						    function get_language($user = false) {
						    static $language = false;

						    if (!$language) {
							if ($user) {
							    $language = $this->get_user_preference($user, 'language', 'en');

							    if (!$language || $language == 'default')
								$language = $this->get_preference('language', 'en');
							} else
							    $language = $this->get_preference('language', 'en');
						    }
						    return $language;
						    }

						    function get_locale($user = false) {
# TODO move to admin preferences screen
							static $locales = array(
								'cs' => 'cs_CZ',
								'de' => 'de_DE',
								'dk' => 'da_DK',
								'en' => 'en_US',
								'fr' => 'fr_FR',
								'he' => 'he_IL', # hebrew
								'it' => 'it_IT', # italian
								'pl' => 'pl_PL', # polish
								'po' => 'po',
								'ru' => 'ru_RU',
								'es' => 'es_ES',
								'sw' => 'sw_SW', # swahili
								'tw' => 'tw_TW',
								);

							if (!isset($locale) or !$locale) {
							    $locale = '';
							    if (isset($locales[$this->get_language($user)]))
								$locale = $locales[$this->get_language($user)];
#print "<pre>get_locale(): locale=$locale\n</pre>";
							}

							return $locale;
						    }

						    function set_locale($user = false) {
							static $locale = false;

							if (!$locale) {
# breaks the RFC 2822 code
							    $locale = @setlocale(LC_TIME, $this->get_locale($user));
#print "<pre>set_locale(): locale=$locale\n</pre>";
							}

							return $locale;
						    }

						    function read_raw($text) {
							$file = split("\n",$text);
							foreach ($file as $line) {
							    $r = $s = '';
							    if (substr($line,0,1) != "#") {
								if (ereg("^\[([A-Z]+)\]",$line,$r)) {
								    $var = strtolower($r[1]);
								}
								if ($var and (ereg("^([-_/ a-zA-Z0-9]+)[ \t]+[:=][ \t]+(.*)",$line,$s))) {
								    $back[$var][trim($s[1])] = trim($s[2]);
								}
							    }
							}
							return $back;
						    }

    } 

    // end of class ------------------------------------------------------

    function compare_links($ar1, $ar2) {
	return $ar1["links"] - $ar2["links"];
    }

    function compare_backlinks($ar1, $ar2) {
	return $ar1["backlinks"] - $ar2["backlinks"];
    }

    function r_compare_links($ar1, $ar2) {
	return $ar2["links"] - $ar1["links"];
    }

    function r_compare_backlinks($ar1, $ar2) {
	return $ar2["backlinks"] - $ar1["backlinks"];
    }

    function compare_images($ar1, $ar2) {
	return $ar1["images"] - $ar2["images"];
    }

    function r_compare_images($ar1, $ar2) {
	return $ar2["images"] - $ar1["images"];
    }

    function compare_files($ar1, $ar2) {
	return $ar1["files"] - $ar2["files"];
    }

    function r_compare_files($ar1, $ar2) {
	return $ar2["files"] - $ar1["files"];
    }

    function compare_versions($ar1, $ar2) {
	return $ar1["versions"] - $ar2["versions"];
    }

    function r_compare_versions($ar1, $ar2) {
	return $ar2["versions"] - $ar1["versions"];
    }

    function compare_changed($ar1, $ar2) {
	return $ar1["lastChanged"] - $ar2["lastChanged"];
    }

    function r_compare_changed($ar1, $ar2) {
	return $ar2["lastChanged"] - $ar1["lastChanged"];
    }

    function chkgd2() {
	if (!isset($_SESSION['havegd2'])) {
#   TODO test this logic in PHP 4.3
#   if (version_compare(phpversion(), "4.3.0") >= 0) {
#  $_SESSION['havegd2'] = true;
#   } else {
    ob_start();

    phpinfo (INFO_MODULES);
    $_SESSION['havegd2'] = preg_match('/GD Version.*2.0/', ob_get_contents());
    ob_end_clean();
# }
	}

	return $_SESSION['havegd2'];
    }

    function httpScheme() {
	return 'http' . ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) ? 's' : '');
    }

    function httpPrefix() {
	/*
	   if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) {
	   $rv = 'https://' . $_SERVER['HTTP_HOST'];

	   if ($_SERVER['SERVER_PORT'] != 443)
	   $rv .= ':' . $_SERVER['SERVER_PORT'];
	   } else {
	   $rv = 'http://' . $_SERVER['HTTP_HOST'];

	   if ($_SERVER['SERVER_PORT'] != 80)
	   $rv .= ':' . $_SERVER['SERVER_PORT'];
	   }

	   return $rv;
	 */
	/* Warning by zaufi: as far as I saw in my apache 1.3.27
	 * there is no need to add port if it is non default --
	 * $_SERVER['HTTP_HOST'] already contain it ...
	 */
	return 'http'.((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) ? 's' : '').'://'.$_SERVER['HTTP_HOST'];
    }

    function detect_browser_language() {

	// Get supported languages
	$supported = preg_split('/\s*,\s*/', preg_replace('/;q=[0-9.]+/','',$_SERVER['HTTP_ACCEPT_LANGUAGE']));

	// Get available languages
	$available = array();
	$available_aprox = array();

	$dh = opendir("lang");
	while ($lang = readdir($dh)) {
	    if (file_exists("lang/$lang/language.php")) {
		$available[] = $lang;
		$available_aprox[substr($lang, 0, 2)] = $lang;
	    }
	}

	// Check better language
	// First try an exact match, then an aproximate
	$aproximate_lang = '';
	foreach ($supported as $supported_lang) {
	    $lang = strtolower($supported_lang);
	    if (in_array($lang, $available)) {
		return $lang;
	    } else {
		$lang = substr($lang, 0, 2);
		if (in_array($lang, array_keys($available_aprox))) {
		    $aproximate_lang = $available_aprox[$lang];
		}
	    }
	}

	return $aproximate_lang;
    }


    if (!function_exists('file_get_contents')) {
	function file_get_contents($f) {
	    ob_start();

	    $retval = @readfile($f);

	    if (false !== $retval) { // no readfile error
		$retval = ob_get_contents();
	    }

	    ob_end_clean();
	    return $retval;
	}

    }

    ?>
