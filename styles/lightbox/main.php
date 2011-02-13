<div class="fotobook-subheader">
  <span class='main'>Albums <?php echo $first_album ?> - <?php echo $last_album ?> out of <?php echo $album_count ?></span>
  <div class='pagination'>
    <?php if($prev_link): ?><a href='<?php echo $prev_link ?>'>Prev</a><?php endif; ?>
    <?php echo $pagination ?>
    <?php if($next_link): ?><a href='<?php echo $next_link ?>'>Next</a><?php endif; ?>
  </div>
</div>

<table id="fotobook-main">
<?php
if(sizeof($albums) > 0):
foreach($albums as $album):
?>
  <tr>
    <th>
      <a href="<?php echo $album['link'] ?>"><img src="<?php echo $album['thumb'] ?>" alt="<?php echo $album['name'] ?>" /></a>
    </th>
    <td>
      <a href='<?php echo  $album['link'] ?>'><?php echo $album['name']; ?></a><br />
      <?php if($album['description'] != ''): echo $album['description'] ?><br /><?php endif; ?>
      <small><?php echo $album['size'] ?> photos</small>
    </td>
  </tr>
<?php 
endforeach; 
endif;
?>
</table>

<div class="fotobook-subheader fotobook-subheader-bottom">
  <span class='main'>Albums <?php echo $first_album ?> - <?php echo $last_album ?> out of <?php echo $album_count ?></span>
  <div class='pagination'>
    <?php if($prev_link): ?><a href='<?php echo $prev_link ?>'>Prev</a><?php endif; ?>
    <?php echo $pagination ?>
    <?php if($next_link): ?><a href='<?php echo $next_link ?>'>Next</a><?php endif; ?>
  </div>
</div>