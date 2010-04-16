<?php
/*
Plugin Name: Picasa Album Uploader
Plugin URI: http://pumastudios.com/software/picasa-album-uploader-wordpress-plugin
Description: Easily upload media from Google Picasa Desktop into WordPress.  Navigate to <a href="options-media.php">Settings &rarr; Media</a> to configure.
Version: 0.4
Author: Kenneth J. Brucker
Author URI: http://pumastudios.com/blog/

Copyright: 2010 Kenneth J. Brucker (email: ken@pumastudios.com)

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

TODO Document how to handle failures to install in Picasa.
TODO Add link to directly download the .pbz file for manual install in Picasa.
TODO Optionally Create a New Post to attach the uploaded images as a WP gallery using [gallery] shortcode.
TODO Internationalize Plugin

*/

// =======================================
// = Define constants used by the plugin =
// =======================================

if ( ! defined( 'PAU_PLUGIN_NAME' ) ) {
	// If Plugin Name not defined, then must need to define all constants used
	
	define( 'PAU_PLUGIN_NAME', 'picasa-album-uploader' );	// Plugin name
	define( 'PAU_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . PAU_PLUGIN_NAME );	// Base directory for Plugin
	define ( 'PAU_PLUGIN_URL', WP_PLUGIN_URL . '/' . PAU_PLUGIN_NAME);	// Base URL for plugin directory
	
	// Name strings used in Nonce hanldling
	define ( 'PAU_NONCE_UPLOAD', 'picasa-album-uploader-upload-images');
	define ( 'PAU_NONCE_GEN_BUTTON', 'picasa-album-uploader-gen-button');

	// plugin function requested based on URL request
	define ('PAU_BUTTON', 1);
	define ('PAU_MINIBROWSER', 2);
	define ('PAU_UPLOAD', 3);
	define ('PAU_RESULT', 4);
	
	// result codes on upload completion or failure
	define ('PAU_RESULT_SUCCESS', 'success');
	define ('PAU_RESULT_NO_FILES', 'no-files');
	define ('PAU_RESULT_NO_PERMISSION', 'no-permission');
}	
	
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
	echo "Unable to load xml Handler\n";
}

// Include admin portion of plugin
if ( ( include_once PAU_PLUGIN_DIR . '/admin/options.php' ) == FALSE ) {
	// TODO - Improve error handling
	echo "Unable to load admin/options\n";
}

// =================================
// = Define the picasa album class =
// =================================

