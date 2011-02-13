jQuery(document).ready(function($) {
	$('a[rel="fotobook"]').colorbox();

	var hash = window.location.hash;
	if (/^#photo[0-9]+$/.test(hash)) {
		$('a' + hash).click();
	}
});
