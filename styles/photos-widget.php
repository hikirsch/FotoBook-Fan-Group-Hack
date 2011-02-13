<div id="fotobook-photos-widget">
  <?php foreach($photos as $photo): ?>
  <div class="thumbnail" style="height: <?php echo $size ?>px; width: <?php echo $size ?>px">
    <a href="<?php echo $photo['link'] ?>">
      <img src="<?php echo $photo['src'] ?>" alt="<?php echo htmlentities($photo['caption'], ENT_QUOTES) ?>" />
    </a>
  </div>
  <?php endforeach; ?>
</div>
<div style="clear: both"></div>

<script type="text/javascript">
window.onload = function() {
  centerSquareThumbs('fotobook-photos-widget', <?php echo $size ?>);  
}
</script>