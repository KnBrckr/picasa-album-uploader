<?php
/*
Plugin Name: Picasa Album Uploader
Plugin URI: http://pumastudios.com/<TBD>
Description: Publish directly from Google Picasa desktop using a button into a Wordpress photo album.
Version: 0.1
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
include_once('dBug.php'); // TODO - remove

// =======================================
// = Define constants used by the plugin =
// =======================================

if ( ! defined( 'PAU_PLUGIN_NAME' ) )
	define( 'PAU_PLUGIN_NAME', 'picasa-album-uploader' );	// Plugin name
if ( ! defined( 'PAU_PLUGIN_DIR') )
	define( 'PAU_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . PAU_PLUGIN_NAME );	// Base directory for Plugin
if ( ! defined( 'PAU_PLUGIN_URL') )
	define ( 'PAU_PLUGIN_URL', WP_PLUGIN_URL . '/' . PAU_PLUGIN_NAME);	// Base URL for plugin directory
if ( ! defined( 'PAU_NONCE_UPLOAD' ) )
	define ( 'PAU_NONCE_UPLOAD', 'picasa-album-uploader-upload-images');
	
define ('PAU_BUTTON', 1);
define ('PAU_MINIBROWSER', 2);
define ('PAU_UPLOAD', 3);
	
// ============================
// = Include needed libraries =
// ============================

// zip.lib.php is copied from phpMyAdmin - great little library for generating zip archives on the fly
if ( ( include_once PAU_PLUGIN_DIR . '/lib/zip.lib.php') == FALSE ) {
	// TODO - Improve error handling
	echo "unable to load zip lib\n";
}

// xmlHandler.class copied from Google's sample handler
if ( ( include_once PAU_PLUGIN_DIR . '/lib/xmlHandler.class')  == FALSE ) {
	// TODO - Improve error handling
	echo "Unable to load xml Handler";
}

// =================================
// = Define the picasa album class =
// =================================

if ( ! class_exists( 'picasa_album_uploader' ) ) {
	class picasa_album_uploader {
		
		// FIXME Create object to define allowed plugin paramters
		
		var $slug = "picasa_album";  // TODO - Make this configurable
		
		var $is_pau = false;  // True if page should be handled by template provided by this plugin
		
		// Constructor function		
		function picasa_album_uploader() {
			
			// Shortcode to generate URL to download Picassa Button
			add_shortcode( 'pau_download_button', array( &$this, 'sc_download_button' ) );
			// Shortcode to display album
			add_shortcode( 'pau_album', array( &$this, 'sc_album' ) );  
			
			// Add plugin XML-RPC methods
			// add_filter( 'xmlrpc_methods', array( &$this, 'attach_new_xmlrpc' ) );
			
			// Add action to check if requested URL matches slug handled by plugin
			add_filter( 'the_posts', array( &$this, 'check_url' ));
		}
		
		/*
			function check_url( $posts )

			Examines requested URL for match to slug handled by plugin.
			
			If URL is handled by plugin, environment setup to catch it in the template redirect			
		*/
		
		function check_url( $posts ) {
			global $wp;
			global $wp_query;
			
			/*
				Request should be handled by this plugin
					- Syntax check request, return if not valid for wp_core processing
			*/
			
			$requested_page = self::parse_request($wp->request);
			if (! $requested_page) {
				return $posts;
			}
			
			/*
				Setup a dummy post class 
				- very little detail needed to deliver the button, just enough that wp-core
				  doesn't treat this as a 404 event.
			*/
			$post = new stdClass();
			$post->ID = -1;										// Fake ID# for the post
			
			// Request should be handled by this plugin
			$this->is_pau = true;
			
			// FIXME save detected request for processing during template redirect
			
			// Add template redirect action to process the page
			add_action('template_redirect', array(&$this, 'template_redirect'));
			
			$wp_query->is_page = true;	// might not be needed; set it like a true page just in case
			$wp_query->is_single = true;
			$wp_query->is_home = false;
			$wp_query->is_archive = false;

			// Clear any 404 error
			unset($wp_query->query["error"]);
			$wp_query->query_vars["error"]="";
			$wp_query->is_404 = false;

			return array($post);
		}
		
		/*
			parse_request($wp_request)
			
			Parse incoming request and confirm it is valid
		*/
		function parse_request( $wp_request ){
			$tokens = split( '/', $wp_request );

			// TODO Enforce only two parameters

			if ( $this->slug != $tokens[0] ) {
				return false;
			}
			
			/*
				Valid values for 2nd parameter:
					wordpress_uploader.pbz
					mini_browser
					upload
			*/
			
			$retval = false;
			
			if ( $tokens[1] == 'wordpress_uploader.pbz' ) {
				$this->pau_serve = PAU_BUTTON;
				$retval = true;
			} else if ( $tokens[1] == 'minibrowser' ) {
				$this->pau_serve = PAU_MINIBROWSER;
				$retval = true;
			} else if ( $tokens[1] == 'upload' ) {
				$this->pau_serve = PAU_UPLOAD;
				$retval = true;
			}
			
			return $retval;
		}
		
		// =================================================================
		// = Template Redirection - used to grab request for picasa plugin =
		// =================================================================
		
		function template_redirect( $requested_url=null, $do_redirect=true ) {

			if (! $this->is_pau ) {
				return;
			}
			
			switch ( $this->pau_serve ) {
				case PAU_BUTTON:
					self::send_picasa_button();
					break;
				case PAU_MINIBROWSER:
					self::minibrowser();
					break;
				case PAU_UPLOAD:
					self::upload_images();
					break;
			}
		}
		
		/**
		 *	function send_picasa_buttion()
		 *
		 *	Generate the Picasa PZB file and emit for web browser to download
		 *
		 * 	Assumes that no other data, including headers, has been sent to browser.
		 */

		function send_picasa_button( ) {
			$blogname = get_bloginfo( 'name' );
			$guid = self::guid();
			$upload_url = get_bloginfo( 'wpurl' ) . '/' . $this->slug . '/minibrowser';
			
			/*
				XML to describe the Picasa plugin button
					See http://code.google.com/apis/picasa/docs/button_api.html
			*/
			$pbf = <<<EOF
<?xml  version="1.0" encoding="utf-8" ?>
<buttons format="1" version="0.1">
   <button id="$guid" type="dynamic">
   	<icon name="$guid/layername" src="pbz"/>
   	<label>Wordpress</label>
		<label_en>Wordpress</label_en>
		<label_zh-tw>上传</label_zh-tw>
		<label_zh-cn>上載</label_zh-cn>
		<label_cs>Odeslat</label_cs>
		<label_nl>Uploaden</label_nl>
		<label_en-gb>Wordpress</label_en-gb>
		<label_fr>Transférer</label_fr>
		<label_de>Hochladen</label_de>
		<label_it>Carica</label_it>
		<label_ja>アップロード</label_ja>
		<label_ko>업로드</label_ko>
		<label_pt-br>Fazer  upload</label_pt-br>
		<label_ru>Загрузка</label_ru>
		<label_es>Cargar</label_es>
		<label_th>อัปโหลด</label_th>
		<tooltip>Upload to "$blogname"</tooltip>
		<action verb="hybrid">
		   <param name="url" value="$upload_url"/>
		</action>
	</button>
</buttons>
EOF;
			
			// Create Zip stream and add the XML data to the zip
			$zip = new zipfile();
			$zip->addFile( $pbf, $guid . '.pbf' );
			
			// FIXME Icon not showing up
			// TODO Allow icon to be replaced by theme
			// Add PSD icon to zip
			$psd_filename =  PAU_PLUGIN_DIR . '/images/wordpress-logo-blue.psd'; // button icon
			$fsize = @filesize( $psd_filename );
			// TODO - Handle errors reading PSD file
			$zip->addFile( file_get_contents( $psd_filename ), $guid . '.psd' );

			// Emit zip file to browser
			$zipcontents = $zip->file();
			header( "Content-type: application/octet-stream\n" );
			header( "Content-Disposition: attachment; filename=\"wordpress_uploader.pbz\"\n" );
			header( 'Content-length: ' . strlen($zipcontents) . "\n\n" );

			echo $zipcontents;
			exit;
		}
		
		/**
		 * function minibrowser()
		 *
		 * Generate post content for Picasa minibrowser image uploading
		 */
		//FIXME Add plugin based classes to all elements for end-user formatting
		function minibrowser() {
			// Open the plugin content div for theme formatting
			$content = '<div class="picasa-album-uploader">';
			
			// Make sure user is logged in to proceed
			if (false == is_user_logged_in()) {
				// Redirect back to this page after login complete
				$content .= '<p>Please <a href="'.wp_login_url( get_bloginfo('wpurl') . '/' . $this->slug . '/minibrowser' ).'" title="Login">login</a> to continue.</p>';
			} else {
				// As long as current user is allowed to upload files, check for requested files
				if ( current_user_can('upload_files') ) {
					if ($_POST['rss']) {
						$content = self::build_upload_form();					
					} else {
					 	$content = '<p>Sorry, but no pictures were received.</p>';
					}					
				} else {
					// User is not allowed to upload files
					$content = '<p>Sorry, you do not have permission to upload files.</p>';
				}
			}
			
			$content .= '</div>';  // Close the Div for this post text
			
			// Generate the post data structure
			self::gen_post($content);
			
			// If Theme has a defined the plugin template, use it, otherwise use elements from the plugin
			if ($theme_template = get_query_template('page-picasa_album_uploader')) {
				include($theme_template);
			} else {
				include(PAU_PLUGIN_DIR.'/templates/page-picasa_album_uploader.php');
			}

			exit; // Finished displaying the minibrowser page
		}
		
		/**
		 * function upload_images()
		 *
		 * Processes POST request from Picasa to upload images and save in Wordpress
		 */
		function upload_images() {
			require_once( ABSPATH . 'wp-admin/includes/admin.php' ); // Load functions to handle uploads
			
			// Confirm the nonce field to allow operation to continue
			// FIXME On Nonce failure generate better failure screen.
			check_admin_referer(PAU_NONCE_UPLOAD, PAU_NONCE_UPLOAD);
			
			// FIXME Errors in processing here don't get displayed by Picasa
			
			// Open the plugin content div for theme formatting
			$content = '<div class="picasa-album-uploader">';
			
			// User must be able to upload files to proceed
			if (! current_user_can('upload_files'))
			  // FIXME - Trap to a 404?
				$content .= '<p>Sorry, you do not have permission to upload files.</p>';
			else {
				if ( $_FILES ) {
					// Don't need to test that this is the wp_upload_form in wp_handle_upload()
					$overrides = array( 'test_form' => false );
					
					foreach ( $_FILES as $key => $file ) {
						if ( empty( $file ) ) {
							continue; // Skip if value empty
						}
						
						$status = wp_handle_upload( $file, $overrides );
						if (isset($status['error'])) {
							continue; // Error on this file, go to next one.
						}
						$url = $status['url'];
            $type = $status['type'];
            $file = $status['file'];
            $filename = basename($file);
            $content = '';

            if ( $image_meta = @wp_read_image_metadata($file) ) {
                if ( trim($image_meta['title']) )
                    $title = $image_meta['title'];
                if ( trim($image_meta['caption']) )
                    $content = $image_meta['caption'];
            }
            $object = array_merge( array(
            'post_title' => $filename,
            'post_content' => $content,
            'post_parent' => 0,
            'post_mime_type' => $type,
            'guid' => $url), array());

            $id = wp_insert_attachment($object, $file,0);
            if ( !is_wp_error($id) ) {
                wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
                do_action('wp_create_file_in_uploads', $file, $id); // for replication
            }						
					} // end foreach $file
				} else {
					$content .="<p>Sorry, no files were uploaded by Picasa.</p>";
				}
			}			
			$content .= '</div>'; // Close the div for this post entry
			
			// FIXME Report any errors
			
			// Picasa will close the minibrowser - Any HTML output will be ignored.
			exit;
		}
		
		// =======================
		// = Shortcode Functions =
		// =======================

		function sc_album( $atts, $content = null ) {
			return 'This is the album Shortcode Replacement';
		}
		
		function sc_download_button( $atts, $content = null ) {
			return '<a href="picasa://importbutton/?url=' . get_bloginfo('wpurl') . '/' . $this->slug . '/wordpress_uploader.pbz" title="Download Picasa Button">Download Picasa Button</a>';
		}
		
		// =====================
		// = Private Functions =
		// =====================		
		
		/*
			function guid ()
			
			Generate a standard format guid
			
			Input:  None
			Returns:  UUID string in form: FIXME
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
		
		/*
			function build_upload_form()
			
			Generate the form used in the Picasa minibrowser to confirm the upload
			
			Input: None
			Output: HTML form returned as string
		*/
		private function build_upload_form() {
			// URL used to POST form
			$targetUrl = 'upload'; // Must be simple page name for Picasa to process the input URLs correctly.

			// Get Posted photos
			$content = "<form method='post' action='$targetUrl'>\n";

			// Add nonce field to the form if nonce is supported
			if ( function_exists( 'wp_nonce_field' ) ) {
				// Set referrer field, do not echo hidden nonce field
				$content .= wp_nonce_field(PAU_NONCE_UPLOAD, PAU_NONCE_UPLOAD, true, false);
				// Don't echo the referer field
				$content .= wp_referer_field(false);
			}

			// Start div used to display images
			$content .= "<div><p>Selected images</p>\n";

			// Parse the RSS feed from Picasa to get the images to be uploaded
			$xh = new xmlHandler();
			$nodeNames = array("PHOTO:THUMBNAIL", "PHOTO:IMGSRC", "TITLE");
			$xh->setElementNames($nodeNames);
			$xh->setStartTag("ITEM");
			$xh->setVarsDefault();
			$xh->setXmlParser();
			$xh->setXmlData(stripslashes($_POST['rss']));
			$pData = $xh->xmlParse();
			$br = 0;

			// For each image, display the image and setup hidden form field for upload processing.
			foreach($pData as $e) {
				$content .= "<img src='".attribute_escape( $e['photo:thumbnail'] )."?size=-96' title='".attribute_escape( $e['title'] )."'>";
				$large = attribute_escape( $e['photo:imgsrc'] ) ."?size=1024";
         $content .= "<input type=hidden name='$large'>";
			}

			$content .= <<<FORM_FIN
</div>
<div class='h'>Select your upload image size
<INPUT type="radio" name="size" value='640'">640
<INPUT type="radio" name="size" value='1024'" CHECKED>1024
<INPUT type="radio" name="size" value='1600'">1600
<INPUT type="radio" name="size" value='0')">Original
</div>
<div class='h'>
<input type="submit" value="Upload">&nbsp;
</div>
FORM_FIN;

			return $content;
		}
		
		
		/*
			Fill in POST data structure
			
			Input: Content
			Output:  Updates global $post
		*/
		private function gen_post($content) {
			global $post;
			
			// Create POST Data Structure
			$formattedNow = date('Y-m-d H:i:s');
			$post->post_author = 1;
			$post->post_date = $formattedNow;
			$post->post_date_gmt = $formattedNow;
			$post->post_content = $content;
			$post->post_title = 'Picasa Uploader';
			$post->post_category = 0;
			$post->post_excerpt = '';
			$post->post_status = 'publish';
			$post->comment_status = 'closed';
			$post->ping_status = 'closed';
			$post->post_password = '';
			$post->post_name = $post->post_title;
			$post->to_ping = '';
			$post->pinged = '';
			$post->post_content_filtered = '';
			$post->post_parent = 0;
			$post->guid = $url;
			$post->menu_order = 0;
			$post->post_type = 'page';
			$post->post_mime_type = '';
			$post->comment_count = 0;
		}
		
	}
} // End Class picasa_album_uploader

// =========================
// = Plugin initialization =
// =========================

global $pau;
if ( class_exists( 'picasa_album_uploader' ) ) {
	$pau = new picasa_album_uploader();
}
?>