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
include_once('dBug.php'); // TODO - remove

// =======================================
// = Define constants used by the plugin =
// =======================================

if ( ! defined( 'PAU_PLUGIN_NAME' ) )
	define( 'PAU_PLUGIN_NAME', 'picasa-album-uploader' );	// Plugin name
if ( ! defined ( 'PAU_PLUGIN_DIR') )
	define( 'PAU_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . PAU_PLUGIN_NAME );	// Base directory for Plugin
	
define ('PAU_BUTTON', 1);
define ('PAU_MINIBROWSER', 2);
define ('PAU_UPLOAD', 3);
	
// ============================
// = Include needed libraries =
// ============================

/* zip.lib.php is copied from phpMyAdmin - great little library for generating zip archives on the fly */
if ( ( include_once 'lib/zip.lib.php') == FALSE ) {
	// TODO - Improve error handling
	echo "unable to load zip lib\n";
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
		
		// ====================
		// = XML RPC Handling =
		// ====================
		
		// TODO - Might not be needed
		// // Add the XML-RPC methods needed for the plugin
		// function attach_new_xmlrpc( $methods ) {
		// 	$methods['pau.picasa_button'] = array( &$this, 'send_picasa_button' );
		// 	return $methods;
		// }
		
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
		
		/*
			function send_picasa_buttion()
			
			Generate the Picasa PZB file and emit for web browser to download
			
			Assumes that no other data, including headers, has been sent to browser.
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
		   <param name="url" value="$upload_url"/>
		</action>
	</button>
</buttons>
EOF;
			
			// Create Zip stream and add the XML data to the zip
			$zip = new zipfile();
			$zip->addFile( $pbf, $guid . '.pbf' );
			
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
		
		//FIXME
		function minibrowser() {
			
			// FIXME Add security check
			
// Add jQuery?
// <script type="text/javascript">
//     function chURL(psize){
//         $("input[type='hidden']").each(function()
//         {
//             this.name = this.name.replace(/size=.*/,"size="+psize);
//         });
//     }
// </script>

			echo <<<'HEAD'
<html>
<head>
<link rel="STYLESHEET" type="text/css" href="style.css">
</head>
HEAD;
			
			new dBug($_SESSION);
			
			$url = get_bloginfo('wpurl') . '/' . $this->slug . '/upload';
			echo "
<form name='f' method='post' action='$url'>
<div class='h'>Selected images</div>
<div>";

			// Get Posted photos
			if($_SESSION['POST']['rss']) {
				$xh = new xmlHandler();
				$nodeNames = array("PHOTO:THUMBNAIL", "PHOTO:IMGSRC", "TITLE");
				$xh->setElementNames($nodeNames);
				$xh->setStartTag("ITEM");
				$xh->setVarsDefault();
				$xh->setXmlParser();
				$xh->setXmlData(stripslashes($_SESSION['POST']['rss']));
				$pData = $xh->xmlParse();
				$br = 0;

				foreach($pData as $e)
					echo "<img src='".$e['photo:thumbnail']."?size=-96'>\r\n";

				foreach($pData as $e) {
					$large = $e['photo:imgsrc']."?size=1024";
          echo "<input type=hidden name='".$large."'>\r\n";
				}

				echo <<<FORM_FIN
</div>

<div class='h'>
<input type=submit value="Upload">&nbsp;
</div>
<div class='h'>Select your upload image size
<INPUT type=radio name=size onclick="chURL('640')">640
<INPUT type=radio name=size onclick="chURL('1024')" CHECKED>1024
<INPUT type=radio name=size onclick="chURL('1600')">1600
<INPUT type=radio name=size onclick="chURL('0')">Original
</div>
<input type=button value="Discard" onclick="location.href='minibrowser:close'"><br />
FORM_FIN;
				
			} else {
				echo <<< FORM_FIN
Sorry, but no pictures were received.<br />
<input type=button value="Close" onclick="location.href='minibrowser:close'"><br />
FORM_FIN;
			}

			echo <<<FOOT

</form>
</body>
</html>
FOOT;

		exit; // Finished displaying the minibrowser page
		}
		
		//FIXME
		function upload_images() {
			/*
			<?php
			require_once('admin.php');
			if (!current_user_can('upload_files'))
			    wp_die(__('You do not have permission to upload files.'));
			if($_FILES) {
			    $_POST['action'] = "wp_handle_upload";
			    foreach($_FILES as $key => $file) {
			        if (!empty($file)) {
			            $overrides = array('test_form' => false);
			            $status = wp_handle_upload($file,$overrides);
			            unset($file);
			            if (isset($status['error']) ){
			                continue;
			            }else{
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
			            }
			        }
			    }
			}

			?>
			
			*/
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
			Returns:  UUID string in form: 
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

// =========================
// = Plugin initialization =
// =========================

global $pau;
if ( class_exists( 'picasa_album_uploader' ) ) {
	$pau = new picasa_album_uploader();
}
?>