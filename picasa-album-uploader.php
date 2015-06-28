<?php
/*
Plugin Name: Picasa Album Uploader
Plugin URI: http://pumastudios.com/software/picasa-album-uploader-wordpress-plugin
Description: Easily upload media from Google Picasa Desktop into WordPress.  Navigate to <a href="options-media.php">Settings &rarr; Media</a> to configure.
Version: 0.9
Author: Kenneth J. Brucker
Author URI: http://pumastudios.com/
Text Domain: picasa-album-uploader

Copyright: 2013,2015 Kenneth J. Brucker (email: ken@pumastudios.com)

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

Implementation notes:

This plugin creates several virtual pages hosted under the plugin slug name. 
The slug is changeable in options and defaults to the plugin name.

Virtual Pages served:
  /picasa-album-uploader/minibrowser
	The main upload selection page that will display in the Picasa minibrowser.
	This page is linked to from the button installed inside Picasa
  /picasa-album-uploader/upload
	Receives image upload data from Picasa
  /picasa-album-uploader/result
	Upload results screen
  /picasa-album-uploader/selftest
	Perform self-test operation for diagnostic purposes
  /picasa-album-uploader/<button_file_name>
	Downloads contents of button description file to be loaded into Picasa

Process flow:
 1. Select images in Picasa and click the Upload button
 2. Picasa sends request for 'minibrowser' page
 3. If not logged in redirect to 'login' page
 4. Build Upload form to handle each image selected by Picasa based on the request args
 5. When user selects upload option on displayed form in Picasa minibrower window it will send request to 'upload' page.
 6. Server processes uploaded images and redirects results to 'result' page.
*/

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

global $pau;
global $pau_errors;

// =======================================
// = Define constants used by the plugin =
// =======================================

