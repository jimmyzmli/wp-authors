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
define('ATH_TAXNAME', 'category');
define('ATH_TAXSTRFMT', 'author_id_%1$s');

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
?>