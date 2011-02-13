<?php

define('WP_USE_THEMES', false);
require('../../../wp-blog-header.php');

// handle cron request
if(isset($_GET['update']) && isset($_GET['secret']) && $_GET['secret'] == get_option('fb_secret')) {
	echo 'Updating Fotobook (be patient)...';
	ob_flush(); flush();
	$facebook = new FacebookAPI;
	if($facebook->link_active())
		$facebook->update_albums();
	echo 'Done';
} else {
	echo 'Invalid URL';
}



?>