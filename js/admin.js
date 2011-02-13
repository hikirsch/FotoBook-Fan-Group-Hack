jQuery(document).ready(function() {
	fotobook.init();
});

var fotobook = {
	interval: undefined,
	init: function() {
		jQuery('#grant-permissions').click(function() {
			jQuery(this).css('opacity', 0.5);
			window.onbeforeunload = function(e) {
				return ('If this page is refreshed or changed between granting and applying permissions the activation process will not work.');
			};
		});
		jQuery('#apply-permissions').submit(function() {
			jQuery(this).find('input[type="submit"]').css('opacity', 0.5);
			window.onbeforeunload = undefined;
		});

		fotobook.initManageList();

		jQuery('#fb-panel input[type="button"][name="get"]').click(fotobook.getAlbums);
		jQuery('#fb-panel input[type="button"][name="order"]').click(fotobook.resetOrder);
		jQuery('#fb-panel input[type="button"][name="remove"]').click(function() {
			if (confirm('Removing the albums will also remove the pages that contain them and their comments.  Are you sure you want to proceed?')) {
				fotobook.removeAll();
			}
			return false;
		});

		// initialize the stylesheet switcher
		jQuery('#fb-stylesheets select').change(function() {
			jQuery('#fb-stylesheets div').hide();
			jQuery('#' + jQuery(this).val() + '-stylesheet').show();
		});

		// toggle the debug info
		jQuery('#fb-debug').click(function() {
			jQuery('#fb-debug-info').toggle();
			return false;
		});
	},
	initManageList: function() {
		var $list = jQuery('#fb-manage-list');
		$list.sortable({
			update: function() {
				var ids = $list.sortable('serialize', {
					key: 'order[]'
				});
				jQuery.post('admin-ajax.php', "action=fotobook&" + ids);
			},
			cursor: 'handle'
		});
		$list.find('.toggle-hidden').click(fotobook.toggleHidden);
	},
	albumList: function(message) {
		var params = {
			action: 'fotobook',
			albums_list: 'true'
		};
		if(message != '') {
			jQuery.extend(params, {message: message});
		}
		jQuery('#fb-manage').load('admin-ajax.php', params, function() {
			var message = jQuery('#fb-message');
			if (message.length > 0) {
				message.slideDown();
				setTimeout(function() {message.slideUp();}, 5000);
			}
			fotobook.initManageList();
		});
	},
	resetOrder: function() {
		jQuery.post('admin-ajax.php', {
			action: 'fotobook',
			reset_order: 'true'
		}, 
		function(response) {
			fotobook.albumList(response);
		});
		return false;
	},
	removeAll: function() {
		jQuery.post('admin-ajax.php', "action=fotobook&remove_all=true", function(response) {
			fotobook.albumList(response);
		});
		return false;
	},
	toggleHidden: function() {
		var $link = jQuery(this);
		var $li   = jQuery(this).parents('li');

		var aid = $li.attr('id').split('_');
		aid.shift();
		aid = aid.join('_');

		jQuery.post('admin-ajax.php', {action: 'fotobook', hide: aid});
		
		if($link.text() == 'Hide') {
			$link.text('Show');
			$li.addClass('disabled');
		}
		else {
			$link.text('Hide');
			$li.removeClass('disabled');
		}
		return false;
	},
	getAlbums: function() {
		fotobook.setProgress(0);
		jQuery('#fb-progress').fadeIn();
		jQuery('#fb-manage-list').addClass('disabled');
		fotobook.interval = setInterval(fotobook.updateProgressBar, 2000);
		jQuery.post('admin-ajax.php', "action=fotobook&update=true", function(response) {
			clearInterval(fotobook.interval);
			fotobook.setProgress(100);
			fotobook.albumList(response);
			jQuery('#fb-progress').fadeOut();
		});
		return false;
	},
	updateProgressBar: function() {
		jQuery.post('admin-ajax.php', "action=fotobook&progress=true", function(response) {
			fotobook.setProgress(response);
		});
	},
	setProgress: function(percentage) {
		var initial     = -119;
		var imageWidth  = 240;
		var eachPercent = (imageWidth / 2) / 100;
		var percentageWidth = eachPercent * percentage;
		var newProgress = eval(initial)+eval(percentageWidth)+'px';
		jQuery('#fb-progress-indicator').css('backgroundPosition', newProgress+' 0');
		jQuery('#fb-progress-indicatorText').text(percentage + '%');
	}
};