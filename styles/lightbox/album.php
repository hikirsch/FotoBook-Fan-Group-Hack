<?php // DISPLAY PAGINATION AND LINK BACK TO MAIN PAGE ?>

<script type="text/javascript">
  // <![CDATA[
    // Automagically load Lightbox on Page Load - by Bramus! (http://www.bram.us/)
    // Code modded from http://www.huddletogether.com/forum/comments.php?DiscussionID=1269&page=1#Item_0
    function autoFireLightbox() {
      //Check if location.hash matches a lightbox-anchor. If so, trigger popup of image.
      setTimeout(function() {
        if(document.location.hash && $(document.location.hash.substr(1)).rel.indexOf('lightbox')!=-1) {
          myLightbox.start($(document.location.hash.substr(1)));
        }},
        250
      );
    }
    Event.observe(window, 'load', autoFireLightbox, false);
  // ]]>
</script>


<div class="fotobook-subheader">
  <span class='main'>Photos <?php echo ($first_photo)." - ".($last_photo) ?> out of <?php echo $photo_count ?> | <a href='<?php echo $albums_page_link ?>'>Back to Albums</a></span>
  <div class='pagination'>
    <?php if($prev_link): ?><a href='<?php echo $prev_link ?>'>Prev</a><?php endif; ?>
    <?php echo $pagination ?>
    <?php if($next_link): ?><a href='<?php echo $next_link ?>'>Next</a><?php endif; ?>
  </div>
</div>

<?php // BUILD THE PHOTO TABLE ?>

<table id="fotobook-album">
  <tr>
    <?php foreach($photos as $key=>$photo): ?>
    <td>
      <a href='<?php echo $photo['src_big'] ?>' rel='lightbox[fotobook]' title="<?php echo $photo['caption'] ?>" id="photo<?php echo $photo['ordinal'] ?>" target='_blank'>
        <img src='<?php echo $photo['src'] ?>' alt="<?php echo $photo['caption'] ?>" style='max-width: <?php echo $thumb_size ?>px; max-height: <?php echo $thumb_size ?>px; _width: expression(this.width > <?php echo $thumb_size ?> ? <?php echo $thumb_size ?> : true); _height: expression(this.height > <?php echo $thumb_size ?> ? <?php echo $thumb_size ?>: true);' />
      </a>
    </td>
    <?php
    if($key % $number_cols == 0) echo '</tr><tr>';
    endforeach;
    for($i = 0; $i < ($number_cols - (count($photos) % $number_cols)); $i++) {
      echo "<td>&nbsp;</td>";
    }
    ?>
  </tr>      
</table>

<div class="fotobook-subheader fotobook-subheader-bottom">
  <span class='main'>Photos <?php echo ($first_photo)." - ".($last_photo) ?> out of <?php echo $photo_count ?> | <a href='<?php echo $albums_page_link ?>'>Back to Albums</a></span>
  <div class='pagination'>
    <?php if($prev_link): ?><a href='<?php echo $prev_link ?>'>Prev</a><?php endif; ?>
    <?php echo $pagination ?>
    <?php if($next_link): ?><a href='<?php echo $next_link ?>'>Next</a><?php endif; ?>
  </div>
</div>

<?php // DISPLAY THE ALBUM INFO  ?>
<table id="fotobook-info">
<?php if($description): ?>
  <tr>
    <th>Description:</th>
    <td><?php echo $description ?></td>
  </tr>
<?php endif; ?>
<?php if($location): ?>
  <tr>
    <th>Location:</th>
    <td><?php echo $location ?></td>
  </tr>
<?php endif; ?>
</table>