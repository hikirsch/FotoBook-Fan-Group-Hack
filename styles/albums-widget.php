<ul>
<?php foreach($albums as $album): ?>
  <li><a href="<?php echo get_permalink($album['page_id']) ?>"><?php echo $album['name'] ?></a></li>
<?php endforeach; ?>
</ul>
