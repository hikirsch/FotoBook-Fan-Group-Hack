<?php
/*
Plugin Name: Fotobook
Plugin URI: http://www.aaronharp.com/dev/wp-fotobook/
Description: Fotobook allows you to import Facebook photo galleries directly into WordPress.  <strong>Requires PHP 5.</strong>
Author: Aaron Harp
Author URI: http://www.aaronharp.com/
Version: 3.2.1
*/

/*
Copyright 2010 Aaron Harp

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.	If not, see <http://www.gnu.org/licenses/>.
*/

global $table_prefix, $wp_version;

// plugin configuration variables
define('FB_ALBUM_TABLE', $table_prefix.'fb_albums');
define('FB_PHOTO_TABLE', $table_prefix.'fb_photos');
define('FB_POSTS_TABLE', $table_prefix.'posts');
define('FB_PLUGIN_PATH', ABSPATH.'/wp-content/plugins/fotobook/');
define('FB_PLUGIN_URL', get_option('siteurl').'/wp-content/plugins/fotobook/');
define('FB_STYLE_URL', FB_PLUGIN_URL.'styles/'.get_option('fb_style').'/');
define('FB_STYLE_PATH', FB_PLUGIN_PATH.'styles/'.get_option('fb_style').'/');
define('FB_MANAGE_URL', (get_bloginfo('version') >= 2.7 ? 'media-new.php' : 'edit.php') .'?page=fotobook/manage-fotobook.php');
define('FB_OPTIONS_URL', 'options-general.php?page=fotobook/options-fotobook.php');
define('FB_WEBSITE', 'http://www.aaronharp.com/dev/wp-fotobook/');
define('FB_VERSION', 3.21);
define('FB_DONATE', 'https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=aaron%40freshwebs%2enet&item_name=Fotobook%20Donation&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP%2dDonationsBF&charset=UTF%2d8');

// facebook configuration variables
define('FB_API_SERVER',   'http://api.facebook.com');
define('FB_LOGIN_SERVER', 'http://www.facebook.com');
define('FB_REST_SERVER',  FB_API_SERVER.'/restserver.php');
define('FB_API_KEY',      '759543efe161e1085f7f6c97791e1eab');
define('FB_API_SECRET',   '639a774490d803ce22cf8b7277403914');

// upgrade if needed
if(fb_needs_upgrade()) {
	fb_initialize();
}

$fb_message = null;

function fb_admin_scripts() {
	wp_enqueue_style('fotobook-css', FB_PLUGIN_URL.'styles/admin-styles.css');
	wp_enqueue_script('fotobook-js', FB_PLUGIN_URL.'js/admin.js', array('jquery', 'jquery-ui-sortable'), FB_VERSION);
}
add_action('load-fotobook/manage-fotobook.php', 'fb_admin_scripts');
add_action('load-fotobook/options-fotobook.php', 'fb_admin_scripts');


//--------------------//
//---FACEBOOK-CLASS---//
//--------------------//

class FacebookAPI {
	var $facebook	 = null;
	var $sessions = array();
	var $token		= null;
	var $error		= false;
	var $msg			= null;
	var $secret	 = null;
	var $progress = 0;
	var $increment = null;

	function FacebookAPI() {
		if(!class_exists('FB_Facebook'))
			include_once('facebook-platform/facebook.php');

		$facebook = new FB_Facebook(FB_API_KEY, FB_API_SECRET, null, true);
		$this->facebook = $facebook->api_client;

		global $fb_message;
		$this->msg = &$fb_message;

		// check if the facebook session is the structure from older
		// versions of Fotobook, if so remove it to start over
		$sessions = get_option('fb_facebook_session');
		if(isset($sessions['session_key'])) {
			update_option('fb_facebook_session', '');
		}

		// set sessions to the object
		$this->set_sessions();

		// get token every time for additional users
		$this->token = $this->get_auth_token();

		// determine how much to increment the progress bar after each request
		$this->progress  = get_option('fb_update_progress');
		$this->increment = count($this->sessions) > 0 ? 100 / (count($this->sessions) * 3) : 0;
	}

	/**
	 * Activates the provided UID to perform actions on that account.
	 * @param int $uid
	 * @return bool Whether or not the UID was found
	 */
	function select_session($uid) {
		foreach ($this->sessions as $session) {
			if ($session['uid'] == $uid) {
				$this->facebook->session_key = $session['session_key'];
				$this->facebook->secret = $session['secret'];
				return true;
			}
		}
		return false;
	}

	function link_active() {
		return count($this->sessions) > 0;
	}

	function get_auth_token() {
		$this->facebook->session_key = '';
		$this->facebook->secret = FB_API_SECRET;
		$this->token = $this->facebook->auth_createToken();
		if(!$this->token) {
			$this->error = true;
			$this->msg = 'Fotobook is unable to connect to Facebook.	Either there are problems communicating with their servers or your server does not allow requests to external servers.	For a host that does allow this, sign up for a <a href="http://www.dreamhost.com/r.cgi?275020/signup|fotobook">Dreamhost account</a> using the promo code "Fotobook" and you will receive a discount.';
		}
		return $this->token;
	}

	function set_sessions() {
		$sessions = get_option('fb_facebook_session');

		if(!$sessions)
			return false;

		// make sure all accounts are still active
		foreach($sessions as $key => $session) {
			$this->select_session($session['uid']);
			$user = $this->facebook->users_getInfo($session['uid'], array('name'));
			if($this->facebook->error_code == 102) {
				// if it can't get the user than remove it from the Facebook session array because
				// the link isn't active anymore
				$this->msg = 'The link to '.$sessions[$key]['name'].'\'s account was lost.	 Please authorize the account again.';
				unset($sessions[$key]);
				update_option('fb_facebook_session', $sessions);
			}
		}

		$this->sessions = $sessions;
		return count($sessions) > 0;
	}

	function get_auth_session($token) {
		$sessions = $this->sessions;

		try {
			$new_session = $this->facebook->auth_getSession($token);
		}
		catch( Exception $e ) {
			$this->error = true;
			$this->msg = 'Unable to activate account: ' . $e->getMessage();
			return false;
		}

		// check to see if this account is already linked
		$active = array();
		if(is_array($sessions)) {
			foreach($sessions as $value) { $active[] = $value['uid']; }
		}
		if(in_array($new_session['uid'], $active)) {
			$this->msg = 'That user is already linked to Fotobook.';
			return false;
		}

		// get user's name
		$this->select_session($new_session['uid']);
		$user = $this->facebook->users_getInfo($new_session['uid'], array('name'));
		$new_session['name'] = $user[0]['name'];
		//if(!$new_session['name'])
			//return false;
		if(!is_array($sessions)) $sessions = array();
		$sessions[] = $new_session;
		update_option('fb_facebook_session', $sessions);
		$this->msg = 'Fotobook is now linked to '.$new_session['name'].'\'s Facebook account.	Now you need to <a href="'.FB_MANAGE_URL.'">import</a> your albums.';

		$this->set_sessions();
		return count($sessions) > 0;
	}

	function remove_user($key) {
		// remove all of this user's albums and photos
		global $wpdb;

		$albums = fb_get_album(0, $this->sessions[$key]['uid']);
		if(is_array($albums)) {
			foreach($albums as $album) {
				fb_delete_page($album['page_id']);
			}
		}

		$wpdb->query('DELETE FROM `'.FB_ALBUM_TABLE."` WHERE `owner` = '".$this->sessions[$key]['uid'] . "'");
		$wpdb->query('DELETE FROM `'.FB_PHOTO_TABLE."` WHERE `owner` = '".$this->sessions[$key]['uid'] . "'");

		$this->msg = 'The link to '.$this->sessions[$key]['name'].'\'s Facebook account has been removed.';

		unset($this->sessions[$key]);
		update_option('fb_facebook_session', $this->sessions);
	}

