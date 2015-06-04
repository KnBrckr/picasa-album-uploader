<?php
/**
 * @package Picasa Album Uploader
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011,2015 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of Picasa Album Uploader, a plugin for Wordpress.
 *
 * Picasa Album Uploader is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Picasa Album Uploader is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Picasa Album Uploader.  If not, see <http://www.gnu.org/licenses/>.
 **/

  // FIXME Trim down header and footer to avoid conflicts and errors with loaded themes

  // Load header-picasa_album_uploader.php if available
	get_header( 'picasa_album_uploader' );
?>
<div id="content" class="narrowcolumn" role="main">
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
</div> <!-- End Content -->
<?php

	// Load footer-picasa_album_uploader.php if available
	get_footer( 'picasa_album_uploader' );
?>
