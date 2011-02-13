<?php 
?>

<a name="photo"></a>
<div class="fotobook-subheader">
  <span class='main'>Photo <?php echo $curr ?> of <?php echo $photo_count ?> | <a href="<?php echo $page_link ?>">Back to Album</a></span>
  <div class='pagination'>
    <?php if($prev_link): ?><a href='<?php echo $prev_link ?>#photo'>Previous</a><?php endif; ?>
    <?php if($next_link): ?><a href='<?php echo $next_link ?>#photo'>Next</a><?php endif; ?>&nbsp;
  </div>
</div>

<div id="fotobook-photo">
  <?php if($next_link): ?><a href="<?php echo $next_link ?>#photo"><?php endif; ?>
  <?php if($width): ?>
  <img src='<?php echo $photo['src_big'] ?>' alt="<?php echo $photo['caption'] ?>" style='max-width: <?php echo $width ?>px; _width: expression(this.width > <?php echo $width ?> ? <?php echo $width ?>: true);' />
  <?php else: ?>
  <img src="<?php echo $photo['src_big'] ?>" alt="<?php echo $photo['caption'] ?>" />
  <?php endif; ?>
  <?php if($next_link): ?></a><?php endif; ?>
</div>

<?php // DISPLAY THE ALBUM INFO  ?>
<table id="fotobook-info">
<?php if($photo['caption']): ?>
  <tr>
    <th>Caption:</th>
    <td><?php echo $photo['caption'] ?></td>
  </tr>
<?php endif; ?>
<?php if($photo['created']): ?>
  <tr>
    <th>Date added:</th>
    <td><?php echo mysql2date('m-d-Y', $photo['created']) ?></td>
  </tr>
<?php endif; ?>
</table>