	function update_progress($reset = false) {
		if($reset == true) {
			$this->progress = 0;
		}
		else {
			$this->progress = $this->progress + $this->increment;
		}
		if($this->progress > 100) {
			$this->progress = 100;
		}
		update_option('fb_update_progress', $this->progress);
		return $this->progress;
	}

	function increase_time_limit() {
		// allow the script plenty of time to make requests
		if(!ini_get('safe_mode') && !strstr(ini_get('disabled_functions'), 'set_time_limit'))
			set_time_limit(500);
	}

	function update_albums() {
		global $wpdb;

		$this->increase_time_limit();

		// reset album import progress
		$this->update_progress(true);

		// if this is the first import then reset the order at the end to make the newest on top
		$reset_order = count(fb_get_album()) > 0 ? false : true;

		// get albums for each user from Facebook
		$fb_albums = array(); $fb_photos = array();
		foreach($this->sessions as $key=>$session) {
			// setup general info
			$uid = $session['uid'];
			$this->select_session($uid);

			// get all albums
			$result = $this->facebook->photos_getAlbums($uid, null);
			if(!is_array($result)) // the current user has no photos so move on
				continue;
			$fb_albums = array_merge($fb_albums, $result);
			$this->update_progress();

			// get all photos - queries are limited to 5,000 items per query so we need to split them up
			// technically this could still error out if the user 100+ photos per album, in that case
			// the following number would need to change to 25 or lower
			$albums_per_query = 50; $i = 0; $album_offset = 0;
			while ($album_offset < count($result)) {
				$photos = $this->facebook->fql_query("SELECT pid, aid, owner, src, src_big, src_small, link, caption, created FROM photo WHERE aid IN (SELECT aid FROM album WHERE owner = '$uid' LIMIT $albums_per_query OFFSET $album_offset)");
				$fb_photos = array_merge($fb_photos, (array) $photos);
				$album_offset = ($albums_per_query * ++$i);
			}
			$this->update_progress();

			// get photos of user
			$fb_user_photos = $this->facebook->photos_get($uid, null, null);
			if($fb_user_photos) {
				foreach($fb_user_photos as $k=>$v) $fb_user_photos[$k]['aid'] = $uid;
				$fb_photos = array_merge($fb_photos, (array)$fb_user_photos);
				$fb_albums[] = array(
					'aid'=>$uid,
					'cover_pid'=>$fb_user_photos[0]['pid'],
					'owner'=>$uid,
					'name'=>'Photos of '.(count($this->sessions) > 1 ? $session['name'] : 'Me'),
					'created'=>time(),
					'modified'=>time(),
					'description'=>'',
					'location'=>'',
					'link'=>"http://www.facebook.com/photo_search.php?id=$uid",
					'size'=>count($fb_user_photos)
				);
			}

			if(!$fb_albums || $this->facebook->error_code) {
				$this->msg	 = 'Fotobook encountered an error while retrieving your photos. [Error #'.$this->facebook->error_code.']';
				return false;
			}

		}

		// put all the albums in an array with the aid as the key
		$albums = fb_get_album();
		if($albums) {
			foreach($albums as $album) {
				$wp_albums[$album['aid']] = $album;
			}
		}

		// go through all the facebook albums see which ones need to be added
		foreach($fb_albums as $fb_album) {
			$wp_album = isset($wp_albums[$fb_album['aid']]) ? $wp_albums[$fb_album['aid']] : false;

			$album_data = array(
				'cover_pid' => $fb_album['cover_pid'],
				'owner' => $fb_album['owner'],
				'name' => $fb_album['name'],
				'created' => !empty($fb_album['created']) ? date('Y-m-d H:i:s', $fb_album['created']) : '',
				'modified' => !empty($fb_album['modified']) ? date('Y-m-d H:i:s', $fb_album['modified']) : '',
				'description' => $fb_album['description'],
				'location' => $fb_album['location'],
				'link' => $fb_album['link'],
				'size' => $fb_album['size']
			);

			// if it already exists, just update it with any updated info
			if ($wp_album) {
				// check to make sure the page exists and update the name of the page if needed
				if(fb_page_exists($wp_album['page_id'])) {
					$album_data['page_id'] = $wp_album['page_id'];
					if($fb_album['name'] != $wp_album['name']) {
						fb_update_page($wp_album['page_id'], $fb_album['name']);
					}
				}
				else {
					$album_data['page_id'] = fb_add_page($fb_album['name']);
				}
				$wpdb->update(FB_ALBUM_TABLE, $album_data, array('aid' => $fb_album['aid']));
			}
			// it doesn't exist so create it
			else {
				$album_data['aid'] = $fb_album['aid'];
				$album_data['page_id'] = fb_add_page($fb_album['name']);
				$album_data['hidden'] = 0;
				$album_data['ordinal'] = fb_get_next_ordinal();
				$wpdb->insert(FB_ALBUM_TABLE, $album_data);
			}
		}

		// update the photos
		$wpdb->query('DELETE FROM '.FB_PHOTO_TABLE);
		$ordinal = 1;
		foreach($fb_photos as $photo) {
			if($last_aid !== $photo['aid']) { // reset ordinal if we're on a new album now
				$ordinal = 1;
			}
			$album_data = array(
				'pid' => $photo['pid'],
				'aid' => $photo['aid'],
				'owner' => $photo['owner'],
				'src' => $photo['src'],
				'src_big' => $photo['src_big'],
				'src_small' => $photo['src_small'],
				'link' => $photo['link'],
				'caption' => $photo['caption'],
				'created' => date('Y-m-d H:i:s', $photo['created']),
				'ordinal' => $ordinal
			);
			$wpdb->insert(FB_PHOTO_TABLE, $album_data);

			// handle ordinal
			$last_aid = $photo['aid'];
			$ordinal++;
		}

		// put IDs of all albums in an array
		foreach($fb_albums as $fb_album) {
			$album_ids[] = $fb_album['aid'];
		}

		$wp_albums = fb_get_album();
		if(count($wp_albums) > 0) {
			// delete albums that have been removed off of Facebook
			foreach($wp_albums as $fb_album) {
				if(!@in_array($fb_album['aid'], $album_ids)) {
					fb_delete_page($fb_album['page_id']);
					$wpdb->query('DELETE FROM `'.FB_ALBUM_TABLE."` WHERE `aid` = '".$fb_album['aid']."'");
				}
			}

			// delete superfluous pages
			foreach($wp_albums as $fb_album) {
				$album_pages[] = $fb_album['page_id'];
			}
			$wp_pages = $wpdb->get_results('SELECT `ID` FROM `'.FB_POSTS_TABLE."` WHERE `post_parent` = '".get_option('fb_albums_page')."'", ARRAY_A);
			foreach($wp_pages as $page) {
				if(!in_array($page['ID'], $album_pages)) {
					fb_delete_page($page['ID']);
				}
			}
		}

		// now reset the order if needed
		if($reset_order) {
			fb_reset_album_order();
		}

		if(!$this->msg) {
			$this->msg = 'Albums imported successfully.';
		}
		$this->update_progress(true);
	}
}

//---------------------//
//---SETUP-FUNCTIONS---//
//---------------------//

