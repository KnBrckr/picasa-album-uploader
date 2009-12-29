<?php
/**
 * Class to manage options
 *
 * @package Picasa Album Uploader
 * @author Kenneth J. Brucker <ken@pumastudios.com>
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
		// Default slug name
		$options['slug'] = 'picasa_album';
		
		// Store defaults to WP database
		add_option( 'pau_plugin_settings', $options);
		
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
		return $options['slug'];
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
		register_setting( 'media', 'pau_plugin_settings', array (&$this, 'sanitize_settings') ); // FIXME Add sanitization of slug name - wp_unique_post_slug($slug, $post_ID, $post_status, $post_type, $post_parent)
		
		// FIXME Need an unregister_setting routine for de-install of plugin
	}
	
	/**
	 * Sanitize the Plugin Options
	 *
	 * @return hash Sanitized hash of plugin options
	 **/
	function sanitize_settings($options)
	{
		$options['slug'] = preg_replace("/\s/", "-", $options['slug']);
		$options['slug'] = preg_replace("/[^a-zA-Z0-9-_]/","", $options['slug']);
		
		return $options;
	}
	
	/**
	 * Emit HTML to create a settings section for the plugin in admin screen.
	 **/
	function pau_settings_section_html()
	{
		echo "<p>The following settings configure the Picasa Album Uploader Plugin.</p>";
		echo do_shortcode( "[pau_download_button]" );
	}
	
	/**
	 * Emit HTML to create form field for slug name
	 **/
	function pau_settings_slug_html()
	{
		echo "<input type='text' name='pau_plugin_settings[slug]' value='".$this->slug()."' />";
	}
} // END class 
?>
