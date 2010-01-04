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
	 * @returns string Slug Name Setting
	 **/
	function slug() {
		$options = get_option('pau_plugin_settings');
		$slug = ($options['slug'] ? $options['slug']: 'picasa_album_uploader');
		return $slug;
	}
	
	/**
	 * Register the plugin settings options when running admin_screen
	 **/
	function pau_settings_admin_init ()
	{
		// Add settings section to the 'media' Settings page
		add_settings_section( 'pau_settings_section', 'Picasa Album Uploader Settings', array( &$this, 'pau_settings_section_html'), 'media' );
		
		// Add slug name field to the plugin admin settings section
		add_settings_field( 'pau_plugin_settings[slug]', 'Slug', array( &$this, 'pau_settings_slug_html' ), 'media', 'pau_settings_section' );
		
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
		$pattern[0] = '/\s+/'; // Translate white space to a -
		$pattern[1] = '/[^a-zA-Z0-9-_]/'; // Only allow alphanumeric, dash (-) and underscore (_)
		$replacement[0] = '-';
		$replacement[1] = '';
		$options['slug'] = preg_replace($pattern, $replacement, $options['slug']);
		
		return $options;
	}
	
	/**
	 * Emit HTML to create a settings section for the plugin in admin screen.
	 **/
	function pau_settings_section_html()
	{	
		// Display button to download the Picasa Button Plugin
		echo do_shortcode( "[picasa_album_uploader_button]" );
		
		?>
		<p>To use the Picasa Album Uploader, install the Button in Picasa Desktop using the link above.</p>
		<?php
	}
	
	/**
	 * Emit HTML to create form field for slug name
	 **/
	function pau_settings_slug_html()
	{ ?>
		<input type='text' name='pau_plugin_settings[slug]' value='<?php echo $this->slug(); ?>' /><br />
		Set the slug used by the plugin.  
		Only alphanumeric, dash (-) and underscore (_) characters are allowed.
		White space will be convereted to dash, illegal characters will be removed.
		<br />When the slug name is changed, a new button must be installed in Picasa to match the new setting.
		<?php
	}
} // END class 
?>