function fb_initialize() {
	global $wpdb;

	// add default options
	add_option('fb_version', FB_VERSION);
	add_option('fb_albums_page', 0);
	add_option('fb_number_rows', 5);
	add_option('fb_number_cols', 3);
	add_option('fb_album_cmts', 1);
	add_option('fb_thumb_size', 130);
	add_option('fb_albums_per_page', 0);
	add_option('fb_style', 'colorbox');
	add_option('fb_embedded_width', 0);
	add_option('fb_hide_pages', 0);

	$photo_table_query = "CREATE TABLE `".FB_PHOTO_TABLE."` (
	                        `pid` varchar(40),
	                        `aid` varchar(40),
	                        `owner` bigint(20) unsigned,
	                        `src` varchar(255) NOT NULL default '',
	                        `src_big` varchar(255) NOT NULL default '',
	                        `src_small` varchar(255) NOT NULL default '',
	                        `link` varchar(255) NOT NULL default '',
	                        `caption` text,
	                        `created` datetime,
	                        `ordinal` int(11) unsigned NOT NULL default 0,
									KEY `pid` (`pid`)
	                      ) TYPE = MyISAM";

	$album_table_query = "CREATE TABLE `".FB_ALBUM_TABLE."` (
	                        `aid` varchar(40),
	                        `page_id` bigint(20) unsigned,
	                        `cover_pid` varchar(40),
	                        `owner` bigint(20) unsigned,
	                        `name` varchar(255) NOT NULL default '',
	                        `description` text,
	                        `location` varchar(255) NOT NULL default '',
	                        `link` varchar(255) NOT NULL,
	                        `size` int(11) unsigned NOT NULL default 0,
	                        `created` datetime,
	                        `modified` datetime,
	                        `hidden` tinyint(1) unsigned NOT NULL default 0,
	                        `ordinal` int(11) unsigned NOT NULL default 0,
	                        UNIQUE KEY `aid` (`aid`)
	                      ) TYPE = MyISAM";

	// this is an upgrade from v1
	if(fb_table_exists(FB_ALBUM_TABLE) && $wpdb->get_results('SHOW COLUMNS FROM '.FB_ALBUM_TABLE." WHERE Field = 'timecached'")) {
		$wpdb->query('DROP TABLE '.FB_ALBUM_TABLE);
		$wpdb->query('DROP TABLE '.FB_PHOTO_TABLE);
	}

	if(!fb_table_exists(FB_PHOTO_TABLE)) {
		$wpdb->query($photo_table_query);
	}

	if(!fb_table_exists(FB_ALBUM_TABLE)) {
		$wpdb->query($album_table_query);
	}

	fb_upgrade_tables();

	update_option('fb_version', FB_VERSION);
}

function fb_table_exists($table_name) {
	global $wpdb;
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $table_name) return true;
	}
	return false;
}

function fb_needs_upgrade() {
	$upgrade = get_option('fb_version') != FB_VERSION ? true : false;
	if($upgrade)
		$tables = fb_table_exists(FB_ALBUM_TABLE);
	else
		$tables = false;
	return ($upgrade && $tables);
}

function fb_upgrade_tables() {
	global $wpdb;

	$version = get_option('fb_version');

	// this is an upgrade to fix the duplicate key issue
	if($version < 3.16 && !$wpdb->get_results('SHOW COLUMNS FROM '.FB_PHOTO_TABLE." WHERE Field = 'id'")) {
		$wpdb->query('ALTER TABLE '.FB_PHOTO_TABLE.' DROP INDEX id, ADD COLUMN id BIGINT(20) AUTO_INCREMENT NOT NULL FIRST, ADD PRIMARY KEY(id)');
	}

	// allow captions to contain more than 255 characters
	if($version < 3.17) {
		$wpdb->query('ALTER TABLE '.FB_PHOTO_TABLE.' CHANGE `caption` `caption` TEXT');
	}

	// allow underscores in photo and album IDs
	if($version < 3.18) {
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." CHANGE `pid` `pid` varchar(25) NOT NULL DEFAULT ''");
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." CHANGE `aid` `aid` varchar(25) NOT NULL DEFAULT ''");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `aid` `aid` varchar(25) NOT NULL DEFAULT ''");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `cover_pid` `cover_pid` varchar(25) NOT NULL DEFAULT ''");
	}

	// tweak the database to better accomodate the data coming in from Facebook and to
	// protect against future errors
	if ($version < 3.2) {
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." DROP `id`");
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." CHANGE `pid` `pid` varchar(40)");
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." ADD INDEX `pid` (`pid`)");
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." CHANGE `aid` `aid` varchar(40)");
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." CHANGE `owner` `owner` bigint(20) unsigned");
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." CHANGE `created` `created` datetime");
		$wpdb->query("ALTER TABLE ".FB_PHOTO_TABLE." CHANGE `ordinal` `ordinal` int(11) unsigned NOT NULL DEFAULT 0");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `aid` `aid` varchar(40)");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `page_id` `page_id` bigint(20) unsigned");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `cover_pid` `cover_pid` varchar(40)");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `owner` `owner` bigint(20) unsigned");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `description` `description` text");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `size` `size` int(11) unsigned NOT NULL DEFAULT 0");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `hidden` `hidden` tinyint(1) unsigned NOT NULL DEFAULT 0");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `ordinal` `ordinal` int(11) unsigned NOT NULL DEFAULT 0");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `created` `created` datetime");
		$wpdb->query("ALTER TABLE ".FB_ALBUM_TABLE." CHANGE `modified` `modified` datetime");
	}
}

function fb_add_pages() {
	if(get_bloginfo('version') >= 2.7)
		add_media_page('Media &rsaquo; Fotobook', 'Fotobook', 8, 'fotobook/manage-fotobook.php');
	else
		add_management_page('Manage &rsaquo; Fotobook', 'Fotobook', 8, 'fotobook/manage-fotobook.php');
	add_options_page('Settings &rsaquo; Fotobook', 'Fotobook', 8, 'fotobook/options-fotobook.php');
}

function fb_action_link($actions) {
	array_unshift($actions, '<a href="' . FB_OPTIONS_URL . '">Settings</a>');
	return $actions;
}

//---------------------//
//--WP-PAGE-FUNCTIONS--//
//---------------------//

function fb_add_page($name) {
	// disable conflicting Wordbook actions
	remove_action('publish_page', 'wordbook_publish');
	remove_action('publish_post', 'wordbook_publish');
	remove_action('transition_post_status', 'wordbook_transition_post_status');

	$post = array(
		'post_type'			=> 'page',
		'post_content'	 => '',
		'post_title'		 => $name,
		'post_status'		=> 'publish',
		'post_parent'		=> get_option('fb_albums_page'),
		'comment_status' => get_option('fb_album_cmts') ? 'open' : 'closed'
	);

	return wp_insert_post($post);
}

function fb_delete_page($id) {
	if(fb_page_exists($id)) {

		// disable conflicting Wordbook action
		remove_action('delete_post', 'wordbook_delete_post');

		wp_delete_post($id);
	}
}

function fb_update_page($id, $name, $hidden = false) {
	global $wpdb;

	$parent = get_option('fb_albums_page');
	$comment_status = get_option('fb_album_cmts') ? 'open' : 'closed';

	$array = array(
		'post_author'		=> 1,
		'post_category'	=> 0,
		'comment_status' => $comment_status,
		'post_parent'		=> $parent,
		'ID'						 => $id,
		'post_title'		 => addslashes($name),
		'post_name'			=> sanitize_title($name)
	);

	if(get_bloginfo('version') >= 2.1) {
		$array['post_status'] = 'publish';
		$array['post_type']	 = 'page';
	} else {
		$array['post_status'] = 'static';
	}

	return wp_update_post($array);
}

function fb_page_exists($id) {
	global $wpdb;
	$page_row = $wpdb->get_row('SELECT * FROM `'.FB_POSTS_TABLE."` WHERE `ID` = '$id'");
	return $page_row ? true : false;
}

//----------------------------//
//--OPTIONS/MANAGE-FUNCTIONS--//
//----------------------------//

