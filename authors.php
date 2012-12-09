<?php
/*
Plugin Name: Authors
Description: A virtual authoring system that can base articles off of author profiles instead of user profiles
Version: 0.1
Author: JzL
License: GPLv3
*/
?>
<?php

defined("ABSPATH") || exit;

define('ATH_OPTKEY', 'ath_list');
define('ATH_MAXIDKEY', 'ath_maxid');
define('ATH_POSTMETAKEY', 'post_ath');
define('ATH_PREF', 'ath-');
define('ATH_SETTINGSKEY', 'ath_profedit');
define('ATH_HTMLKEY', 'ath_html');
define('ATH_CSSKEY', 'ath_css');
//define('ATH_TAXNAME', 'author');
define('ATH_TAXNAME', 'category');
define('ATH_TAXSTRFMT', 'author_id_%1$s');

define('ATH_LIST_PER_PAGE', 10);

/* Default display */
define('ATH_DEFAULTHTML',
<<<HTML
<div class="name"><a href="\${postsby_link}" target="_blank">\${fname} \${lname}</a><a href="\${fb}" target="_blank"><img src="http://i.imgur.com/yXDVF.png" class="social fb-link"></a><a href="\${twitter}" target="_blank"><img src="http://i.imgur.com/9vmic.png" class="social twitter-link"></a><div style="clear:both"></div>
</div>
<div class="bio">
<img class="pic" src="\${pic}"/>\${bio}<div style="clear:both"></div>
</div>
HTML
);

define('ATH_DEFAULTCSS',
<<<CSS
.ath-desc {
	margin: 20px;
	padding: 10px;
	border-radius: 13px;
	background: #BABABA;
}
.ath-desc .name {
	background: #D9D9D9;
	padding: 5px;
}
.ath-desc .bio {
	background: #E8E8E8;
	padding: 5px;
}
.ath-desc .pic {
	width: 45px;
	height: 45px;
	float: left;
}
.ath-desc .social {
	display: block;
	width:20px;
	height:20px;
	float:right;
	margin: 0px;
	padding: 0px;
}
CSS
);

/* Author fields */
$ATH_FIELDS = array(
		'fname'=>array('text','First Name'),
		'lname'=>array('text','Last Name'),
		'bio'=>array('textarea','Bio'),
		'pic'=>array('text','Picture'),
		'twitter'=>array('text','Twitter Link'),
		'fb'=>array('text','Facebook Link'),
		'linkedin'=>array('text','LinkedIn')
);

/* Author management backend */

function ath_genid() {
	$id = get_option(ATH_MAXIDKEY,0);
	update_option(ATH_MAXIDKEY,$id+1);
	return $id;
}

function ath_update($id, $info) {
	if((!is_array($info) && $info!=null) || !is_numeric($id)) return false;
	$auths = get_option(ATH_OPTKEY, array());
	if($info===null)
		unset($auths[$id]);
	else
		$auths[$id] = $info;
	update_option(ATH_OPTKEY, $auths);
	return true;
}

function ath_create($info) {
	$id = ath_genid();
	$name = sprintf("%s %s", htmlentities($info['fname']), htmlentities($info['lname']));
	$tid = wp_insert_term($name, ATH_TAXNAME,
		array(
			'description'=>"Posts by $name",
			'slug'=>sprintf(ATH_TAXSTRFMT,$id)
		)
	);
	if(!is_wp_error($tid)) {
		$info['termid'] = $tid['term_id'];
		ath_update($id, $info);
		return $id;
	}else {
		return false;
	}
}

function ath_check_info(&$info) {
	if(strlen(trim($info['fname']))=="" && strlen(trim($info['lname']))==0)
		return false;
	if(array_key_exists('edit-ids',$info))
		unset($info['edit-ids']);
	return true;
}

function ath_search($qInfo) {
	$auths = get_option(ATH_OPTKEY, array());
	$r = array();
	foreach($auths as $id=>$info) {
		foreach($qInfo as $qKey=>$qVal) {
			if($qVal == $info[$qKey]) {
				if(!array_key_exists($id,$r))
					$r[$id] = $info;
				break;
			}
		}
	}
	return $r;
}

function ath_get($id) {
	$auths = get_option(ATH_OPTKEY, array());
	return array_key_exists($id, $auths) ? $auths[$id] : false;
}

function ath_getlist($ids=null) {
	$auths = get_option(ATH_OPTKEY, array());
	$r = array();
	if($ids===null) {
		return $auths;
	}else {
		foreach($ids as $id)
			if(array_key_exists($id,$auths))
				$r[$id] = $auths[$id];
	}
	return $r;
}



/* Administrative Interface */

add_action('add_meta_boxes', 'ath_meta_boxes_init', 1);
add_action('admin_menu', 'ath_menu_init', 1);
add_action('admin_init', 'ath_settings_parser_init', 1);
add_action('admin_enqueue_scripts', 'ath_load_admin_js', 1);
add_action('save_post', 'ath_store_postinfo', 1, 2);

/* Register meta boxes + pages + settings with wordpress */
function ath_meta_boxes_init() {
	add_meta_box('authors_postath', __('Choose an author'), 'ath_selection_post_form', 'post');
}

