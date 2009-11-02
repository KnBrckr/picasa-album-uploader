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

// =======================================
// = Define constants used by the plugin =
// =======================================

if ( ! defined( 'PLUGIN_NAME' ) )
	define( 'PAU_PLUGIN_NAME', 'picasa-album-uploader' );	// Plugin name
IF ( ! defined ( 'PAU_PLUGIN_DIR') )
	define( 'PAU_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . PAU_PLUGIN_NAME );	// Base directory for Plugin

// ============================
// = Include needed libraries =
// ============================

/* zip.lib.php is copied from phpMyAdmin - great little library for generating zip archives on the fly */
if ( ( include_once 'lib/zip.lib.php') == FALSE ) {
	// TODO
	echo "unable to load zip lib\n";
}

// =================================
// = Define the picasa album class =
// =================================

if ( ! class_exists( 'picasa_album_uploader' ) ) {
	class picasa_album_uploader {
		
		// Constructor function		
		function picasa_album_uploader() {
			// Shortcode to generate URL to download Picassa Button
			add_shortcode( 'pau_download_button', array( &$this, 'sc_download_button' ) );
			// Shortcode to display album
			add_shortcode( 'pau_album', array( &$this, 'sc_album' ) );  
			// Add plugin XML-RPC methods
			add_filter( 'xmlrpc_methods', array( &$this, 'attach_new_xmlrpc' ) );
		}
		
		// ====================
		// = XML RPC Handling =
		// ====================
		
		// Add the XML-RPC methods needed for the plugin
		function attach_new_xmlrpc( $methods ) {
			$methods['pau.picasa_button'] = array( &$this, 'send_picasa_button' );
			return $methods;
		}
		
		// Generate the Picasa PZB file and return to the caller
		function send_picasa_button( $args ) {
			$blogname = get_bloginfo( 'name');
			$guid = self::guid();
			
			/*
				XML to describe the Picasa plugin button
					See http://code.google.com/apis/picasa/docs/button_api.html
			*/
			$pbf = <<<EOF
<?xml  version="1.0" encoding="utf-8" ?>
<buttons format="1" version="0.1">
   <button id="$guid" type="dynamic">
   	<icon name="$guid/layername" src="pbz"/>
   	<label>WP Upload</label>
		<label_en>WP Upload</label_en>
		<label_zh-tw>上传</label_zh-tw>
		<label_zh-cn>上載</label_zh-cn>
		<label_cs>Odeslat</label_cs>
		<label_nl>Uploaden</label_nl>
		<label_en-gb>WP Upload</label_en-gb>
		<label_fr>Transférer</label_fr>
		<label_de>Hochladen</label_de>
		<label_it>Carica</label_it>
		<label_ja>アップロード</label_ja>
		<label_ko>업로드</label_ko>
		<label_pt-br>Fazer  upload</label_pt-br>
		<label_ru>Загрузка</label_ru>
		<label_es>Cargar</label_es>
		<label_th>อัปโหลด</label_th>
		<tooltip>Upload images to the "$blogname" Wordpress Gallery.</tooltip>
		<action verb="hybrid">
		   <param name="url" value="hybrid_uploader_url"/>
		</action>
	</button>
</buttons>
EOF;

			/*
				Create Zip stream
			*/
			$zip = new zipfile();
			$zip->addFile( $pbf, $guid . '.pbf' ); 						// Add the XML data to zip
			
			/* Add PSD icon to zip */
			$psd_filename =  PAU_PLUGIN_DIR . '/images/wordpress-logo-blue.psd'; // button icon
			$fsize = @filesize( $psd_filename );
			// TODO - Handle errors reading PSD file
			$zip->addFile( file_get_contents( $psd_filename ), $guid . '.psd' );

			/*
				Send zip file to browser
			*/
			$zipcontents = $zip->file();
			header( "Content-type: application/octet-stream\n" );
			header( "Content-Disposition: attachment; filename=\"wordpress_uploader.pbz\"\n" );
			header( 'Content-length: ' . strlen($zipcontents) . "\n\n" );

			echo $zipcontents;
			exit;
		}
		
		// =======================
		// = Shortcode Functions =
		// =======================

		function sc_album( $atts, $content = null ) {
			return 'This is the album Shortcode Replacement';
		}
		
		function sc_download_button( $atts, $content = null ) {
			return 'BUTTON URL';
		}
		
		// =====================
		// = Private Functions =
		// =====================		
		
		/*
			Generate a standard format guid
		*/
		private function guid() {
			if ( function_exists( 'com_create_guid' ) ) {
				return com_create_guid();
			} else {
				mt_srand( (double)microtime()*10000 ) ;//optional for php 4.2.0 and up.
				$charid = strtoupper( md5( uniqid( rand(), true ) ) );
				$hyphen = chr( 45 );	// "-"
				$uuid = chr( 123 )		// "{"
					.substr($charid, 0, 8).$hyphen
					.substr($charid, 8, 4).$hyphen
					.substr($charid,12, 4).$hyphen
					.substr($charid,16, 4).$hyphen
					.substr($charid,20,12)
					.chr(125);	// "}"
				return $uuid;
			}
		}
		
	}
} // End Class picasa_album_uploader

if ( class_exists( 'picasa_album_uploader' ) ) {
	$pau = new picasa_album_uploader();
}
?>