function fb_ajax_handler() {
	if(!isset($_POST['action']) || $_POST['action'] != 'fotobook')
		return false;

	// handle hide/unhide requests
	if(isset($_POST['hide'])) {
		fb_toggle_album_hiding($_POST['hide']);
		echo 'success';
	}

	// handle order change
	elseif(isset($_POST['order'])) {
		fb_update_album_order($_POST['order']);
		echo 'success';
	}

	// handle order reset
	elseif(isset($_POST['reset_order'])) {
		fb_reset_album_order();
		echo 'The albums have been ordered by their modification date.';
	}

	// handle remove all
	elseif(isset($_POST['remove_all'])) {
		fb_remove_all();
		echo 'All albums have been removed.';
	}

	// handle update progress request
	elseif(isset($_POST['progress'])) {
		echo round(get_option('fb_update_progress'));
	}

	// handle update albums request
	elseif(isset($_POST['update'])) {
		$facebook = new FacebookAPI;
		if($facebook->link_active()) {
			$facebook->update_albums();
			echo $facebook->msg;
		} else {
			echo 'There are no accounts linked to Fotobook.';
		}
	}

	// handle albums list request
	elseif(isset($_POST['albums_list'])) {
		fb_display_manage_list($_POST['message']);
	}

	exit;
}

function fb_options_update_albums_page($new_id) {
	global $wpdb;

	$old_id = get_option('fb_albums_page');
	if($old_id == $new_id) {
		return true;
	}

	$albums = fb_get_album();
	if(sizeof($albums) > 0) {
		foreach($albums as $album) {
			$wpdb->update(FB_POSTS_TABLE, array('post_parent' => $new_id), array('ID' => $album['page_id']));
		}
	}

	update_option('fb_albums_page', $new_id);
}

function fb_options_toggle_comments($status = true) {
	global $wpdb;

	if($status) $status = 'open';
	else $status = 'closed';

	$fb_albums_page = get_option('fb_albums_page');

	$wpdb->update(FB_POSTS_TABLE, array('comment_status' => $status), array('post_parent' => $fb_albums_page));
}

function fb_albums_page_is_set() {
	global $wpdb;
	$album_page = get_option('fb_albums_page');
	return $wpdb->get_var("SELECT `ID` FROM `$wpdb->posts` WHERE `ID` = '$album_page'") ? true : false;
}

function fb_get_styles() {
	// get styles
	$styles = array();
	if ($handle = opendir(FB_PLUGIN_PATH.'styles')) {
		while (false !== ($file = readdir($handle))) {
			if(substr($file, 0, 1) != '.' && is_dir(FB_PLUGIN_PATH.'styles/'.$file))
				$styles[] = $file;
		}
		closedir($handle);
	}
	sort($styles);
	return $styles;
}

function fb_parent_dropdown( $default = 0, $parent = 0, $level = 0 ) {
	global $wpdb;

	$albums_page = get_option('fb_albums_page');

	$items = $wpdb->get_results( "SELECT `ID`, `post_parent`, `post_title` FROM `$wpdb->posts` WHERE `post_parent` = '$parent' AND `post_type` = 'page' AND `post_parent` != '$albums_page' ORDER BY `menu_order`" );

	if ( $items ) {
		foreach ( $items as $item ) {
			$pad = str_repeat( '&nbsp;', $level * 3 );
			if ( $item->ID == $default)
				$current = ' selected="selected"';
			else
				$current = '';

			echo "\n\t<option value='$item->ID'$current>$pad " . wp_specialchars($item->post_title) . "</option>";
			fb_parent_dropdown( $default, $item->ID, $level +1 );
		}
	} else {
		return false;
	}
}

function fb_days_used() {
	global $wpdb;
	$status = $wpdb->get_row("SHOW TABLE STATUS FROM ".DB_NAME." WHERE `Name` = '".FB_ALBUM_TABLE."'", ARRAY_A);
	$created = $status['Create_time'];
	$days = ceil((time() - strtotime($created)) / (60 * 60 * 24));
	return $days > 2190 || $days < 0 ? 0 : $days;
}

function fb_cron_url() {
	$secret = get_option('fb_secret');
	if(!$secret) {
		$secret = substr(md5(uniqid(rand(), true)), 0, 12);
		update_option('fb_secret', $secret);
	}
	return FB_PLUGIN_URL.'cron.php?secret='.$secret.'&update';
}

//-------------------------//
//--ALBUM/PHOTO-FUNCTIONS--//
//-------------------------//

function fb_get_album($album_id = 0, $user_id = null, $displayed_only = false) {
	global $wpdb;

	$query = 'SELECT * FROM `'.FB_ALBUM_TABLE.'` ';
	$where = '';

	if($album_id || $user_id || $displayed_only)
		$query .= "WHERE ";

	if($album_id) {
		$query .= "`aid` = '$album_id' ";
		$array = $wpdb->get_results($query, ARRAY_A);
		return $array[0];
	}
	if($user_id) {
		if($where) $where .= "AND ";
		$where .= "`owner` = '$user_id' ";
	}
	if($displayed_only) {
		if($where) $where .= "AND ";
		$where .= "`hidden` = 0 ";
	}

	$query .= $where."ORDER BY `ordinal` DESC";

	$results = $wpdb->get_results($query, ARRAY_A);

	return $results;
}

function fb_get_album_id($page_id) {
	global $wpdb;
	return $wpdb->get_var("SELECT `aid` FROM `".FB_ALBUM_TABLE."` WHERE `page_id` = '$page_id'");
}

function fb_update_album_order($order) {
	global $wpdb;
	$order = array_reverse($order);
	foreach($order as $key=>$value) {
		$wpdb->update(FB_ALBUM_TABLE, array('ordinal' => $key), array('aid' => $value));
	}
}

function fb_reset_album_order() {
	global $wpdb;
	$albums = $wpdb->get_results('SELECT `aid` FROM `'.FB_ALBUM_TABLE.'` ORDER BY `modified` ASC', ARRAY_A);
	if(!$albums)
		return false;
	foreach($albums as $key=>$album) {
		$wpdb->update(FB_ALBUM_TABLE, array('ordinal' => $key), array('aid' => $album['aid']));
	}
	return true;
}

function fb_remove_all() {
	global $wpdb;
	$pages = $wpdb->get_results('SELECT `ID` FROM `'.FB_POSTS_TABLE."` WHERE `post_parent` = '".get_option('fb_albums_page')."'", ARRAY_A);
	if($pages) {
		foreach($pages as $page) {
			// I would use the wp_delete_post function here but I'm getting a strange error
			$wpdb->query('DELETE FROM `'.FB_POSTS_TABLE."` WHERE `ID` = '{$page['ID']}'");
		}
	}
	$wpdb->query('DELETE FROM '.FB_ALBUM_TABLE);
	$wpdb->query('DELETE FROM '.FB_PHOTO_TABLE);
	return;
}

function fb_get_next_ordinal() {
	global $wpdb;
	$highest = $wpdb->get_var('SELECT `ordinal` FROM `'.FB_ALBUM_TABLE.'` ORDER BY `ordinal` DESC LIMIT 1');
	return ($highest + 1);
}

function fb_toggle_album_hiding($id) {
	global $wpdb;
	$old = $wpdb->get_row("SELECT `hidden` FROM `".FB_ALBUM_TABLE."` WHERE `aid` = '$id'");
	$new = ($old->hidden == 1) ? 0 : 1;
	$wpdb->update(FB_ALBUM_TABLE, array('hidden' => $new), array('aid' => $id));
	return true;
}

