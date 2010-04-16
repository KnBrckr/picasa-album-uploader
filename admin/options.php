<?php
/**
 * Class to manage options
 *
 * @package Picasa Album Uploader
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2010 Kenneth J. Brucker (email: ken@pumastudios.com)
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
class picasa_album_uploader_options
{
	/**
	 * slug used to detect pages requiring plugin action
	 *
	 * @var string slug name
	 * @access public
	 **/
	public $slug;
	
	/**
	 * Relative directory path to button file based in the WP_CONTENT directory
	 *
	 * Value set via plugin options.
	 *
	 * @var string directory path or empty string
	 * @access private
	 **/
	var $button_file_rel_dirname;
	
	/**
	 * Full path to button file
	 *
	 * @var string Full path to the button file
	 * @access public
	 **/
	public $button_file_path;
	
	/**
	 * URL path to button file
	 *
	 * @var string URL path to button file
	 * @access public
	 **/
	public $button_file_url;
	
	/**
	 * When errors are detected in the module, this variable will contain a text description
	 *
	 * @var string Error Message
	 * @access public
	 **/
	public $error;
	
	/**
	 * Class Constructor function
	 *
	 * Setup plugin defaults and register with WordPress for use in Admin screens
	 **/
	function picasa_album_uploader_options()
	{
		// Retrieve Plugin Options
		$options = get_option('pau_plugin_settings');
		
		// Init value for slug name - supply default if undefined
		$this->slug = $options['slug'] ? $options['slug'] : 'picasa_album_uploader';

		// Init paths to the button file
		$button_file_name = 'picasa_album_uploader.pbz';
		$this->button_file_rel_dirname = $options['button_file_rel_dirname'] || '';
		$relpath = $this->button_file_rel_dirname ? $this->button_file_rel_dirname . '/' . $button_file_name : $button_file_name;		
		$this->button_file_path = WP_CONTENT_DIR . '/' . $relpath;
		$this->button_file_url = WP_CONTENT_DIR . '/' . $relpath;
		
		// If the button file is not present, flag the plugin as needing attention
		if ( ! is_readable($this->button_file_path) ) {
			$this->error = "Picasa Album Uploader Plugin:  No button file found for downloading to Picasa.  It must be re-generated";
		}

		// When displaying admin screens ...
		if ( is_admin() ) {
			add_action( 'admin_init', array( &$this, 'pau_settings_admin_init' ) );
		}
	}
		
	/**
	 * Register the plugin settings options when running admin_screen
	 **/
	function pau_settings_admin_init ()
	{
		// Add settings section to the 'media' Settings page
		add_settings_section( 'pau_settings_section', 'Picasa Album Uploader Settings', array( &$this, 'pau_settings_section_html'), 'media' );
		
		// FIXME Add button to generate Picasa button.
		// FIXME Add warnings if the slug or permalink structure modified that button must be regenerated.
		
		// Add slug name field to the plugin admin settings section
		add_settings_field( 'pau_plugin_settings[slug]', 'Slug', array( &$this, 'pau_settings_slug_html' ), 'media', 'pau_settings_section' );
		
		// Add button file name field
		add_settings_field( 'pau_plugin_settings[button_file]', 'Button File Path', array( &$this, 'pau_settings_button_file_html' ), 'media', 'pau_settings_section' );

		// Register the slug name setting;
		register_setting( 'media', 'pau_plugin_settings', array (&$this, 'sanitize_settings') );
		
		// TODO Need an unregister_setting routine for de-install of plugin
	}
	
	/**
	 * Sanitize the Plugin Options received from the user
	 *
	 * @return hash Sanitized hash of plugin options
	 **/
	function sanitize_settings($options)
	{
		// Slug must be alpha-numeric, dash and underscore.
		$slug_pattern[0] = '/\s+/'; 						// Translate white space to a -
		$slug_replacement[0] = '-';
		$slug_pattern[1] = '/[^a-zA-Z0-9-_]/'; 	// Only allow alphanumeric, dash (-) and underscore (_)
		$slug_replacement[1] = '';
		$options['slug'] = preg_replace($slug_pattern, $slug_replacement, $options['slug']);
		
		// button file relative directory path can not contain .. references
		$button_file_pattern[0] = '/^\.\.\//';	// Not allowed to start with ../
		$button_file_replacement[0] = '';
		$button_file_pattern[1] = '/\/\.\.\/';	// No intermediate ..
		$button_file_replacement[1] = '/';
		$button_file_pattern[2] = '/^\/|\/$/';	// Trim Leading and Trailing slashes
		$button_file_replacement[2] = '';
		$options['button_file_rel_dirname'] = preg_replace($button_file_pattern, $button_file_replacement, $options['button_file']);
		
		// If User wanted the button file generated act on it here.
		if ( $options['generate_button'] ) {
			$result = self::generate_picasa_button();
			error_log($result);
		}
		return $options;
	}
	
	/**
	 * Emit HTML to create a settings section for the plugin in admin screen.
	 **/
	function pau_settings_section_html()
	{	
		?>
		<p>To use the Picasa Album Uploader, install the Button in Picasa Desktop using this automated install link:</p>
		<?php
		// Display button to download the Picasa Button Plugin
		echo do_shortcode( "[picasa_album_uploader_button]" );
		?>
		<?php
		// FIXME Provide instructions on manual install
	}
	
	/**
	 * Emit HTML to create form field for slug name
	 **/
	function pau_settings_slug_html()
	{ ?>
		<input type='text' name='pau_plugin_settings[slug]' value='<?php echo $this->slug; ?>' />
		<p>
			Set the slug used by the plugin.  
			Only alphanumeric, dash (-) and underscore (_) characters are allowed.
			White space will be converted to dash, illegal characters will be removed.
			<br />When the slug name is changed or permalink settings are altered, 
			a new button must be installed in Picasa to match the new setting.
		</p>
		<?php
	}
	
	/**
	 * Emit HTML to create form field for button file name
	 **/
	function pau_settings_button_file_html()
	{ ?>
		<input type='text' name='pau_plugin_settings[button_file_rel_dirname]' value='<?php echo $this->button_file_rel_dirname; ?>' />
		<input type="checkbox" name="pau_plugin_settings[generate_button]" value="1" > Generate the Button File for download into Picasa Desktop.
		<?php
		if (! is_writable(dirname($this->button_file_path)) ) {
			// Put up warning if the directory is not writable
			echo "<p class='attention'>WARNING: The directory '" . dirname($this->button_file_path) . "' is not writeable; the button file can not be generated.</p>";
		}
		?>
		<p>
			Set the path to be used for the button file that is downloaded into Picasa.  
			The path is relative to the wp_content directory and the path must be writable for the file to be generated.
			By default, the button file will be generated in the wp_content directory.
			<br />The file must be re-generated if
			the slug name is changed or the site permalink settings are altered.
		</p>
		<?php

		// FIXME Detect if the Permalink or slug settings have changed and the button needs to be regenerated.
		// Alternative is to always use the non-permalink settings which will always work.  Only change is if the
		// URL to the site changes.
		
	}
	
	/**
	 * Generate the Picasa PZB file and save as a media file for later download.
	 *
	 * See http://code.google.com/apis/picasa/docs/button_api.html for a
	 * description of the contents of the PZB file.
	 *
	 * @access private
	 */
	private function generate_picasa_button( ) {
		global $pau;
		
		$blogname = get_bloginfo( 'name' );
		$guid = self::guid(); // TODO Only Generate GUID once for a blog - keep same guid - allow blog config to update it.
		$upload_url = $pau->build_url('minibrowser');

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
			return "Unable to initialize zipfile module.";
		}
		$zip->addFile( $pbf, $guid . '.pbf' );

		// TODO Allow icon to be replaced by theme
		// Add PSD icon to zip
		$psd_filename =  PAU_PLUGIN_DIR . '/images/wordpress-logo-blue.psd'; // button icon
		$fsize = @filesize( $psd_filename );
		if (false == $fsize) {
			return "Unable to get filesize of " . $psd_filename;
		}
		$zip->addFile( file_get_contents( $psd_filename ), $guid . '.psd' );

		// Copy Zip file into media area for later download
		$button_file = $this->button_file_path;
		// FIXME - Create file w/in system somewhere
		$retval = file_put_contents($button_file, $zip->file());
		if ( 0 == $retval ) {
			return "Failed to write contents of " . $button_file;
		}

		return "Generated Button File";
	}
	
	/**
	 * Generate a standard format guid
	 *
	 * @return string UUID in form: {xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx}
	 * @access private
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
} // END class 
?>
