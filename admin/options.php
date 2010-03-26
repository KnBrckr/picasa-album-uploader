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
	 * Class Constructor function
	 *
	 * Setup plugin defaults and register with WordPress for use in Admin screens
	 **/
	function picasa_album_uploader_options()
	{
		// If admin screens in use, enable settings fields for manipulation
		if ( is_admin() ) {			
			add_action( 'admin_init', array( &$this, 'pau_settings_admin_init' ) );
		}
	}
	
	/**
	 * slug used to detect pages requiring plugin processing
	 *
	 * @return string Slug Name Setting
	 * @access public
	 **/
	function slug() {
		$options = get_option('pau_plugin_settings');
		return ($options['slug'] ? $options['slug'] : 'picasa_album_uploader');
	}
	
	/**
	 * Return relative path to button file
	 *
	 * @return string Relative path within WP_CONTENT_DIR to use for button file
	 **/
	function button_file()
	{
		$options = get_option('pau_plugin_settings');
		return ($options['button_file'] ? $options['button_file'] : '');
	}
	
	/**
	 * Return full path to button file.
	 *
	 * @return string Path within WP_CONTENT_DIR to use for button file
	 **/
	function button_file_path()
	{
		$button_file = self::button_file();
		return WP_CONTENT_DIR . '/' . ($button_file ? $button_file . '/' : '') . 'picasa_album_uploader.pbz';
	}
	
	/**
	 * Return URL path to button file
	 *
	 * @return string URL path to button file
	 **/
	function button_file_url()
	{
		$button_file = self::button_file();
		return WP_CONTENT_URL . '/' . ($button_file ? $button_file . '/' : '') . 'picasa_album_uploader.pbz';
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
		add_settings_field( 'pau_plugin_settings[button_file]', 'Button File', array( &$this, 'pau_settings_button_file_html' ), 'media', 'pau_settings_section' );
		
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
		
		// button file path can not contain .. references
		$button_file_pattern[0] = '/^\.\.\//';	// Not allowed to start with ../
		$button_file_replacement[0] = '';
		$button_file_pattern[1] = '/\/\.\.\/';	// No intermediate ..
		$button_file_replacement[1] = '/';
		$button_file_pattern[2] = '/^\/|\/$/';	// Trim Leading and Trailing slashes
		$button_file_replacement[2] = '';
		$options['button_file'] = preg_replace($button_file_pattern, $button_file_replacement, $options['button_file']);
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
		<p>In the event the automated install does not work, you can also try to manually install the plugin.</p>
		<?php
		// FIXME Provide instructions on manual install
	}
	
	/**
	 * Emit HTML to create form field for slug name
	 **/
	function pau_settings_slug_html()
	{ ?>
		<input type='text' name='pau_plugin_settings[slug]' value='<?php echo self::slug(); ?>' /><br />
		Set the slug used by the plugin.  
		Only alphanumeric, dash (-) and underscore (_) characters are allowed.
		White space will be converted to dash, illegal characters will be removed.
		<br />When the slug name is changed or permalink settings are altered, 
		a new button must be installed in Picasa to match the new setting.
		<?php
	}
	
	/**
	 * Emit HTML to create form field for button file name
	 **/
	function pau_settings_button_file_html()
	{ ?>
		<input type='text' name='pau_plugin_settings[button_file]' value='<?php echo self::button_file(); ?>' /><br />
		Set the path to be used for the button file that is downloaded into Picasa.  
		The path is relative to the wp_content directory and the path must be writable for the file to be generated.
		By default, the button file will be generated in the wp_content directory.
		<br />The file must be re-generated if
		the slug name is changed or the site permalink settings are altered.
		<?php
		// Put up warning if the directory is not writable
		if (! is_writable(dirname(self::button_file_path())) ) {
			echo "<p>FIXME WARNING - Directory '" . dirname(self::button_file_path()) . "' not writeable</p>";
		}
	}
} // END class 
?>
