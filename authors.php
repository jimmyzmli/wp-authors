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

function ath_reset() {
	global $wpdb;
	delete_option(ATH_OPTKEY);
	delete_option(ATH_MAXIDKEY);
	delete_option(ATH_CSSKEY);
	delete_option(ATH_HTMLKEY);
	$wpdb->query(
		$wpdb->prepare(sprintf("DELETE FROM $wpdb->postmeta WHERE meta_key = '%s'", ATH_POSTMETAKEY))
	);
	$wpdb->query(
		$wpdb->prepare(
			sprintf("DELETE FROM $wpdb->term_relationships WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE taxonomy='%s')", ATH_TAXNAME)
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			sprintf("DELETE FROM $wpdb->terms WHERE term_id IN (SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy='%s'", ATH_TAXNAME)
		)
	);
	$wpdb->query(
		$wpdb->prepare(sprintf("DELETE FROM $wpdb->term_taxonomy WHERE taxonomy='%s'", ATH_TAXNAME))
	);
}


/* User Interface */

add_filter('the_content', 'ath_box_append', 1);
add_action('wp_head', 'ath_box_header');

function ath_box_append($content) {
	if(is_single()) {
		$id = get_the_ID();
		$athlist = get_post_meta($id, ATH_POSTMETAKEY, true);
		$athdata = ath_getlist(is_array($athlist) ? $athlist : array());
		$template = get_option(ATH_HTMLKEY, ATH_DEFAULTHTML);
		foreach($athdata as $id=>$info) {
			foreach($info as $k=>$v)
				$info[$k] = htmlentities($v);
			extract($info, EXTR_PREFIX_ALL, 'a');
			/* Set up special path variable */
			$a_path = plugins_url("",__FILE__);
			$a_postsby_link = get_term_link(intval($a_termid), ATH_TAXNAME);
			if(is_wp_error($a_postsby_link))
				$a_postsby_link = get_bloginfo('url').'?'.ATH_TAXNAME.'='.$a_termid;
			$pid = get_the_ID();
			
			$content .= '<div class="ath-desc">'.preg_replace('/\${([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}/e', '$a_\1', $template).'</div>';
		}
	}
	return $content;
}

function ath_box_header() {
	if(is_single()) {
		print('<style type="text/css">');
			print(htmlentities(get_option(ATH_CSSKEY, ATH_DEFAULTCSS)));
		print('</style>');
	}
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

function ath_menu_init() {
	add_submenu_page('tools.php', "Author Profiles", "Author Profiles", "edit_theme_options", "authors_profiles_edit", 'ath_profiles_edit_page');
}

function ath_settings_parser_init() {
	register_setting(ATH_SETTINGSKEY, 'p', 'ath_store_forminfo');
	register_setting(ATH_SETTINGSKEY, 's', 'ath_store_miscinfo');
	register_setting(ATH_SETTINGSKEY, 'rst', 'ath_reset_form');
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

function ath_profiles_edit_page() {
	$min_id = is_numeric($_GET['min_id']) ? $_GET['min_id'] : 0;
	$c = is_numeric($_GET['c']) ? $_GET['c'] : ATH_LIST_PER_PAGE;
	$auths = ath_getlist();
	$ids = array_keys($auths); sort($ids);
	foreach($ids as $i=>$id)
		if($id >= $min_id) {
			$keys = array_slice($ids, $i, $c);
			$r = array();
			foreach($keys as $id)
				$r[$id] = $auths[$id];
			$auths = $r;
			break;
		}
	if(!is_array($keys)) {
		/* Nothing on list */
		if(count($ids)%$c > 0)
			$i = count($ids)-count($ids)%$c;
		else
			$i = count($ids)-$c;
		$auths = array();
	}else {
		$i = $i-$c-$c%2;
		if(!is_array($auths))
			$auths = array();
	}
	printf('<script type="text/javascript">window.authinfo=%s;window.authlastid=%d;</script>', json_encode((object)$auths), $ids[$i]);
	print('<form action="options.php" id="ath-prof-form" method="POST">');
		printf('<div class="cloak" style="display:none"></div>');
		settings_fields(ATH_SETTINGSKEY);
		print('<input type="hidden" name="p" id="ath-info-encoded"/>');
		if(count($auths) == 0 || $min_id <= min($ids)):
		print('<div>');
			printf('<div class="ath-html">HTML Template<textarea name="s[ath-html]">%s</textarea></div>', htmlentities(get_option(ATH_HTMLKEY,ATH_DEFAULTHTML)));
			printf('<div class="ath-css">CSS<textarea name="s[ath-css]">%s</textarea></div>', htmlentities(get_option(ATH_CSSKEY,ATH_DEFAULTCSS)));
		print('<div style="clear:both"></div></div>');
		endif;
		print('<div class="new-ath-box" style="display:none">');
				ath_update_form(-1);
				print('<a class="ath-new-winclose">Close</a>');
		print('</div>');
		print('<table id="ath-table">');
			print('<tr class="ath-entry" style="display:none"><td class="ath-name"></td><td><a class="ath-edit-btn"><input type="hidden" class="ath-id"/>Edit</a></td><td><a class="ath-del-btn"><input type="hidden" class="ath-id"/>Delete</a></td></tr>');
		print('</table>');
		print('<a class="ath-create-btn">New Author</a>');
		print('<a id="last-page-btn">Last Page</a><a id="next-page-btn">Next Page</a>');
		print('<input type="submit" name="rst" class="rst-btn" value="Reset"/>');
		print('<input type="submit" class="submit-btn" value="Save"/>');
	print('</form>');
}

function ath_reset_form($opt) {
	if(strlen(trim($opt)) > 0)
		ath_reset();
}

/*
	Function for parsing misc settings
*/
function ath_store_miscinfo($opts) {
	if(is_array($opts)) {
		if(array_key_exists('ath-html',$opts) && is_string($opts['ath-html']))
			update_option(ATH_HTMLKEY, $opts['ath-html']);
		if(array_key_exists('ath-css',$opts) && is_string($opts['ath-css']))
			update_option(ATH_CSSKEY, $opts['ath-css']);
	}
	return NULL;
}

/*
	Function for parsing author form author management.
*/
function ath_store_forminfo($opts_json) {
	if(strlen(trim($opts_json)) > 0) {
		$aths = ath_getlist();
		$opts = json_decode($opts_json, true);
		if(is_array($opts)) {
			/* Create new authors */
			$newauths = $opts[-1];
			if(is_array($newauths)) {
				foreach($newauths as $info)
					if(ath_check_info($info)===true)
						ath_create($info);
			}
			unset($opts[-1]);
			/* Update authors */
			foreach($opts as $id=>$info) {
				if($info["deleteflag"]===true) {
					ath_update($id, NULL);
					wp_delete_term($aths[$id]['termid'], ATH_TAXNAME);
				}else {
					ath_safe_updateinfo($id,$info);
				}
			}
		}
	}
	/* Don't store anything */
	return NULL;
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