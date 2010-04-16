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

		// When displaying admin screens ...
		if ( is_admin() ) {
			add_action( 'admin_init', array( &$this, 'pau_settings_admin_init' ) );

			// Add section for reporting configuration errors
			add_action('admin_footer', array( &$this, 'pau_admin_notice'));
		}
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
	 * Display Notice messages at head of admin screen
	 *
	 * @return void
	 **/
	function pau_admin_notice()
	{
		
 		if ( get_option('permalink_structure') != '' )
			return;

		echo "<div class='error'><p><a href='options-media.php'>" . PAU_PLUGIN_NAME
			. "</a> requires the use of <a href='options-permalink.php'>Permalinks</a></p></div>";
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
} // END class 
?>
