<?php
/*
Plugin Name: Picasa Album Uploader
Plugin URI: http://pumastudios.com/<TBD>
Description: Publish directly from Google Picasa desktop using a button into a Wordpress photo album.
Version: 0.0
Author: Kenneth J. Brucker
Author URI: http://pumastudios.com/<TBD>

Copyright: 2009 Kenneth J. Brucker (email: ken@pumastudios.com)

This file is part of Picasa Album Uploader, a plugin for Wordpress.

Picasa Album Uploader is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Picasa Album Uploader is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Picasa Album Uploader.  If not, see <http://www.gnu.org/licenses/>.

*/

if (!class_exists("PicasaAlbumUloader")) {
	class PicasaAlbumUploader {
		function PicasaAlbumUploader() { // Constructor
		}
	}
} // End Class PicasaAlbumUploader

if (class_exists("PicasaAlbumUploader")) {
	$pau = new PicacaAlbumUploader();
}

// Setup Actions & Filters
if (isset($pau)) {
}

?>