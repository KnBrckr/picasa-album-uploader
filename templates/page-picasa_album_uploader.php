<?php
  // Load header-picasa_album_uploader.php if available
	get_header( 'picasa_album_uploader' );
?>

<div id="content">

	<?php if (have_posts()) : ?>
		
		<?php while (have_posts()) : the_post(); ?>
			<div class="post" id="post-<?php the_ID(); ?>">
				<div class="entry">
					<?php the_content(); ?>
				</div>
			</div>
		<?php endwhile; ?>

	<?php else : ?>

		<h2 class="center">Not Found</h2>
		<p class="center">Sorry, but you are looking for something that isn't here.</p>

	<?php endif; ?>

	</div>
</div>

<?php
	// Load footer-picasa_album_uploader.php if available
	get_footer( 'picasa_album_uploader' );
?>