function ath_load_admin_js() {
	wp_enqueue_script('ath_admin_js', plugins_url('admin.js', __FILE__), array('jquery'));
	wp_enqueue_script('ba-bbq', plugins_url('jquery.ba-bbq.min.js', __FILE__), array('jquery'));
	wp_enqueue_script('json2', plugins_url('json2.js', __FILE__));
	wp_enqueue_style('ath_admin_style', plugins_url('admin.css', __FILE__));
}

/* Form generation */

function ath_selection_list($def_id, $fName="ath_ids", $selID="", $extraOpts=array()) {
	$auths = get_option(ATH_OPTKEY, array());
	$stxt = ' selected="selected" ';
	printf('<select name="%s[]" id="%s" class="ath-selector">', $fName, $selID);
	foreach($extraOpts as $text=>$val)
			printf("<option value=\"%1\$s\" %3\$s>%2\$s</option>", htmlentities($val), htmlentities($text), $def_id==$val?$stxt:'');
	printf("<option value=\"-1\" %s>New Author</option>", $def_id==-1?$stxt:'');
	foreach($auths as $id=>$info)
		printf('<option value="%1$d" %3$s>%2$s</option>', htmlentities($id), htmlentities($info['fname']." ".$info['lname']), $def_id==$id?$stxt:'');
	print("</select>");
}

function ath_selection_post_form($post) {
	$athlist = get_post_meta($post->ID,ATH_POSTMETAKEY,true);
	$athlist = is_array($athlist) ? $athlist : array();
	ath_selection_form($athlist);
}

function ath_selection_form($athlist=array()) {
	array_unshift($athlist,-1000);
	$first = true;
	printf('<div class="cloak" style="display:none"></div>');
	foreach($athlist as $id) {
		printf('<div class="ath-field" %s>', $first ? 'style="display:none"':'');
			ath_selection_list($id, "ath_ids", "", array('<As User>'=>-1000));
			print('<a class="ath-field-kill">[X]</a>');
			print("<div class=\"new-ath-box\" style=\"display:none\">");
				ath_update_form(-1);
				print('<a class="ath-new-winclose">Close</a>');
			print("</div>");
		print("</div>");
		$first = false;
	}
	print('<a id="add-ath-btn">Aditional Author</a>');
}

function ath_update_form($id) {
	global $ATH_FIELDS;
	print("<table>");
	printf('<input type="hidden" name="ath-edit-ids[]" value="%d"/>', htmlentities($id));
	foreach($ATH_FIELDS as $fname=>$finfo) {
		printf('<tr><td class="label-col"><label for="ath-%1$s[]">%2$s</label></td><td class="input-col">', $fname, $finfo[1]);
		if($finfo[0]=="textarea") {
			printf('<textarea name="ath-%1$s[]" class="ath-%1$s"></textarea>', $fname);
		}else {
			printf('<input name="ath-%1$s[]" class="ath-%1$s" type="%2$s"/>', $fname, $finfo[0]);
		}
		print('</td></tr>');
	}
	print("</table>");
}


/*
	Function for parsing post author selection.
*/
function ath_store_postinfo($post_id, $post_object) {
	global $_POST;
	$p = $_POST;
	
	/* Check/authencate */
	if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
		return;

    if ( $post_object->post_type == 'revision' )
        return;
	if(current_user_can('edit_'.$p['POST_TYPE'], $post_id))
		return;
		
	/* Find all the author properties */
	$athInfos = array();
	$minc = 10000000;
	foreach($p as $k=>$v) {
		if(!strncmp($k, ATH_PREF, strlen(ATH_PREF)) && is_array($v)) {
			$athInfos[substr($k,strlen(ATH_PREF))] = $v;
			if(count($v)<$minc) $minc=count($v);
		}
	}

	/* Add/edit authors */
	$nAuthStack = array();
	$nids = $athInfos['edit-ids'];
	unset($athInfos['edit-ids']);
	for($i=0;$i<$minc;$i++) {
		$info = array();
		$nid = $nids[$i];
		if($nid!=-1) {
			if($nid<0) continue;
			/* This is an ID edit.*/
			foreach($athInfos as $fName=>$infoList)
				$info[$fName] = $infoList[$k];
			ath_update($nid, $info);
		}else {
			/* This is a new author */
			$valid = true;
			foreach($athInfos as $fName=>$infoList)
				if(in_array($fName,array('fname','lname')) && strlen(trim($infoList[$i]))==0) {
					$valid=false; break;
				} else {
					$info[$fName] = $infoList[$i];
				}
			
			if($valid) {
				$id = ath_create($info);
				array_push($nAuthStack, $id);
			}
		}
	}
	
	/* Store list of authors */
	$aths = ath_getlist();
	$post_id = $p['post_ID'];
	$ath_ids = is_array($p['ath_ids']) ? $p['ath_ids'] : array($p['ath_ids']);
	$athlst = array(); $k = 0;
	foreach($ath_ids as $i=>$id)
		if(is_numeric($id)) {
			if($id==-1) {
				$id = array_shift($nAuthStack);
			}
			if($id<0 || $id===false || $id===null) {
				continue;
			}
			array_push($athlst, intval($id));
			wp_set_post_terms($post_id, intval($aths[$id]['termid']), ATH_TAXNAME, true);
		}
	update_post_meta($post_id, ATH_POSTMETAKEY, $athlst);
}
?>