function fb_get_photos($album_id = 0) {
	global $wpdb;

	$query = 'SELECT * FROM `'.FB_PHOTO_TABLE.'` ';
	if($album_id != 0) $query .= "WHERE `aid` = '$album_id' ";
	$query .= "ORDER BY `ordinal` ASC";
	$photos = $wpdb->get_results($query, ARRAY_A);

	return $photos;
}

function fb_get_photo($id, $size = null) {
	global $wpdb;
	$query = 'SELECT * FROM `'.FB_PHOTO_TABLE."` WHERE `pid` = '$id'";
	$photo = $wpdb->get_row($query, ARRAY_A);
	switch ($size) {
		case 'small':
			return $photo['src_small'];
			break;
		case 'thumb':
			return $photo['src'];
			break;
		case 'full':
			return $photo['src_big'];
			break;
		default:
			return $photo;
			break;
	}
}

function fb_get_random_photos($count) {
	global $wpdb;
	$query = "SELECT `".FB_PHOTO_TABLE."`.`link`, `pid`, `src`, `src_big`, `src_small`, `caption`
	          FROM `".FB_PHOTO_TABLE."`, `".FB_ALBUM_TABLE."`
	          WHERE `".FB_PHOTO_TABLE."`.`aid` = `".FB_ALBUM_TABLE."`.`aid` AND `".FB_ALBUM_TABLE."`.`hidden` = 0
	          ORDER BY rand() LIMIT ".$count;
	$photos = $wpdb->get_results($query, ARRAY_A);
	for($i = 0; $i < count($photos); $i++) {
		$photos[$i]['link'] = fb_get_photo_link($photos[$i]['pid']);
	}
	return $photos;
}

function fb_get_recent_photos($count) {
	global $wpdb;
	$query = "SELECT `".FB_PHOTO_TABLE."`.`link`, `pid`, `src`, `src_big`, `src_small`, `caption`
	          FROM `".FB_PHOTO_TABLE."`, `".FB_ALBUM_TABLE."`
	          WHERE `".FB_PHOTO_TABLE."`.`aid` = `".FB_ALBUM_TABLE."`.`aid` AND `".FB_ALBUM_TABLE."`.`hidden` = 0
	          ORDER BY `".FB_PHOTO_TABLE."`.`created` DESC LIMIT ".$count;
	$photos = $wpdb->get_results($query, ARRAY_A);
	for($i = 0; $i < count($photos); $i++) {
		$photos[$i]['link'] = fb_get_photo_link($photos[$i]['pid']);
	}
	return $photos;
}

function fb_get_photo_link($photo)	{ // accepts either photo id or array of photo
	if(!is_array($photo)) {
		$photo = fb_get_photo($photo);
	}
	$album = fb_get_album($photo['aid']);
	$page_id = $album['page_id'];
	$page_link = get_permalink($page_id);
	$number_cols = get_option('fb_number_cols');
	$number_rows = get_option('fb_number_rows');
	if($number_rows == 0)
		$number_rows = ceil($photo_count / $number_cols);
	$photos_per_page = $number_cols * $number_rows;
	$album_p = $photos_per_page == 0 ? 1 : ceil($photo['ordinal'] / $photos_per_page);
	switch (get_option('fb_style')) {
		case 'lightbox':
		case 'colorbox':
			$page_link .= strstr($page_link, '?') ? '&' : '?';
			$page_link .= 'album_p='.$album_p;
			$page_link .= '#photo'.($photo['ordinal']);
			break;
		case 'embedded':
			$page_link .= strstr($page_link, '?') ? '&' : '?';
			$page_link .= 'photo='.($photo['ordinal']);
			break;
	}
	return htmlentities($page_link);
}

function fb_hidden_pages($array = array()) {
	global $wpdb;

	if(get_option('fb_hide_pages') == 1) {
		$query = 'SELECT `page_id` FROM `'.FB_ALBUM_TABLE.'`';
	} else {
		$query = 'SELECT `page_id` FROM `'.FB_ALBUM_TABLE.'` WHERE `hidden` = 1';
	}

	$results = $wpdb->get_results($query, ARRAY_A);
	if(!$results) return $array;

	foreach($results as $result) {
		$array[] = $result['page_id'];
	}
	return $array;
}

//---------------------//
//--DISPLAY-FUNCTIONS--//
//---------------------//

function fb_display($content) {
	global $wpdb;

	// get variables to check if this is part of fotobook
	$post           = $GLOBALS['post'];
	$post_id        = $post->ID;
	$post_parent    = $post->post_parent;
	$albums_page_id = get_option('fb_albums_page');

	// don't show password protected pages
	if (!empty($post->post_password) && $_COOKIE['wp-postpass_'. COOKIEHASH] != $post->post_password) {
		return $content;
	}

	if($post_id != $albums_page_id && $post_parent != $albums_page_id) {
		return $content;
	}

	// display all albums
	if($post_id == $albums_page_id) {
		return fb_display_main($content);
	}

	// display individual albums
	if($post_parent == $albums_page_id && $post_parent != 0) {
		if(isset($_GET['photo']) && get_option('fb_style') == 'embedded') {
			return fb_display_photo($content, $post_id, $_GET['photo']);
		} else {
			return fb_display_album($content, $post_id);
		}
	}

	return $content;
}

function fb_display_main($content) {
	remove_filter('the_content','wpautop');

	// buffer the output
	ob_start();

	// get albums
	$albums = fb_get_album(null, null, true);
	$album_count = sizeof($albums);
	if(!$albums) {
		echo "<p>There are no albums.</p>";
		return;
	}
	$album_link = get_permalink(get_option('fb_albums_page'));
	array_unshift($albums, ''); // moves all the keys down
	unset($albums[0]);

	// determine pagination
	$albums_per_page = get_option('fb_albums_per_page');
	if($albums_per_page == 0) {
		$albums_per_page = $album_count;
	}
	$page_count = ceil($album_count / $albums_per_page);
	$curr_page = $_GET['album_p'] <= $page_count && $_GET['album_p'] > 0 ? $_GET['album_p'] : 1;
	$first_album = (($curr_page-1) * $albums_per_page) + 1;
	$last_album = $first_album + $albums_per_page - 1;
	$last_album = $last_album > $album_count ? $album_count : $last_album;

	// generate pagination
	if($page_count == 1) {
		$prev_link = ''; $next_link = ''; $pagination = '&nbsp;';
	} else {
		$prev_link = $curr_page > 1 ? $curr_page - 1 : false;
		if($prev_link !== false)
			$prev_link = $album_link.(strstr($album_link, '?') ? '&amp;album_p='.($prev_link) : '?album_p='.($prev_link));
		$next_link = $curr_page + 1 <= $page_count ? $curr_page + 1 : false;
		if($next_link)
			$next_link = $album_link.(strstr($album_link, '?') ? '&amp;album_p='.($next_link) : '?album_p='.($next_link));
		$pagination = '';
		for($i = 1; $i <= $page_count; $i++) {
			if($i == $curr_page)
				$pagination .= '<b>'.$i.'</b>';
			else {
				$link = $album_link.(strstr($album_link, '?') ? '&amp;album_p='.$i : '?album_p='.$i);
				$pagination .= "<a href='$link'>".($i)."</a>";
			}
		}
	}

	// now get rid of all albums in the array that aren't displayed on this page
	$albums = array_slice_preserve_keys($albums, $first_album-1, $albums_per_page);
	foreach($albums as $key=>$album) {
		$albums[$key]['link']	= get_permalink($albums[$key]['page_id']);
		$albums[$key]['thumb'] = fb_get_photo($albums[$key]['cover_pid'], 'thumb');
	}

	include(FB_STYLE_PATH.'main.php');
	?>
	<div id="fotobook-credits" style="text-align: right;">
		<small>Powered by <a href="http://www.aaronharp.com/dev/wp-fotobook/">Fotobook</a></small>
	</div>
<?php
	// now capture the buffer and add it to $content
	$content .= ob_get_clean();
	return $content;
}

