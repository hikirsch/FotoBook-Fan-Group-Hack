<?php

/*
Fotobook Management Panel
*/

// get facebook authorization token
$facebook = new FacebookAPI;

?>

<?php if(!$facebook->link_active()): ?>
<div id="message" class="error fade"><p>There is no Facebook account linked to this plugin.  Change that in the <a href="<?php echo FB_OPTIONS_URL ?>">settings panel</a>.</p></div>
<?php endif; ?>

<?php if($fb_message): ?>
<div id="message" class="<?php echo $facebook->error ? 'error' : 'updated' ?> fade"><p><?php echo $fb_message ?></p></div>
<?php endif; ?>

<div class="wrap">

	<div id="fb-panel">
		<?php fb_info_box() ?>
		<h2 style="clear: none"><?php _e('Fotobook &rsaquo; Manage'); ?> <span><a href="<?php echo FB_OPTIONS_URL ?>">Change Settings &raquo;</a></span></h2>
		<?php if(!fb_albums_page_is_set()): ?>
		<p><?php _e('This is where you can import and manage your Facebook albums.	You can drag the albums to change the order.'); ?></p>
		<p><em>You must select a page for the photo gallery in the <a href="<?php echo FB_OPTIONS_URL ?>">Fotobook Options</a> panel before you can import albums.</em></p>
		<?php else: ?>
		<?php if($facebook->link_active()): ?>
		<div class="nav">
			<input type="button" class="button-secondary" name="get" value="Get Albums" style="width: 100px" />
			<input type="button" class="button-secondary" name="order" value="Order By Date" />
			<input type="button" class="button-secondary" name="remove" value="Remove All" /> &nbsp;&nbsp;
			<span id="fb-progress" style="display: none">
				<img id="fb-progress-indicator" src="../wp-content/plugins/fotobook/images/percentImage.png" alt="0%" class="percentImage" style="background-position: -500px 0pt;"/>
				<span id="fb-progress-indicatorText">0%</span>
			</span>
		</div>
		
		<div id="fb-manage">
			<?php fb_display_manage_list() ?>
		</div>
		<?php endif; ?>
		
		<?php endif; // condition checking if a gallery page is selected	?>
	</div>
</div>