if ( ! defined( 'PAU_PLUGIN_NAME' ) ) {
	// If Plugin Name not defined, then must need to define all constants used
	
	define('PAU_PLUGIN_NAME', 'picasa-album-uploader');	// Plugin name
	define('PAU_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . PAU_PLUGIN_NAME);	// Base directory for Plugin
	define('PAU_PLUGIN_URL', plugins_url(PAU_PLUGIN_NAME));	// Base URL for plugin directory
	
	// Name strings used in Nonce hanldling
	define('PAU_NONCE_UPLOAD', 'picasa-album-uploader-upload-images');

	// Define the Picasa button file name
	define('PAU_BUTTON_FILE_NAME', sanitize_file_name(get_bloginfo('name') . '.pbz'));

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

// =================================
// = Define the picasa album class =
// =================================

if ( ! class_exists( 'picasa_album_uploader' ) ) {
	class picasa_album_uploader {
		/**
		 * Minimum version of WordPress required by plugin
		 **/
		const wp_version_required = '4.0';

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

				// Hook action to check if requested URL matches slug handled by plugin
				add_action ('parse_request', array($this, 'parse_request'));
			} else {
				$this->pau_options->debug_log('Permalinks not enabled - Plugin filter is not setup.');
			}
			
			// Javascript and Styles
			add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
			
			// i18n support
			add_action('init', array($this, 'load_textdomain'));
		}
		
		/**
		 * Parse incoming request to determine if the plugin needs to handle it
		 *
		 * @return $query
		 */
		function parse_request($query)
		{
			if (! isset($query->query_vars['pagename'])) return $query;
			
			$tokens = explode('/', $query->query_vars['pagename']);
			$this->pau_options->debug_log("Parsing request " . implode('/', $tokens));
			
			if (count($tokens) != 2) {
				$this->pau_options->debug_log("Valid requests will have 2 elements, skipping.");
				return $query;
			}

			// Does request slug match request for this plugin?
			if ( $this->pau_options->slug != $tokens[0] ) {
				// Request is not for this plugin
				$this->pau_options->debug_log("Request does not match plugin slug (" . $this->pau_options->slug . ")");
				return $query;
			}
			
			// Decode plugin request
			switch ( $tokens[1] ) {
				case PAU_BUTTON_FILE_NAME:
					// Process request for button immediately, no further WP processing is required.
					// No authorization required because the client request from Picasa can not be authenticated.
					// There is also no security harm in downloading the button file.
					$this->send_picasa_button();
					// Should not get here
					exit;
				
				case 'minibrowser':
					// Immediately handle display of form to upload content in the Picasa minibrowser window
					$this->confirm_valid_user();
					$this->display_upload_form();
					exit;
			
				case 'upload':
					// Immediately handle image upload if it's been requested
					$this->confirm_valid_user();
					$this->upload_images();
					// Should not get here
					exit;
					
				case 'selftest':
					// Immediately handle the self-test request
					$this->confirm_valid_user();
					$this->test_access();
					// Should not get here
					exit;

				default:
					// Have a valid plugin slug, but missing the sub-token.
					$this->pau_options->debug_log("Request '". $tokens[1] . "' is not recognized.");
					break;
			}
		
			return $query;
		}
		
		/**
		 * Confirms that the user is logged in before continuing.
		 * If user is not logged in, will redirect to the WP login form.
		 *
		 * @return void or redirects to login form
		 */
		private function confirm_valid_user()
		{
			if (! is_user_logged_in()) {
				$this->pau_options->debug_log("Redirecting request to login");

				// Redirect user to the login page - login process will redirect back here on success
				wp_safe_redirect(wp_login_url($this->pau_options->build_url('minibrowser')));
				$this->pau_options->save_debug_log();  // Save log file messages before exit
				exit;  // Requested browser to redirect - done here.
			}
		}
		
		/**
		 * Enqueue Scripts and Styles
		 *
		 * @access public
		 * @return void
		 **/
		function admin_enqueue_scripts()
		{
			// Register Plugin CSS
			wp_register_style('picasa-album-uploader-style', PAU_PLUGIN_URL . '/picasa-album-uploader.css');
			wp_enqueue_style('picasa-album-uploader-style');
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
		 * Generate Picasa minibrowser image uploading form
		 * 
		 * @access public
		 * @return emits form HTML and exits
		 */
		function display_upload_form() {
			global $wp_scripts;
			
			$this->pau_options->debug_log("Generating Minibrowser content");
			?>
<!doctype html>

<html lang="en">
<head>
  <meta charset="utf-8">

  <title>Picasa Album Uploader Minibrower Selection Form</title>
  <meta name="description" content="Upload form to display in Picasa to manage upload of images to website.">

  <link rel="stylesheet" href="<?php echo PAU_PLUGIN_URL; ?>/minibrowser.css">
  <?php
  if (isset($wp_scripts->registered['jquery-core'])) {
	  echo '<script type="text/javascript" src="';
	  echo home_url() . $wp_scripts->registered['jquery-core']->src;  
	  echo '"></script>';
  }
  ?>
  <script type="text/javascript">
  /**
   * Fixup the hidden <input> tags Picasa detects to create the upload stream of files.
   * This function based on example downloaded from Google.
   *
   * @params string Image size to be generated by Picasa for upload
   */
  function chURL(psize){
  	jQuery("input[type='hidden']").each(function()
  	{
  		this.name = this.name.replace(/size=.*/,"size="+psize);
  	});
  }
  </script>
</head>

<body>
  <?php
			// Open the plugin content div for theme formatting

			if (current_user_can('upload_files')) {
				// Add the upload form to content
				echo $this->build_upload_form();				
			} else {
				$this->pau_options->debug_log("Permission violation - user not allowed to upload");
	
				// TODO Add a logout capability
				echo '<div class="pau-privs-error">';
				echo '<p class="error">' . __('Sorry, you do not have permission to upload files.', 'picasa-album-uploader') . '</p>';
				echo '</div>';
			}
  ?>
</body>
</html>
			<?php
			/*
			 * No more processing is needed, exit here
			 */
		  	exit;
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

			// TODO Display results on the media upload page
			// Provide Picasa URL to open a result page in the browser.
			echo admin_url("upload.php");

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
				
				// Display upload button at the top
				$content .= '<div class="button">';
				$content .= '<input type="submit" value="' . __('Upload', 'picasa-album-uploader') . '">&nbsp;';
				$content .= '</div>';
				
				// Image Size Selector
				// TODO Provide method for admin screen to pick available image sizes
				$content .= '<div class="pau-img-size">';
				$content .= __('Select your upload image size:', 'picasa-album-uploader');
				$content .= '<label><INPUT type="radio" name="size" onclick="chURL(\'640\')">640</label>';
				$content .= '<label><INPUT type="radio" name="size" onclick="chURL(\'1024\')" CHECKED>1024</label>';
				$content .= '<label><INPUT type="radio" name="size" onclick="chURL(\'1600\')">1600</label>';
				$content .= '<label><INPUT type="radio" name="size" onclick="chURL(\'0\')">Original</label>';
				$content .= '</div><!-- End image size selection -->';				

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
				$content .= '</div><!-- End of pau-images -->';
				
				// Display upload button at end of list
				$content .= '<div class="button">';
				$content .= '<input type="submit" value="' . __('Upload', 'picasa-album-uploader') . '">&nbsp;';
				$content .= '</div>';
				
				$content .= '</form>';
			} else {
				$this->pau_options->error_log("Empty RSS feed from Picasa; unable to build minibrowser form.  Dumping POST payload:");
				$this->pau_options->debug_log("_POST: " . print_r($_POST,true));
				
			 	$content .= '<p class="error">' . __('Sorry, no images were received from Picasa.', 'picasa-album-uploader') . '</p>';
			}
			$content .= '</div>';
			
			return $content;
		}
		
		/**
		 * Generate the Picasa PZB file for immediate download.
		 *
		 * See http://code.google.com/apis/picasa/docs/button_api.html for a
		 * description of the contents of the PZB file.
		 *
		 * @access private
		 * @return this function does not return
		 */
		private function send_picasa_button( ) {
			$this->pau_options->debug_log("Sending button file to client");
			
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