function fb_display_album($content, $page_id) {
	// turn off content filter so that <p> and <br> tags aren't added
	remove_filter('the_content','wpautop');

	// buffer the output
	ob_start();

	$albums_page_link = htmlentities(get_permalink(get_option('fb_albums_page')));
	$page_link = get_permalink($page_id);
	$album_id = fb_get_album_id($page_id);
	$album = fb_get_album($album_id);
	$photos = fb_get_photos($album_id);
	$photo_count = sizeof($photos);
	if($photo_count == 0) {
		echo '<p>This album is empty.</p>';
		return false;
	}
	array_unshift($photos, ''); // moves all the keys down
	unset($photos[0]);

	// check if page is hidden
	if($album['hidden'] == 1) {
		$message = '<p>This album is not available. <a href="'.get_permalink(get_option('fb_albums_page')).'">Return to albums</a>.</p>';
		return $message.$content;
	}

	// html encode all captions
	foreach($photos as $key=>$photo) {
		$photos[$key]['caption'] = function_exists('seems_utf8') && seems_utf8($photo['caption'])
															 ? htmlentities($photo['caption'], ENT_QUOTES, 'utf-8')
															 : htmlentities($photo['caption'], ENT_QUOTES);
	}

	$thumb_size = get_option('fb_thumb_size');
	$number_cols = get_option('fb_number_cols');
	$number_rows = get_option('fb_number_rows') == 0 ? ceil($photo_count / $number_cols) : get_option('fb_number_rows');
	$photos_per_page = $number_cols * $number_rows;

	$page_count = ceil($photo_count / $photos_per_page);
	$curr_page = ($_GET['album_p'] <= $page_count) && ($_GET['album_p'] > 0) ? $_GET['album_p'] : 1;
	$first_photo = ($curr_page - 1) * $photos_per_page + 1;
	$last_photo = $first_photo + $photos_per_page - 1;
	$last_photo = $last_photo > $photo_count ? $photo_count : $last_photo;
	$rows_curr_page = ceil(($last_photo - $first_photo + 1) / $number_cols);

	// generate pagination
	if($page_count == 1) {
		$prev_link = ''; $next_link = ''; $pagination = '&nbsp;';
	} else {
		$prev_link = $curr_page > 1 ? $curr_page - 1 : false;
		if($prev_link !== false)
			$prev_link = $page_link.(strstr($page_link, '?') ? '&amp;album_p='.($prev_link) : '?album_p='.($prev_link));
		$next_link = $curr_page < $page_count ? $curr_page + 1 : null;
		if($next_link)
			$next_link = $page_link.(strstr($page_link, '?') ? '&amp;album_p='.($next_link) : '?album_p='.($next_link));
		$pagination = '';
		for($i = 1; $i <= $page_count; $i++) {
			if($i == $curr_page)
				$pagination .= '<b>'.$i.'</b>';
			else {
				$link = $page_link.(strstr($page_link, '?') ? '&amp;album_p='.$i : '?album_p='.$i);
				$pagination .= "<a href='$link'>".($i)."</a>";
			}
		}
	}

	// album info
	$description = $album['description'];
	$location = $album['location'];

	// add hidden links for all images before so that next and previous
	// buttons in lightbox will display these images as well
	$hidden_top = ''; $hidden_bottom = '';
	for($i = 1; $i < $first_photo; $i++) {
		$hidden_top .= "<a href=\"{$photos[$i]['src_big']}\" rel=\"fotobook\" title=\"{$photos[$i]['caption']}\"></a>";
	}
	for($i = $last_photo+1; $i <= $photo_count; $i++) {
		$hidden_bottom .= "<a href=\"{$photos[$i]['src_big']}\" rel=\"fotobook\" title=\"{$photos[$i]['caption']}\"></a>";
	}

	// now get rid of all photos in the array that aren't displayed on this page
	$photos = array_slice_preserve_keys($photos, $first_photo-1, $photos_per_page);

	?>
	<br />
	<p style="display: none"><?php echo $hidden_top ?></p>
	<?php include(FB_STYLE_PATH.'album.php') ?>
	<p style="display: none"><?php echo $hidden_bottom ?></p>
<?php
	$content .= ob_get_clean();
	return $content;
}

function fb_display_photo($content, $page_id, $photo) {
	// turn off content filter so that <p> and <br> tags aren't added
	remove_filter('the_content','wpautop');

	// buffer the output
	ob_start();

	// get photos
	$photos = fb_get_photos(fb_get_album_id($page_id));
	$photo_count = sizeof($photos);
	array_unshift($photos, ''); // moves all the keys down
	unset($photos[0]);

	// pagination
	$page_link = get_permalink($page_id);
	$curr = ($photo <= $photo_count && $photo > 0) ? $photo : 1;
	$next = ($curr + 1 <= $photo_count) ? $curr + 1 : false;
	$prev = ($curr != 1) ? $curr - 1 : false;
	if($next)
		$next_link = $page_link.(strstr($page_link, '?') ? '&amp;photo='.($next) : '?photo='.($next));
	if($prev)
		$prev_link = $page_link.(strstr($page_link, '?') ? '&amp;photo='.($prev) : '?photo='.($prev));
	$photo = $photos[$curr];

	// html encode caption
	$photo['caption'] = function_exists('seems_utf8') && seems_utf8($photo['caption'])
											? htmlentities($photo['caption'], ENT_QUOTES, 'utf-8')
											: htmlentities($photo['caption'], ENT_QUOTES);

	// get max width
	$width = get_option('fb_embedded_width');

	include(FB_STYLE_PATH.'photo.php');

	$content .= ob_get_clean();
	return $content;
}

function fb_display_manage_list($message = '') {
	$albums = fb_get_album();

	if($message != ''): ?>
	<div id="fb-message" class="updated fade" style="display: none"><p><?php echo $message ?></p></div>
	<?php endif; ?>

	<?php if($albums) { ?>
	<ul id="fb-manage-list">
		<?php
		for($i = 0; $i < count($albums); $i++):
		$album = $albums[$i];
		$thumb = fb_get_photo($album['cover_pid'], 'small');
		$class = ($album['hidden'] == 1) ? 'disabled' : '';
		?>
		<li class="<?php echo $class ?>" id="album_<?php echo $album['aid'] ?>">
			<div class="thumb" style="background-image:url(<?php echo $thumb ?>);"></div>
			<div>
				<h3><?php echo $album['name'] ?><small style="font-weight: normal"></h3>
				<div class="description">
					<?php echo $album['size'] ?> Photos</small><br />
					Created: <?php echo mysql2date('m-d-Y', $album['created']) ?>, Modified: <?php echo mysql2date('m-d-Y', $album['modified']) ?><br />
					<span>
						<a href="<?php echo get_option('siteurl').'?page_id='.$album['page_id'] ?>" target="_blank">View</a>
						<a href="#" class="toggle-hidden"><?php echo $album['hidden'] ? 'Show' : 'Hide' ?></a>
					</span>
				</div>
			</div>
			<div style="clear: left"></div>
		</li>
		<?php endfor; ?>
	</ul>
	<?php } else { ?>
	<p>There are no albums.</p>
	<?php
	}
}

