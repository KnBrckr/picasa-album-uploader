<?php
/*
Plugin Name: Picasa Album Uploader
Plugin URI: http://pumastudios.com/software/picasa-album-uploader-wordpress-plugin
Description: Easily upload media from Google Picasa Desktop into WordPress.  Navigate to <a href="options-media.php">Settings &rarr; Media</a> to configure.
Version: 0.7.3
Author: Kenneth J. Brucker
Author URI: http://pumastudios.com/blog/
Text Domain: picasa-album-uploader

Copyright: 2013 Kenneth J. Brucker (email: ken@pumastudios.com)

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

if ( ! defined( 'PAU_PLUGIN_NAME' ) ) {
	// If Plugin Name not defined, then must need to define all constants used
	
	define( 'PAU_PLUGIN_NAME', 'picasa-album-uploader' );	// Plugin name
	define( 'PAU_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . PAU_PLUGIN_NAME );	// Base directory for Plugin
	define( 'PAU_PLUGIN_URL', plugins_url(PAU_PLUGIN_NAME));	// Base URL for plugin directory
	
	// Name strings used in Nonce hanldling
	define( 'PAU_NONCE_UPLOAD', 'picasa-album-uploader-upload-images');

	define ( 'PAU_BUTTON_FILE_NAME', 'picasa_album_uploader.pbz');

	// plugin function requested based on URL request
	define('PAU_BUTTON', 1);       // Download Picasa button file
	define('PAU_MINIBROWSER', 2);  // Upload screen for Picasa minibrowser
	define('PAU_UPLOAD', 3);       // Receive upload from Picasa
	define('PAU_RESULT', 4);       // Report upload results
	define('PAU_LOGIN', 5);        // User login screen
	define('PAU_SELFTEST', 6);     // Perform selftest checks
	
	// result codes on upload completion or failure
	define('PAU_RESULT_SUCCESS', 'success');
	define('PAU_RESULT_NO_FILES', 'no-files');
	define('PAU_RESULT_NO_PERMISSION', 'no-permission');
}	

// ================================
// = Include libries and handlers =
// ================================

// Include admin portion of plugin
if ( ( include_once PAU_PLUGIN_DIR . '/admin/options.php' ) == FALSE ) {
	pau_error_log("Unable to load admin/options");
	return;	// Required file not available
}

// zip.lib.php is copied from phpMyAdmin - great little library for generating zip archives on the fly
if ( ( include_once PAU_PLUGIN_DIR . '/lib/zip.lib.php') == FALSE ) {
	pau_error_log("Unable to load zip lib");
	return;	// Required file not available
}

// xmlHandler.class copied from Google's sample handler
if ( ( include_once PAU_PLUGIN_DIR . '/lib/xmlHandler.class')  == FALSE ) {
	pau_error_log("Unable to load xml Handler");
	return;	// Required file not available
}

global $pau;
global $pau_errors;
global $pau_versions;

$pau_versions[] = '$Id: picasa-album-uploader.php 534002 2012-04-20 12:34:47Z draca $'; // SVN Version string
	
// =================================
// = Define the picasa album class =
// =================================

if ( ! class_exists( 'picasa_album_uploader' ) ) {
	class picasa_album_uploader {
		/**
		 * Minimum version of WordPress required by plugin
		 **/
		const wp_version_required = '3.1';

		/**
		 * Minimum version of PHP required by the plugin
		 **/
		const php_version_required = '5.2';
		
		/**
		 * Instansiate option class - provides access to plugin options and debug logging
		 *
		 * @var string
		 * @access private
		 **/
		var $pau_options;
		
		/**
		 * Constructor function for picasa_album_uploader class.
		 *
		 * Adds the needed shortcodes and filters to processing stream
		 *
		 * @access public
		 * @return void
		 */
		function picasa_album_uploader() {
			// Retrieve plugin options
			$this->pau_options = new picasa_album_uploader_options();
			
			// register admin section with WP
			$this->pau_options->register();

			// Plugin requires permalink usage - Only setup handling if permalinks enabled
			if ( get_option('permalink_structure') != '' ) {
				// Shortcode to generate URL to download Picassa Button
				add_shortcode( 'picasa_album_uploader_button', array( &$this, 'sc_download_button' ) );

				// Add action to check if requested URL matches slug handled by plugin
				add_filter( 'the_posts', array( &$this, 'check_url' ));				
			} else {
				$this->pau_options->debug_log('Permalinks not enabled - Plugin filter is not setup.');
			}
			
			// Javascript and Styles
			add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
			
			// i18n support
			add_action('init', array(&$this, 'load_textdomain'));
		}
		
		/**
		 * Enqueue Scripts and Styles
		 *
		 * @access public
		 * @return void
		 **/
		function enqueue_scripts()
		{
			// Register Plugin CSS
			wp_register_style('picasa-album-uploader-style', PAU_PLUGIN_URL . '/picasa-album-uploader.css');
			wp_enqueue_style('picasa-album-uploader-style');
			
			wp_register_style('picasa-album-uploader-minibrowser', PAU_PLUGIN_URL . '/minibrowser.css');

			// Register Plugin javascript
			wp_register_script('picasa-album-uploader-minibrowser', PAU_PLUGIN_URL . '/minibrowser.js', array('jquery'));				
		}
		
		/**
		 * WP callback to turn download button shortcode into HTML link
		 *
		 * @access public
		 * @param array $atts shortcode attributes - Not used by function
		 * @param string $content shortcode content - Not used by function
		 * @return string URL to download Picasa button
		 */
		function sc_download_button( $atts, $content = null ) {
			$link =  '<a href="picasa://importbutton/?url=' . $this->pau_options->build_url(PAU_BUTTON_FILE_NAME)
				. '" title="' . __('Download Picasa Button and Install in Picasa Desktop', 'picasa-album-uploader'). '">'
				. __('Install Image Upload Button in Picasa Desktop', 'picasa-album-uploader'). '</a>';
			return $link;
		}	
		
		/**
		 * WP filter to examine post request for match to slug handled by the plugin.
		 *
		 * If URL is handled by plugin, environment setup to catch it in the template redirect and
		 * a new Array of Posts containing a single element is created for the plugin processed post.
		 *
		 * @access public
		 * @param array $posts Posts matching $wp_query
		 * @uses $wp Access to WP URL request
		 * @uses $wp_query manage WP query result information
		 * @return array Array of Posts to be displayed by WP
		 */
		function check_url( $posts ) {
			global $wp;
			global $wp_query;
			
			// Determine if request should be handled by this plugin
			$requested_page = self::parse_request($wp->request);
			if (! $requested_page) {
				$this->pau_options->debug_log("Request is not for plugin: '".$wp->request."'");
				return $posts;
			}
			
			$this->pau_options->debug_log("Request will be handled by plugin");
			
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
			
			$result = isset($_REQUEST['result']) ? $_REQUEST['result'] : '';
			$file_count = isset($_REQUEST['file_count']) ? $_REQUEST['file_count'] : '';
			$errors = isset($_REQUEST['errors']) ? $_REQUEST['errors'] : '';

			// If this is a result page it will be handled by default browser & template redirect is not needed
			if ( PAU_RESULT == $this->pau_serve ) {
				$post->post_content = self::result_page($result, $file_count, $errors);
			} else {
				// Add template redirect action to process the page
				add_action('template_redirect', array(&$this, 'template_redirect'));				
			}
			
			return array($post);
		}
		
		/**
		 * Generate content for the results page
		 *
		 * @access private
		 * @param int $result_code result of upload request
		 * @param int $file_count number of files uploaded
		 * @param int $errors number of errors encountered during upload
		 * @return post content
		 **/
		function result_page($result_code, $file_count, $errors)
		{
			switch ($result_code) {

				// All is well, uploaded some files from Picasa
				case PAU_RESULT_SUCCESS:
					$good = $file_count - $errors; // Number of successful uploaded files
					$content = '<p>' . sprintf(_n('%d image uploaded successfully.','%d images uploaded successfully.', $good, 'picasa-album-uploader'),$good) . '</p>';
					if ($errors > 0) {
						$content .= '<p>' . sprintf(_n('%d error detected.','%d errors detected.', $errors, 'picasa-album-uploader'), $errors);
						$content .= '  ' . __('See the server error log for details.', 'picasa-album-uploader') . '</p>';
					}
					break;
					
				// Plugin did not receive files from Picasa for upload.  Probable server configuration problem
				case PAU_RESULT_NO_FILES:
					$content = '<p>' . __('Error:  No files received in upload request.  Related errors might appear in the server error log.', 'picasa-album-uploader') . '</p>';
				
					// Perform long-var check now, could be reason no files received.
					if ( $result = $this->pau_options->test_long_var() ) {
						$content .= '<p>' . __('Your server might be configured to restrict the length of request argument names.','picasa-album-uploader') . '</p>';
						$content .= '<p>' . __('Possible modules causing this include Suhosin and mod_security.') . ' ';
						$content .= sprintf(__('More details are available in the Picasa Album Uploader %s',  'picasa-album-uploader'), '<a href="https://wordpress.org/extend/plugins/picasa-album-uploader/faq/">' . __('FAQ',  'picasa-album-uploader') . '.</a>');
						$content .= '</p>';
					}
					break;
					
				// User does not have required permission for uploads
				case PAU_RESULT_NO_PERMISSION:
					$content = '<p>' . __('Sorry, You do not have permission to upload files to this site.', 'picasa-album-uploader') . '</p>';
					break;
					
				// Unknown result - should not get here
				default:
					$content = '<p>' . sprintf(__('Unknown result code %d reported.', 'picasa-album-uploader'), $result_code) . '</p>';
					$this->pau_options->debug_log('*** Unknown result code ' . $result_code . ' reported.');
					break;
			}
			
			return $content;
		}

		/**
		 * WP action to perform template redirect processing for requests handled by the plugin
		 *
		 * This function will not return under normal conditions.  Each case
		 * handled by the plugin via template redirect results in a complete page or action with no
		 * further action needed by WordPress core.
		 *
		 * @access public
		 * @param string $requested_url - not used
		 * @param boolean $do_redirect - not used
		 * @return does not return to caller in normal conditions
		 **/
		function template_redirect( $requested_url=null, $do_redirect=true ) {			
			switch ( $this->pau_serve ) {
				case PAU_BUTTON:
					$this->send_picasa_button();
					// Should not get here
					exit;
				case PAU_MINIBROWSER:
					$this->minibrowser();
					// Should not get here
					exit;
				case PAU_UPLOAD:
					$this->upload_images();
					// Should not get here
					exit;
				case PAU_LOGIN:
					$this->login();
					// Should not get here
					exit;
				case PAU_SELFTEST:
					$this->test_access();
					// Should not get here
					exit;
			}
			
			$this->pau_options->debug_log('*** Unknown template redirect code ' . $this->pau_serve . '.  Redirect not performed');
		}
		
		/**
		 * WP action to load the i18n text domain for the plugin
		 *
		 * @access public
		 * @return void
		 **/
		function load_textdomain()
		{
			load_plugin_textdomain(PAU_PLUGIN_NAME , false, basename(dirname(__FILE__)) . '/languages' );
		}
		
		/**
		 * WP filter Add plugin defined classes to body
		 *
		 * Update body class to flag as a page generated by this plugin.
		 * The class is useful in managing the formatting of the entire page in the picasa minibrowser.
		 *
		 * @access public
		 * @param array $classes list of classes to update
		 * @return array list of classes to apply to body element
		 */
		function add_body_class($classes) {
			$classes[] = "picasa-album-uploader-minibrowser";
			
			return $classes;
		}
		
		/**
		 * WP filter to add plugin defined class to a post
		 *
		 * Update an entry to flag as a post generated by this plugin for CSS formatting.
		 *
		 * @access public
		 * @param array $classes list of classes to update
		 * @return array classes to apply to a post
		 **/
		function add_post_class($classes)
		{
			$classes[] = "post-picasa-album-uploader";
			return $classes;
		}
		
		/**
		 * Parse incoming request and test if it should be handled by this plugin.
		 *
		 * $this->pau_serve will store type of request to be processed for later stages in WP handling
		 *
		 * @access private
		 * @param string $query WP URL query
		 * @return boolean True if request is to be handled by the plugin
		 */
		private function parse_request( $query ){
			$tokens = explode( '/', $query );
			
			$this->pau_options->debug_log("Parsing request '$query'");

			if ( $this->pau_options->slug != $tokens[0] ) {
				return false; // Request is not for this plugin
			}
			
			// Decode plugin request
			switch ( $tokens[1] ) {
				case PAU_BUTTON_FILE_NAME:
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
					
				case 'login':
					$this->pau_serve = PAU_LOGIN;
					break;
					
				case 'selftest':
					$this->pau_serve = PAU_SELFTEST;
					break;

				default:
					$this->pau_options->debug_log("bad request token: '" . $tokens[1] . "'");
					return false; // slug matched, but 2nd token did not
			}
			
			return true; // Have a valid request to be handled by this plugin
		}
		
		/**
		 * login form template redirect to present login screen for user and handle login request.
		 * Redirects back to minibrowser on successful login.
		 *
		 * @access private
		 * @uses $post For setup of post content
		 * @return function does not return.
		 **/
		private function login()
		{
			global $post;
			
			// Gather request data if here from a POST action
			$log = isset($_REQUEST['log']) ? trim($_REQUEST['log']) : '';
			$pwd = isset($_REQUEST['pwd']) ? trim($_REQUEST['pwd']) : '';
			
			$error = '';
			
			if ($log != '') {
				$signon = wp_signon();
				if (get_class($signon) == 'WP_User') {
					$this->pau_options->debug_log("Login successful - redirecting to minibrowser");
					wp_redirect($this->pau_options->build_url('minibrowser'));
					$this->pau_options->save_debug_log();
					exit;											
				} else {
					$error = __('Invalid username and password combination, please try again', 'picasa-album-uploader');
				}				
			}

			$this->pau_options->debug_log("Generating login window content");

			// Add minibrowser styling
			wp_enqueue_style('picasa-album-uploader-minibrowser');			

			// Add class to the body element for CSS styling of the entire page that will be displayed in the minibrowser
			add_filter('body_class', array(&$this, 'add_body_class'));

			$content = '<div class=pau-login-error>' . $error . '</div>';			
				
			$form = wp_login_form(array(
				'echo' => false,
				'form_id' => 'pau-login-form',
			));
			
			$content .= preg_replace('/action=".*?"/', 'action="' . $this->pau_options->build_url('login') . '"', $form);
			
			// Setup post content
			$post->post_content = $content;
		
			// If Theme has defined the plugin template, use it, otherwise use template from the plugin
			if ($theme_template = get_query_template('page-picasa_album_uploader')) {
				$this->pau_options->debug_log("Using Theme supplied template: " . $theme_template);
				include($theme_template);
			} else {
				$this->pau_options->debug_log("Using plugin supplied template");
				include(PAU_PLUGIN_DIR.'/templates/page-picasa_album_uploader.php');
			}

			// Save log file messages before exit
			$this->pau_options->save_debug_log();
			exit; // Finished displaying the minibrowser page - No more WP processing should be performed
		}
		
		/**
		 * template redirect for Picasa minibrowser image uploading form
		 * 
		 * @access private
		 * @uses $post
		 * @return function does not return
		 */
		private function minibrowser() {
			global $post; // To setup the Post content
									
			$this->pau_options->debug_log("Generating Minibrowser content");
			
			// Add class to the body element for CSS styling of the entire page that will be displayed in the minibrowser
			add_filter('body_class', array(&$this, 'add_body_class'));
			
			// Open the plugin content div for theme formatting
			$content = '<div id="post-pau-minibrowser">';

			if (! is_user_logged_in()) {
				$this->pau_options->debug_log("Redirecting minibrowser request to login");

				// Redirect user to the login page - login process will redirect back here on success
				wp_redirect($this->pau_options->build_url('login') );
				$this->pau_options->save_debug_log();  // Save log file messages before exit
				exit;  // Requested browser to redirect - done here.
			} elseif (current_user_can('upload_files')) {
				// Add the upload form to content
				$content .= self::build_upload_form();				
			} else {
				$this->pau_options->debug_log("Permission violation - user not allowed to upload");
				
				// TODO Add a logout capability
				$content .= '<div class="pau-privs-error">';
				$content .= '<p class="error">' . __('Sorry, you do not have permission to upload files.', 'picasa-album-uploader') . '</p>';
				$content .= '</div>';
			}
			
			// TODO Error states would be better displayed in browser to avoid use of the Picasa minibrowser 
			//      as a general purpose browser window.
			
			$content .= '</div>';  // Close picasa_album_uploader div for this post text
			
			$post->post_content = $content;
			
			// If Theme has a defined the plugin template, use it, otherwise use template from the plugin
			if ($theme_template = get_query_template('page-picasa_album_uploader')) {
				$this->pau_options->debug_log("Using Theme supplied template: " . $theme_template);
				include($theme_template);
			} else {
				$this->pau_options->debug_log("Using plugin supplied template");
				include(PAU_PLUGIN_DIR.'/templates/page-picasa_album_uploader.php');
			}

			// Save log file messages before exit
			$this->pau_options->save_debug_log();
			exit; // Finished displaying the minibrowser page - No more WP processing should be performed
		}
		
		/**
		 * template redirect to processes POST request from Picasa to upload images and save in Wordpress.
		 *
		 * Picasa will close the minibrowser - Any HTML output will be ignored.
		 * Picasa will accept a URL that will be opened in the user's browser.
		 *
		 * @access private
		 * @return function does not return
		 */
		private function upload_images() {
			$errors = 0;
			$file_count = 0;
			
			$this->pau_options->debug_log("Upload request received");
			$this->pau_options->debug_log("_FILES: " . print_r($_FILES,true));
			$this->pau_options->debug_log("_POST: " . print_r($_POST,true));
			
			require_once( ABSPATH . 'wp-admin/includes/admin.php' ); // Load functions to handle uploads

			// Confirm the nonce field to allow operation to continue
			check_admin_referer(PAU_NONCE_UPLOAD, PAU_NONCE_UPLOAD);

			// User must be able to upload files to proceed
			if (! current_user_can('upload_files')) {
				$this->pau_options->debug_log("User is not allowed to upload files.");
				$result = PAU_RESULT_NO_PERMISSION;
			} else {
				if ( $_FILES ) {
					// Don't need to test that this is a wp_upload_form in wp_handle_upload() in loop below so set test_form to false
					$overrides = array( 'test_form' => false );

					foreach ( $_FILES as $key => $file ) {
						$file_count++; // Count number of files handled
						
						if ( empty( $file ) ) {
							$this->pau_options->error_log("File information missing for uploaded file.");
							$errors++;
							continue; // Skip if value empty
						}		

						$status = wp_handle_upload( $file, $overrides );
						if (isset($status['error'])) {
							$this->pau_options->error_log("Error detected during file upload: " . $status['error']);
							$errors++;
							continue; // Error on this file, go to next one.
						}

						// Image processing below based on Google example

						$url = $status['url'];
						$type = $status['type'];
						$file_name = $status['file'];						
						
						// Use title, caption and description received from form (subtract 1 from file count to get zero based index)
						$title = $_POST['title'][$file_count - 1];
						$excerpt = $_POST['caption'][$file_count - 1];
						$content = $_POST['description'][$file_count - 1];
						
						$this->pau_options->debug_log('Received file: "' . $file_name . '"'); 
						$this->pau_options->debug_log('Title: "' . $title . '"');
						$this->pau_options->debug_log('Excerpt: "' . $excerpt . '"');
						$this->pau_options->debug_log('Description: "' . $content . '"');

						$object = array_merge( array(
							'post_title' => $title,
							'post_content' => $content,
							'post_excerpt' => $excerpt,
							'post_parent' => 0,
							'post_mime_type' => $type,
							'guid' => $url), array());
						
						// Insert the image into the WP media library
						$id = wp_insert_attachment($object, $file_name, 0);
						if ( !is_wp_error($id) ) {
							wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file_name ) );
							do_action('wp_create_file_in_uploads', $file_name, $id); // for replication
						} else {
							$this->pau_options->error_log("Error from wp_insert_attachment: " . $id->get_error_message());
							$errors++;
						}
					} // end foreach $file
					$this->pau_options->debug_log("Processed $file_count files from Picasa with $errors errors.");
					$result = PAU_RESULT_SUCCESS;
				} else {
					$this->pau_options->debug_log("Picasa did not upload any files");
					$result = PAU_RESULT_NO_FILES;
				}
			}

			// Provide Picasa URL to open a result page in the browser.
			echo $this->pau_options->build_url('result?result=' . $result . '&errors=' . $errors . '&file_count=' . $file_count);

			// Save log file messages before exit
			$this->pau_options->save_debug_log();
			exit; // No more WP processing should be performed.
		}

		/**
		 * Generate the form used in the Picasa minibrowser to confirm the upload
		 *
		 * Examines $_POST['rss'] for RSS feed from Picasa to display form dialog
		 * used to confirm images to be uploaded and set any per-image fields
		 * per user request.
	   *
		 * @access private
		 * @return string HTML form
		 */
		private function build_upload_form() {
			// Form handling in the minibrowser requires some javascript - depends on jQuery
			wp_enqueue_script('picasa-album-uploader-minibrowser');
			wp_enqueue_style('picasa-album-uploader-minibrowser');
			
			if (isset($_POST['rss'])) {
				$this->pau_options->debug_log('Using $_POST["rss"]');
				$rss_data = $_POST['rss'];				
			}
			elseif (isset($_POST['RSS'])) {
				$this->pau_options->debug_log('Using $_POST["RSS"]');
				$rss_data = $_POST['RSS'];				
			}
			
			$content = '<div id="pau-upload-form">';
			if (isset($rss_data) && $rss_data) {
							// **************************************************************************************************
							// MUST be simple page name target in the POST action for Picasa to process the input URLs correctly.
							// Can't use:
							//			$content = '<form method="post" action="' . $this->pau_options->build_url('upload') . '">';
							// **************************************************************************************************
							$content .= '<form method="post" action="upload">';

							// Add nonce field to the form
							if ( function_exists( 'wp_nonce_field' ) ) {
								// Set nonce and referer fields, use return value vs. echo
								$content .= wp_nonce_field(PAU_NONCE_UPLOAD, PAU_NONCE_UPLOAD, true, false);
								$content .= wp_referer_field(false);
							}

							// Parse the RSS feed from Picasa to get the images to be uploaded
							$xh = new xmlHandler();
							$nodeNames = array("PHOTO:THUMBNAIL", "PHOTO:IMGSRC", "TITLE", "DESCRIPTION");
							$xh->setElementNames($nodeNames);
							$xh->setStartTag("ITEM");
							$xh->setVarsDefault();
							$xh->setXmlParser();
							$xh->setXmlData(stripslashes($rss_data));
							$pData = $xh->xmlParse();

							// Start div used to display images
							$content .= '<p class="pau-header">' . __('Selected images', 'picasa-album-uploader') . '</p>';
							$content .= '<div class="pau-images">';

							// For each image, display the image and setup hidden form field for upload processing.
							foreach($pData as $e) {
								$this->pau_options->debug_log("Form Setup: " . esc_attr($e['photo:imgsrc']));

								$content .= '<div  class="pau-img">';
								$title = isset($e['title']) ? esc_attr( $e['title'] ) : '';
								$description = isset($e['description']) ? esc_attr( $e['description'] ) : '';
								$large = esc_attr( $e['photo:imgsrc'] ) .'?size=1024';

								$content .= "<img alt='Image from Picasa' src='".esc_attr( $e['photo:thumbnail'] )."?size=-96' title='" . $title . "'>";
								
								// Add input tags to update image description, etc.
								// TODO Put fields into div that can be hidden/displayed
								$content .= '<div class="pau-attributes">'; // Start Definition List
								$content .= '<input type="hidden" name="' . $large . '">';
								$content .= '<label><span>' . __('Title', 'picasa-album-uploader') . '</span><input type="text" name="title[]" class="pau-img-text" value="' . $title . '" /></label>';
								$content .= '<label><span>' . __('Caption', 'picasa-album-uploader') . '</span><input type="text" name="caption[]" class="pau-img-text" /></label>';				
								$content .= '<label><span>' . __('Description', 'picasa-album-uploader') . '</span><textarea name="description[]" class="pau-img-textarea" rows="4" cols="80">' . $description . '</textarea></label>';
								$content .= '</div>'; // End Image Attributes
								$content .= '</div>';
							} // End of image list

							// TODO Provide method for admin screen to pick available image sizes
							$content .= '</div><!-- End of pau-images -->';
							$content .= '<div class="pau-img-size">';
							$content .= __('Select your upload image size:', 'picasa-album-uploader');
							$content .= '<label><INPUT type="radio" name="size" onclick="chURL(\'640\')">640</label>';
							$content .= '<label><INPUT type="radio" name="size" onclick="chURL(\'1024\')" CHECKED>1024</label>';
							$content .= '<label><INPUT type="radio" name="size" onclick="chURL(\'1600\')">1600</label>';
							$content .= '<label><INPUT type="radio" name="size" onclick="chURL(\'0\')">Original</label>';
							$content .= '</div><!-- End image size selection -->';
							$content .= '<div class="button">';
							$content .= '<input type="submit" value="' . __('Upload', 'picasa-album-uploader') . '">&nbsp;';
							$content .= '</div></form>';
			} else {
				$this->pau_options->error_log("Empty RSS feed from Picasa; unable to build minibrowser form.  Dumping POST payload:");
				$this->pau_options->debug_log("_POST: " . print_r($_POST,true));
				
			 	$content .= '<p class="error">' . __('Sorry, no images were received from Picasa.', 'picasa-album-uploader') . '</p>';
			}
			$content .= '</div>';
			
			return $content;
		}
		
		/**
		 * Create a plugin generated post
		 *
		 * @access private
		 * @return object New post object
		 */
		private function gen_post() {
			$formattedNow = date('Y-m-d H:i:s');
			
			// Create POST Object
			$post = new stdClass();
			
			$post->ID = -1;										// Fake ID# for the post
			$post->post_author = 1;
			$post->post_date = $formattedNow;
			$post->post_date_gmt = $formattedNow;
			$post->post_title = __('Picasa Album Uploader', 'picasa-album-uploader');
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
			$post->guid = WP_PLUGIN_URL;
			$post->menu_order = 0;
			$post->post_type = 'page';
			$post->post_mime_type = '';
			$post->comment_count = 0;
			
			// Add plugin class to the generated post for CSS formatting
			add_filter('post_class', array(&$this, 'add_post_class'));
			
			// Plugin crafted the content - avoid wpautop formatting which inserts <br> elements when not desired
			remove_filter( 'the_content', 'wpautop' );
			
			return $post;
		}
				
		/**
		 * template redirect to generate the Picasa PZB file and save as a media file for later download.
		 *
		 * See http://code.google.com/apis/picasa/docs/button_api.html for a
		 * description of the contents of the PZB file.
		 *
		 * @access private
		 * @return this function does not return
		 */
		private function send_picasa_button( ) {
			$blogname = get_bloginfo( 'name' );
			$guid = self::guid(); // TODO Only Generate GUID once for a blog - keep same guid - allow blog config to update it.
			$upload_url = $this->pau_options->build_url('minibrowser');
			
			// XML to describe the Picasa plugin button
			$pbf = <<<EOF
<?xml  version="1.0" encoding="utf-8" ?>
<buttons format="1" version="1">
   <button id="picasa-album-uploader/$guid" type="dynamic">
   	<icon name="$guid/upload-button" src="pbz"/>
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
			if (null == $zip) {
				$this->pau_options->error_log("Unable to initialize zipfile module; can't generate button.");
				$this->pau_options->save_debug_log();  // Must call directly to save since process will exit
				
				header('HTTP/1.1 500 Plugin failed initialization.');
				echo "Unable to initialize zipfile module; can't generate button.";
				exit;  // No more WP processing should be performed
			}
			$zip->addFile( $pbf, $guid . '.pbf' );

			// TODO Allow icon to be replaced by theme
			// Add PSD icon to zip
			$psd_filename =  PAU_PLUGIN_DIR . '/images/wordpress-logo-blue.psd'; // button icon
			$fsize = @filesize( $psd_filename );
			if (false == $fsize) {
				$this->pau_options->error_log('Unable to get filesize of ' . $psd_filename . '; can\'t generate button.');
				$this->pau_options->save_debug_log();  // Must call directly to save since process will exit

				header('HTTP/1.1 500 Missing required plugin file.');
				echo 'Unable to get filesize of ' . $psd_filename . '; can\'t generate button.';
				exit;  // No more WP processing should be performed
			}
			
			$zip->addFile( file_get_contents( $psd_filename ), $guid . '.psd' );

			// Emit zip file to the client
			$zipcontents = $zip->file();
			header( 'Content-type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . PAU_BUTTON_FILE_NAME . '"' );
			header( 'Content-length: ' . strlen($zipcontents) );

			echo $zipcontents;
			
			$this->pau_options->debug_log('Delivered button file to client');
			$this->pau_options->save_debug_log();  // Must call directly to save since process will exit
			
			exit; // Finished sending the button - No more WP processing should be performed
		}

		/**
		 * Generate a standard format guid
		 *
		 * @access private
		 * @return string UUID in form: {xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx}
		 */
		private function guid() {
			if ( function_exists( 'com_create_guid' ) ) {
				return com_create_guid();
			} else {
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

		/**
		 * Perform Plugin Activation handling
		 *  * Confirm that plugin environment requirements are met
		 *
		 * @internal This function is not called in class context - $this will not be available
		 * @access public
		 * @return void
		 **/
		public static function plugin_activation()
		{
			global $wp_version;

			// Enforce minimum PHP version requirements
			if (version_compare(self::php_version_required, phpversion(), '>')) {
				die(PAU_PLUGIN_NAME . ' plugin requires minimum PHP v' . self::php_version_required . '.  You are running v' . phpversion());
			}

			// Enforce minimum WP version
			if (version_compare(self::wp_version_required, $wp_version, '>')) {
				die(PAU_PLUGIN_NAME . ' plugin requires minimum WordPress v' . self::wp_version_required . '.  You are running v' . $wp_version);
			}
		}
		
		/**
		 * template redirect to respond to self test request
		 *
		 * @access private
		 * @return void
		 **/
		private function test_access()
		{
			if (isset($_REQUEST[$this->pau_options->long_var_name])) {
				if ($_REQUEST[$this->pau_options->long_var_name] == $this->pau_options->long_var_name) {
					header('Status: 200 OK');
					header('HTTP/1.1 200 OK');
					echo 'REQUEST long variable OK, received length=' . strlen($this->pau_options->long_var_name);
				} else {
					header('Status: 400 REQUEST long variable received - Data wrong');
					header('HTTP/1.1 400 REQUEST long variable received - Data wrong');
					echo 'REQUEST long variable received - Data wrong';
				}
			} else {
				header('Status: 400 REQUEST missing expected variable');
			  header('HTTP/1.1 400 REQUEST missing expected variable');
				echo 'REQUEST missing expected variable';
			}
		}		
	} // End Class picasa_album_uploader
}

function pau_error_log($msg) {
	global $pau_errors;

	if ( ! is_array( $pau_errors ) ) {
		add_action('admin_footer', 'pau_error_log_display');
		$pau_errors = array();
	}
	
	array_push($pau_errors, PAU_PLUGIN_NAME . $msg);
}

// Display errors logged when the plugin options module is not available.
function pau_error_log_display() {
	echo "<div class='error'><p><a href='options-media.php'>" . PAU_PLUGIN_NAME 
		. "</a> unable to initialize correctly.  Error(s):<br />";
	foreach ($pau_errors as $line) {
		echo "$line<br/>\n";
	}
	echo "</p></div>";
}

// =========================
// = Plugin initialization =
// =========================

$pau = new picasa_album_uploader();

// Setup plugin activation function
register_activation_hook( __FILE__, array('picasa_album_uploader', 'plugin_activation'));


?>