if ( ! class_exists( 'picasa_album_uploader' ) ) {
	class picasa_album_uploader {
		/**
		 * Option settings used by plugin
		 *
		 * @var string
		 * @access private
		 **/
		var $pau_options;
		
		/**
		 * Mapping from result codes to message strings
		 *
		 * @var array
		 * @access private
		 **/
		var $result_strings = array (
			PAU_RESULT_SUCCESS => "Files uploaded successfully.",
			PAU_RESULT_NO_FILES => "Error:  No files provided for upload",
			PAU_RESULT_NO_PERMISSION => "Sorry, You do not have permission to upload files to this Blog."
		);
		
		/**
		 * Constructor function for picasa_album_uploader class.
		 *
		 * Adds the needed shortcodes and filters to processing stream
		 */
		function picasa_album_uploader() {
			$this->pau_options = new picasa_album_uploader_options();
			
			$this->using_permalinks = ( get_option('permalink_structure') != '' ) ? true : false ;
			
			// Shortcode to generate URL to download Picassa Button
			add_shortcode( 'picasa_album_uploader_button', array( &$this, 'sc_download_button' ) );
						
			// Add action to check if requested URL matches slug handled by plugin
			add_filter( 'the_posts', array( &$this, 'check_url' ));
			
			// Add CSS to HTML header
			add_action('wp_head', array(&$this, 'add_css'));

			// Add section for reporting configuration errors
			if (is_admin () ) {
				add_action('admin_notices', array( &$this, 'pau_admin_notice'));
			}
		}
		
		/**
		 * Turn the download button shortcode into HTML link
		 *
		 * @return string URL to download Picasa button
		 */
		function sc_download_button( $atts, $content = null ) {
			// Build the URL to the button
			$button_path = $this->pau_options->button_file_path;
			$button_url = $this->pau_options->button_file_url;
			
			if ( is_readable($button_path) ) {
				$text = '<a href="picasa://importbutton/?url=' . $button_url . '" title="Download Picasa Button and Install in Picasa Desktop">Install Image Upload Button in Picasa Desktop</a>';				
			} else {
				// Button file not readable on the server
				$text = "Picasa Album Uploader Configuration Required.";
			}
						
			return $text;
		}	
		
		/**
		 * Called via 'the_posts' filter to examine requested URL for match to slug handled by plugin.
		 *
		 * If URL is handled by plugin, environment setup to catch it in the template redirect and
		 * a new Array of Posts containing a single element is created for the plugin processed post.
		 *
		 * @return array Array of Posts
		 */
		function check_url( $posts ) {
			global $wp;
			global $wp_query;
			
			// Determine if request should be handled by this plugin
			$query = $this->using_permalinks ? $wp->request : $wp->query_vars['page_id'];
			$requested_page = self::parse_request($query);
			if (! $requested_page) {
				return $posts;
			}
			
			//	Request is for this plugin.  Setup a dummy Post.			
			$post = self::gen_post();
			
			// Set field for themes to use to detect if displayed page is for the plugin
			$wp_query-> is_picasa_album_slug = true;
			
			$wp_query->is_page = false;	// Set to false so WordPress.com Stats Plugin doesn't choke
			$wp_query->is_single = false; // Set to false so WordPress.com Stats Plugin doesn't choke
			$wp_query->is_home = false;
			$wp_query->is_archive = false;

			// Clear any 404 error
			unset($wp_query->query["error"]);
			$wp_query->query_vars["error"]="";
			$wp_query->is_404 = false;

			// If this is a result page it will be handled by default browser - template redirect is not needed
			if ( PAU_RESULT == $this->pau_serve ) {
				$result = $_REQUEST['result'];
				$post->post_content = $this->result_strings[$result];
			} else {
				// Add template redirect action to process the page
				add_action('template_redirect', array(&$this, 'template_redirect'));				
			}
			
			return array($post);
		}

		/**
		 * Perform template redirect processing for requests handled by the plugin
		 *
		 * This function will not return under normal conditions.  Each case
		 * handled by the plugin via template redirect results in a complete page or action with no
		 * further action needed by WordPress core.
		 **/
		function template_redirect( $requested_url=null, $do_redirect=true ) {			
			switch ( $this->pau_serve ) {
				case PAU_BUTTON:
					self::send_picasa_button();
					// Should not get here
					exit;
				case PAU_MINIBROWSER:
					self::minibrowser();
					// Should not get here
					exit;
				case PAU_UPLOAD:
					self::upload_images();
					// Should not get here
					exit;
			}
		}
		
		/**
		 * emit HTML needed to include plugin's CSS file
		 **/
		function add_css()
		{
			echo '<link rel="stylesheet" type="text/css" href="' . PAU_PLUGIN_URL . '/picasa-album-uploader.css" />';
		}
		
		/**
		 * Display Notice messages at head of admin screen
		 *
		 * @return void
		 **/
		function pau_admin_notice()
		{
			if ( ! $this->pau_options->error )
				return;
				
			echo "<div class='error'><p><a href='options-media.php'>".PAU_PLUGIN_NAME."</a> needs attention: please review the configuration.</p></div>";
		}

		/**
		 * Parse incoming request and test if it should be handled by this plugin.
		 *
		 * @return boolean True if request is to be handled by the plugin
		 * @access private
		 */
		private function parse_request( $wp_request ){
			$tokens = split( '/', $wp_request );

			if ( $this->pau_options->slug != $tokens[0] ) {
				return false; // Request is not for this plugin
			}
			
			// Valid values for 2nd parameter:
			//	picasa_album_uploader.pbz
			//	mini_browser
			//	upload
			switch ( $tokens[1] ) {
				case 'picasa_album_uploader.pbz':
					$this->pau_serve = PAU_BUTTON;
					break;
				
				case 'minibrowser':
					$this->pau_serve = PAU_MINIBROWSER;
					break;
				
				case 'upload':
					$this->pau_serve = PAU_UPLOAD;
					break;
					
				case 'result':
					$this->pau_serve = PAU_RESULT;
					break;
				
				default:
					return false; // slug matched, but 2nd token did not
			}
			
			return true; // Have a valid request to be handled by this plugin
		}		
		
		/**
		 * Generate post content for Picasa minibrowser image uploading.
		 * 
		 * This function does not return.
		 *
		 * @access private
		 */
		private function minibrowser() {
			global $post; // To setup the Post content
			
			// Open the plugin content div for theme formatting
			$content = '<div class="picasa-album-uploader">';
			
			// Make sure user is logged in to proceed
			if (false == is_user_logged_in()) {
				
				// Redirect user to the login page - come back here after login complete
				if (wp_redirect(wp_login_url( self::build_url('minibrowser') ))) {
					// Requested browser to redirect - done here.
					exit;
				}
				
				// wp_redirect failed for some reason - setup page text with redirect back to this location
				$content .= '<p>Please <a href="'.wp_login_url( self::build_url('minibrowser') )
						. '" title="Login">login</a> to continue.</p>';
			} else {
				// As long as current user is allowed to upload files, check for requested files
				if ( current_user_can('upload_files') ) {
					if ($_POST['rss']) {
						$content = self::build_upload_form();					
					} else {
					 	$content .= '<p class="error">Sorry, but no pictures were received.</p>';
					}					
				} else {
					// User is not allowed to upload files
					$content .= '<p class="error">Sorry, you do not have permission to upload files.</p>';
				}
			}
			
			$content .= '</div>';  // Close the Div for this post text
			
			// Setup post content
			$post->post_content = $content;
			
			// If Theme has a defined the plugin template, use it, otherwise use template from the plugin
			if ($theme_template = get_query_template('page-picasa_album_uploader')) {
				include($theme_template);
			} else {
				include(PAU_PLUGIN_DIR.'/templates/page-picasa_album_uploader.php');
			}

			exit; // Finished displaying the minibrowser page - No more WP processing should be performed
		}
		
		/**
		 * Processes POST request from Picasa to upload images and save in Wordpress.
		 *
		 * Picasa will close the minibrowser - Any HTML output will be ignored.  This function
		 * does not return.
		 *
		 * @access private
		 */
		private function upload_images() {
			require_once( ABSPATH . 'wp-admin/includes/admin.php' ); // Load functions to handle uploads

			// Confirm the nonce field to allow operation to continue
			// TODO On Nonce failure generate better failure screen.
			check_admin_referer(PAU_NONCE_UPLOAD, PAU_NONCE_UPLOAD);

			// User must be able to upload files to proceed
			if (! current_user_can('upload_files')) {
				$result = PAU_RESULT_NO_PERMISSION;
			} else {
				if ( $_FILES ) {
					// Don't need to test that this is a wp_upload_form in wp_handle_upload()
					$overrides = array( 'test_form' => false );

					$i = 0; // Loop counter
					foreach ( $_FILES as $key => $file ) {
						if ( empty( $file ) ) {
							continue; // Skip if value empty
						}

						$status = wp_handle_upload( $file, $overrides );
						if (isset($status['error'])) {
							continue; // Error on this file, go to next one.
						}

						// Image processing below based on Google example

						$url = $status['url'];
						$type = $status['type'];
						$file = $status['file'];
						
						// Use title, caption and description received from form
						$title = $_POST['title'][$i];
						$excerpt = $_POST['caption'][$i];
						$content = $_POST['description'][$i];

						$object = array_merge( array(
							'post_title' => $title,
							'post_content' => $content,
							'post_excerpt' => $excerpt,
							'post_parent' => 0,
							'post_mime_type' => $type,
							'guid' => $url), array());

						$id = wp_insert_attachment($object, $file,0);
						if ( !is_wp_error($id) ) {
							wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
							do_action('wp_create_file_in_uploads', $file, $id); // for replication
						}
						
						$i++; // Next array element					
					} // end foreach $file
					$result = PAU_RESULT_SUCCESS;
				} else {
					$result = PAU_RESULT_NO_FILES;
				}
			}

			// Tell Picasa to open a result page in the browser.
			echo self::build_url('result?result=' . $result);

			exit; // No more WP processing should be performed.
		}

		/**
		 * Generate the form used in the Picasa minibrowser to confirm the upload
		 *
		 * Examines $_POST['rss'] for RSS feed from Picasa to display form dialog
		 * used to confirm images to be uploaded and set any per-image fields
		 * per user request.
	   *
		 * @return string HTML form
		 * @access private
		 */
		private function build_upload_form() {
			// Form handling requires some javascript - depends on jQuery
			wp_enqueue_script('picasa-album-uploader', PAU_PLUGIN_URL . '/pau.js' ,'jquery');
			
			// Must be simple page name target in the POST action for Picasa to process the input URLs correctly.
			$content = "<form method='post' action='upload'>\n";

			// Add nonce field to the form if nonce is supported to improve security
			if ( function_exists( 'wp_nonce_field' ) ) {
				// Set referrer field, do not echo hidden nonce field
				$content .= wp_nonce_field(PAU_NONCE_UPLOAD, PAU_NONCE_UPLOAD, true, false);
				// Don't echo the referer field
				$content .= wp_referer_field(false);
			}

			// Parse the RSS feed from Picasa to get the images to be uploaded
			$xh = new xmlHandler();
			$nodeNames = array("PHOTO:THUMBNAIL", "PHOTO:IMGSRC", "TITLE", 'DESCRIPTION');
			$xh->setElementNames($nodeNames);
			$xh->setStartTag("ITEM");
			$xh->setVarsDefault();
			$xh->setXmlParser();
			$xh->setXmlData(stripslashes($_POST['rss']));
			$pData = $xh->xmlParse();

			// Start div used to display images
			$content .= "<p class='pau_header'>Selected images</p><div class='pau_images'>\n";

			// For each image, display the image and setup hidden form field for upload processing.
			foreach($pData as $e) {
				$content .= "<img class='pau_img' src='".attribute_escape( $e['photo:thumbnail'] )."?size=-96' title='".attribute_escape( $e['title'] )."'>";
				$large = attribute_escape( $e['photo:imgsrc'] ) ."?size=1024";
				$content .= "<input type='hidden' name='$large'>";
				
				// Add input tags to update image description, etc.
				// TODO Put fields into div that can be hidden/displayed
				$content .= "<dl class='pau_attributes'>\n"; // Start Definition List
				$content .= "<dt class='pau_img_header'>Title<dd><input type='text' name='title[]' class='pau_img_text' value='".attribute_escape( $e['title'] )."' />";
				$content .= "<dt class='pau_img_header'>Caption<dd><input type='text' name='caption[]' class='pau_img_text' />";				
				$content .= "<dt class='pau_img_header'>Description<dd><textarea name='description[]' class='pau_img_textarea' rows='4' cols='80'>".attribute_escape( $e['description'] )."</textarea>";
				$content .= "</dl>\n"; // End Definition List
			}

			// TODO Provide method for admin screen to pick available image sizes
			$content .= <<<FORM_FIN
</div>
<div class='header'>Select your upload image size
<INPUT type="radio" name="size" onclick="chURL('640')">640
<INPUT type="radio" name="size" onclick="chURL('1024')" CHECKED>1024
<INPUT type="radio" name="size" onclick="chURL('1600')">1600
<INPUT type="radio" name="size" onclick="chURL('0')">Original
</div>
<div class='button'>
<input type="submit" value="Upload">&nbsp;
</div>
FORM_FIN;

			return $content;
		}
		
		/**
		 * Fill in WordPress global $post data structure to describe the fake post
		 *
		 * @access private
		 */
		private function gen_post() {
			$formattedNow = date('Y-m-d H:i:s');
			
			// Create POST Data Structure
			$post = new stdClass();
			
			$post->ID = -1;										// Fake ID# for the post
			$post->post_author = 1;
			$post->post_date = $formattedNow;
			$post->post_date_gmt = $formattedNow;
			$post->post_title = 'Picasa Album Uploader';
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
			
			return $post;
		}
		
		/**
		 * Build a URL to pages generated by this plugin based on use of permalinks
		 *
		 * 
		 * @access public
		 * @return string URL to a plugin generated page
		 **/
		public function build_Url( $page )
		{
			$url = get_bloginfo('wpurl') . '/';
			if ( ! $this->using_permalinks ) {
				$url .= '?page_id=';
				# Request might include a parameter string.  Convert to ?p1&p2 syntax
				$page = str_replace('?', '&', $page);
			}
			$url .= $this->pau_options->slug . '/' . $page;
			
			return $url;
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