function fb_info_box() {
?>
	<div id="fb-info">
		<h3>Info</h3>
		<?php if(fb_days_used() >= 100): ?>
		<p>You've used Fotobook for <?php echo fb_days_used(); ?> days.  If you find it useful, consider <a href="<?php echo FB_DONATE ?>">donating</a> $5.  It would be very appreciated.</p>
		<?php endif; ?>
		<ul>
			<li><a href="http://www.aaronharp.com/dev/wp-fotobook/">Fotobook Home</a></li>
			<li><a href="http://wordpress.org/tags/fotobook?forum_id=10">Support Forum</a></li>
			<li><a href="<?php echo FB_DONATE ?>">Donate</a></li>
			<li><a href="http://www.dreamhost.com/r.cgi?275020/signup|fotobook">DreamHost</a></li>
			<li><a href="http://www.aaronharp.com/dev/">Need a developer?</a></li>
		</ul>

	</div>
<?php
}

function fb_display_scripts() {
	$post = $GLOBALS['post'];
	$albums_page = get_option('fb_albums_page');
	if ($post->ID == $albums_page || $post->post_parent == $albums_page) {
		if (get_option('fb_style') == 'colorbox') {
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-colorbox', FB_STYLE_URL . 'js/colorbox.js', array('jquery'));
		}
		if (file_exists(FB_STYLE_PATH . 'js/init.js')) {
			wp_enqueue_script('fotobook-style', FB_STYLE_URL . 'js/init.js', array('jquery'));
		}
	}
	if (is_active_widget('fb_photos_widget')) {
		wp_enqueue_script('fotobook-widget', FB_PLUGIN_URL.'js/widget.js');
	}
}

function fb_display_styles() {
	$post = $GLOBALS['post'];
	$albums_page = get_option('fb_albums_page');
	if ($post->ID == $albums_page || $post->post_parent == $albums_page) {
		if (get_option('fb_style') == 'colorbox') {
			wp_enqueue_style('fotobook-colorbox', FB_STYLE_URL.'colorbox.css');
		}
		wp_enqueue_style('fotobook-style', FB_STYLE_URL.'style.css');
	}
	if (is_active_widget('fb_photos_widget')) {
		wp_enqueue_style('fotobook-widget', FB_PLUGIN_URL . 'styles/sidebar-style.css');
	}
}

//------------------------//
//--PHOTOS-TAB-FUNCTIONS--//
//------------------------//

function fb_add_upload_tab($tabs) {
	// 0 => tab display name, 1 => required cap, 2 => function that produces tab content, 3 => total number objects OR array(total, objects per page), 4 => add_query_args
	$tab = array('fotobook' => array('Fotobook', 'upload_files', 'fb_upload_tab', 0));
	return array_merge($tabs, $tab);
}

function fb_upload_tab() {
	// generate link without aid variable
	$vars = explode('&', $_SERVER['QUERY_STRING']);
	if(stristr($vars[count($vars)-1], 'aid')) {
		unset($vars[count($vars)-1]);
	}
	$link = 'upload.php?'.implode('&', $vars);
	echo '<br />';
	fb_photos_tab($link);
}

function fb_add_media_tab($tabs) {
	if(isset($_GET['type']) && $_GET['type'] == 'image')
		$tabs['fotobook'] = 'Fotobook';
	return $tabs;
}

function media_upload_fotobook() {
	global $wpdb, $wp_query, $wp_locale, $type, $tab, $post_mime_types;
	wp_enqueue_script('admin-gallery');
	wp_enqueue_script('media-upload');
	return wp_iframe( 'media_upload_fotobook_tab', $errors );
}

function media_upload_fotobook_tab($errors) {
	// generate link without aid variable
	$vars = explode('&', $_SERVER['QUERY_STRING']);
	if(stristr($vars[count($vars)-1], 'aid')) {
		unset($vars[count($vars)-1]);
	}
	$link = 'media-upload.php?'.implode('&', $vars);
	media_upload_header();
	fb_photos_tab($link);
}

function fb_photos_tab($link) { ?>
	<style type="text/css">
	<?php include(FB_PLUGIN_PATH.'styles/admin-styles.css'); ?>
	</style>
	<form id="image-form">
	<?php
	if(isset($_GET['aid'])):
	$album = fb_get_album($_GET['aid']);
	$photos = fb_get_photos($_GET['aid']);
	?>
	<script language="javascript">
	var fbThumb; var fbFull; var fbLink; var fbCaption;
	function findPos(obj) {
		var curleft = curtop = 0;
		if (obj.offsetParent) {
			do {
				curleft += obj.offsetLeft;
				curtop += obj.offsetTop;
			} while (obj = obj.offsetParent);
		}
		return [curleft,curtop];
	}
	function insertPopup(obj, thumb, full, link, caption) {
		fbThumb = thumb;	fbFull		= full;
		fbLink	= link;	 fbCaption = caption;
		var popup = document.getElementById('fb-insert-popup');
		popup.style.display = 'block';
		popup.style.left		= findPos(obj)[0]+'px';
		popup.style.top		 = findPos(obj)[1]+'px';
	}
	function insertPhoto(size) {
		var src;
		if (size == 'thumb')
			src = fbThumb;
		else
			src = fbFull;
		var html =
			'<a href="'+fbLink+'" class="fb-photo">' +
			'<img src="'+src+'" alt="'+fbCaption+'" />' +
			'</a> ';
		wpgallery.getWin().send_to_editor(html);
	}
	</script>
	<h3><?php echo $album['name'] ?> <a href="<?php echo $link ?>" style="font-size: 11px">&laquo; Back to Albums</a></small></h2>

	<div id="fb-insert-popup">
		Insert...<br />
		&nbsp; <a href="#" onclick="insertPhoto('thumb'); return false;">Thumbnail</a><br />
		&nbsp; <a href="#" onclick="insertPhoto('full'); return false;">Full</a><br />
		<br /><a href="#" onclick="this.parentNode.style.display = 'none'; return false;">[close]</a>
	</div>

	<ul id="fb-photos-tab">
		<?php foreach($photos as $photo): ?>
		<li>
			<a href="#" onclick="insertPopup(this.parentNode, '<?php echo $photo['src'] ?>','<?php echo $photo['src_big'] ?>','<?php echo fb_get_photo_link($photo['pid']) ?>', '<?php echo addslashes($photo['caption']) ?>'); return false;">
				<img src="<?php echo $photo['src']; ?>" />
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php
	else:
	$albums = fb_get_album();
	?>
	<h3>Select an Album</h3>
	<ul id="fb-manage-list">
		<?php
		foreach($albums as $album):
		$thumb = fb_get_photo($album['cover_pid'], 'small');
		?>
		<li id="album_<?php echo $album['aid'] ?>" style="cursor: default">
			<div class="thumb" style="background-image:url(<?php echo $thumb ?>);"></div>
			<div>
				<h3><a href="<?php echo $link ?>&amp;aid=<?php echo $album['aid'] ?>"><?php echo $album['name'] ?></a></h3>
				<div class="description">
					<small style="font-weight: normal"><?php echo $album['size'] ?> Photos</small><br />
				</div>
			</div>
			<div style="clear: both"></div>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
	</form>
	<?php
}

// This function was removed from WP 2.5.1 to 2.6
if (!function_exists('media_admin_css')) {
	function media_admin_css() {
		wp_admin_css('css/media');
	}
}

if(get_bloginfo('version') >= 2.5) {
	add_filter('media_upload_tabs', 'fb_add_media_tab');
	add_filter('media_upload_fotobook', 'media_upload_fotobook');
	add_action('admin_head_media_upload_fotobook_tab', 'media_admin_css');
} else {
	add_filter('wp_upload_tabs', 'fb_add_upload_tab');
}

//--------------------//
//--WIDGET-FUNCTIONS--//
//--------------------//

function fb_widget_init() {
	if (!function_exists('register_sidebar_widget'))
		return;

	register_sidebar_widget(array('Fotobook Photos', 'widgets'), 'fb_photos_widget');
	register_widget_control(array('Fotobook Photos', 'widgets'), 'fb_photos_widget_control', 300, 150);
	register_sidebar_widget(array('Fotobook Albums', 'widgets'), 'fb_albums_widget');
	register_widget_control(array('Fotobook Albums', 'widgets'), 'fb_albums_widget_control', 300, 150);

}

function fb_photos_widget($count = '4', $mode = 'random', $size = '80') {

	// this is a widget
	if(is_array($count)) {
		extract($count);

		$options = get_option('fb_photos_widget');
		if(is_array($options)) {
			$title = $options['title'];
			$count = $options['count'];
			$size	= $options['size'];
			$mode	= $options['mode'];
		}

		echo $before_widget . $before_title . $title . $after_title;
	}

	if($mode == 'recent') {
		$photos = fb_get_recent_photos($count);
	} else {
		$photos = fb_get_random_photos($count);
	}

	// if the thumbnail size is set larger than the size of
	// the thumbnail, use the full size photo
	if($size > 130) {
		foreach($photos as $key=>$photo)
			$photos[$key]['src'] = $photos[$key]['src_big'];
	}

	if($photos) {
		include(FB_PLUGIN_PATH.'styles/photos-widget.php');
	} else {
		echo '<p>There are no photos.</p>';
	}

	echo $after_widget;
}

function fb_photos_widget_control() {
	$options = get_option('fb_photos_widget');
	if (!is_array($options) )
		$options = array('title'=>'Random Photos', 'count'=>'4', 'style'=>'list','size'=>'80','mode'=>'random');
	if ( $_POST['fb-photos-submit'] ) {
		$options['title'] = strip_tags(stripslashes($_POST['fb-photos-title']));
		$options['count'] = is_numeric($_POST['fb-photos-count']) ? $_POST['fb-photos-count'] : 4;
		$options['style'] = $_POST['fb-photos-style'];
		$options['mode'] = $_POST['fb-photos-mode'];
		$options['size']	= is_numeric($_POST['fb-photos-size']) ? $_POST['fb-photos-size'] : 60;
		update_option('fb_photos_widget', $options);
	}
	$options['title'] = htmlspecialchars($options['title'], ENT_QUOTES);

	?>
	<p><label for="fb-title"><?php echo __('Title:'); ?>
		<input style="width: 200px;" id="fb-photos-title" name="fb-photos-title" type="text" value="<?php echo $options['title'] ?>" />
	</label></p>
	<p><label for="fb-count"><?php echo __('Number of Pictures:'); ?>
		<input style="width: 80px;" id="fb-photos-count" name="fb-photos-count" type="text" value="<?php echo $options['count'] ?>" />
	</label></p>
	<p><label for="fb-size"><?php echo __('Thumbnail Size:'); ?>
		<input style="width: 80px;" id="fb-photos-size" name="fb-photos-size" type="text" value="<?php echo $options['size'] ?>" />
	</label></p>
	<p><?php echo __('Mode:'); ?>
		<label><input type="radio" name="fb-photos-mode" value="recent" <?php echo $options['mode'] == 'recent' ? 'checked ' : '' ?>/> Recent Photos</label>
		<label><input type="radio" name="fb-photos-mode" value="random" <?php echo $options['mode'] == 'random' ? 'checked ' : '' ?>/> Random Photos</label>
	</p>
	<input type="hidden" name="fb-photos-submit" value="1" />
	<?php
}

function fb_albums_widget($count = '4', $mode = 'recent') {
	global $wpdb;

	if(is_array($count)) {
		extract($count);

		$options = get_option('fb_albums_widget');
		if(is_array($options)) {
			$title = $options['title'];
			$mode	= $options['mode'];
			$count = $options['count'];
		}

		echo $before_widget . $before_title . $title . $after_title;
	}

	$count = (int) $count;

	if($mode == 'recent') {
		$albums = $wpdb->get_results('SELECT `name`, `aid`, `page_id` FROM `'.FB_ALBUM_TABLE.'` WHERE `hidden` = 0 ORDER BY `modified` DESC LIMIT '.$count, ARRAY_A);
	} else {
		$albums = $wpdb->get_results('SELECT `name`, `aid`, `page_id` FROM `'.FB_ALBUM_TABLE.'` WHERE `hidden` = 0 ORDER BY rand() LIMIT '.$count, ARRAY_A);
	}

	if($albums) {
		include(FB_PLUGIN_PATH.'styles/albums-widget.php');
	} else {
		echo '<p>There are no albums.</p>';
	}

	echo $after_widget;
}

function fb_albums_widget_control() {
	$options = get_option('fb_albums_widget');
	if (!is_array($options) )
		$options = array('title'=>'Recent Albums', 'mode'=>'recent', 'count'=>'4');
	if ( $_POST['fb-albums-submit'] ) {
		$options['title'] = strip_tags(stripslashes($_POST['fb-albums-title']));
		$options['count'] = is_numeric($_POST['fb-albums-count']) ? $_POST['fb-albums-count'] : 4;
		$options['mode'] = $_POST['fb-albums-mode'];
		update_option('fb_albums_widget', $options);
	}
	$options['title'] = htmlspecialchars($options['title'], ENT_QUOTES);

	?>
	<p><label for="fb-title"><?php echo __('Title:'); ?>
		<input style="width: 200px;" id="fb-albums-title" name="fb-albums-title" type="text" value="<?php echo $options['title'] ?>" />
	</label></p>
	<p><label for="fb-count"><?php echo __('Number of Albums:'); ?>
		<input style="width: 80px;" id="fb-albums-count" name="fb-albums-count" type="text" value="<?php echo $options['count'] ?>" />
	</label></p>
	<p><?php echo __('Mode:'); ?>
		<label><input type="radio" name="fb-albums-mode" value="recent" <?php echo $options['mode'] == 'recent' ? 'checked ' : '' ?>/> Recent Albums</label>
		<label><input type="radio" name="fb-albums-mode" value="random" <?php echo $options['mode'] == 'random' ? 'checked ' : '' ?>/> Random Albums</label>
	</p>
	<input type="hidden" name="fb-albums-submit" value="1" />
	<?php
}

//------------------------//
//--INTEGRATE-IT-INTO-WP--//
//------------------------//

add_filter('wp_list_pages_excludes', 'fb_hidden_pages');
add_action('activate_fotobook/fotobook.php', 'fb_initialize');
add_action('plugin_action_links_fotobook/fotobook.php', 'fb_action_link');
add_action('admin_menu', 'fb_add_pages');
add_filter('the_content', 'fb_display');
add_action('widgets_init', 'fb_widget_init');
add_action('template_redirect', 'fb_display_scripts');
add_action('wp_print_styles', 'fb_display_styles');
add_action('wp_ajax_fotobook', 'fb_ajax_handler');

//---------------------//
//--GENERAL-FUNCTIONS--//
//---------------------//

function array_slice_preserve_keys($array, $offset, $length = null) {
	// PHP >= 5.0.2 is able to do this itself
	//if((int)str_replace('.', '', phpversion()) >= 502)
		//return(array_slice($array, $offset, $length, true));

	// prepare input variables
	$result = array();
	$i = 0;
	if($offset < 0)
		$offset = count($array) + $offset;
	if($length > 0)
		$endOffset = $offset + $length;
	else if($length < 0)
		$endOffset = count($array) + $length;
	else
		$endOffset = count($array);

	// collect elements
	foreach($array as $key=>$value)
	{
		if($i >= $offset && $i < $endOffset)
			$result[$key] = $value;
		$i++;
	}

	// return
	return($result);
}

if(!function_exists('file_put_contents')) {
	function file_put_contents($n,$d) {
		$f=@fopen($n,"w");
		if (!$f) {
		 return false;
		} else {
		 fwrite($f,$d);
		 fclose($f);
		 return true;
		}
	}
}

?>