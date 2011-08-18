<?php
/**
 * picasa_album_uploader_options class to manage options
 *
 * @package Picasa Album Uploader
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011 Kenneth J. Brucker (email: ken@pumastudios.com)
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

$pau_versions[] = '$Id: options.php 425381 2011-08-18 13:46:51Z draca $'; // SVN Version string

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
	 * Long variable name used in self_test
	 */
	public $long_var_name =   "this_is_a_long_variable_name_to_mimic_picasa_upload_operation_3456789012345678901234567890123456789";

	/**
	 * Class Constructor function
	 *
	 * Setup plugin defaults
	 *
	 * @access public
	 * @return void
	 **/
	function picasa_album_uploader_options()
	{
		// Retrieve Plugin Options
		$options = get_option('pau_plugin_settings');
		
		// TODO Improve handling of default settings
		// Init value for slug name - supply default if undefined
		$this->slug = isset($options['slug']) ? $options['slug'] : 'picasa_album_uploader';
		
		// Init value for error log
		$this->debug_log_enabled = isset($options['debug_log_enabled']) ? $options['debug_log_enabled'] : 0;
		$this->debug_log = isset($options['debug_log']) ? $options['debug_log'] : array();
	}
	
	/**
	 * Cleanup database if uninstall is requested
	 *
	 * @access public
	 * @return void
	 **/
	function uninstall() {
		delete_option('pau_plugin_settings'); // Remove the plugin settings		
	}
	
	/**
	 * Register plugin actions with WordPress
	 *
	 * @access public
	 * @return void
	 **/
	function register()
	{
		// When displaying admin screens ...
		if ( is_admin() ) {
			add_action( 'admin_init', array( &$this, 'pau_settings_admin_init' ) );
			
			// Add section for reporting configuration errors
			add_action('admin_footer', array( &$this, 'pau_admin_notice'));			
		}

		// If logging is enabled, setup save in the footers.
		if ($this->debug_log_enabled) {
			add_action('admin_footer', array( &$this, 'save_debug_log'));
			add_action('wp_footer', array( &$this, 'save_debug_log'));				
		}
	}
		
	/**
	 * WP action to register the plugin settings options when running admin_screen
	 *
	 * @access public
	 * @return void
	 **/
	function pau_settings_admin_init ()
	{
		// Add settings section to the 'media' Settings page
		add_settings_section( 
				'pau_settings_section', 
				'Picasa Album Uploader Settings', 
				array( &$this, 'settings_section_html'), 
				'media' );
		
		// Add slug name field to the plugin admin settings section
		add_settings_field( 
				'pau_plugin_settings[slug]', 
				'Slug', 
				array( &$this, 'slug_html' ), 
				'media', 
				'pau_settings_section' );
		
		// Add Plugin Error Logging
		add_settings_field( 
				'pau_plugin_settings[debug_log_enabled]', 
				'Enable Debug Log', 
				array( &$this, 'debug_log_enabled_html'), 
				'media', 
				'pau_settings_section' );
		add_settings_field(
				'pau_plugin_settings[debug_log]',
				array( &$this, 'debug_log_html'),
				'media',
				'pau_settings_section' );
		
		// Register the slug name setting;
		register_setting( 'media', 'pau_plugin_settings', array (&$this, 'sanitize_settings') );
	}
	
	/**
	 * WP action to emit Admin notice messages with class "error" for display on WP Admin pages
	 *
	 * @access public
	 * @return void
	 **/
	function pau_admin_notice()
	{
		if ( get_option('permalink_structure') == '' ) {
			echo '<div class="error"><p>';
			printf(__('%1$s requires the use of %2$s', 'picasa-album-uploader'), '<a href="options-media.php">' . PAU_PLUGIN_NAME . '</a>', '<a href="options-permalink.php">Permalinks</a>');
			echo '</p></div>';
		}
		
		if ( $this->debug_log_enabled ) {
			echo '<div class="error"><p>';
			printf(__('%s logging is enabled.  If left enabled, this can affect database performance.', 'picasa-album-uploader'),'<a href="options-media.php">' . PAU_PLUGIN_NAME . '</a>');
			echo '</p></div>';
		}		
	}
	
	/**
	 * WP callback function to sanitize the Plugin Options received from the user
	 *
	 * @access public
	 * @param hash $options Options defined by plugin indexed by option name
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
		
		// Cleanup error log if it's disabled
		if ( ! isset($options['debug_log_enabled']) || ! $options['debug_log_enabled'] ) {
			$options['debug_log'] = array();
		}

		return $options;
	}
	
	/**
	 * WP options screen callback to emit HTML to create a settings section for the plugin in admin screen.
	 *
	 * @access public
	 * @return void
	 **/
	function settings_section_html()
	{	
		// Permalinks must be enabled ...
		if ( get_option('permalink_structure') != '' ) {
			echo '<p>';
			_e('To use Picasa Album Uploader, install the Button in Picasa Desktop using this automated install link:', 'picasa-album-uploader');
			echo '</p>';
			// Display button to download the Picasa Button Plugin
			echo do_shortcode( "[picasa_album_uploader_button]" );
		} else {
			echo '<p>';
			_e('To use Picasa Album Uploader, Permalinks must be enabled due to limitations in the Desktop Picasa application.');
			echo '</p>';
		}
	}
	
	/**
	 * WP options screen callback to emit HTML to create form field for slug name
	 *
	 * @access public
	 * @return void
	 **/
	function slug_html()
	{ 
		echo '<input type="text" name="pau_plugin_settings[slug]" value="' . $this->slug . '" />';
		echo '<p>';
		_e('Set the slug used by the plugin.  Only alphanumeric, dash (-) and underscore (_) characters are allowed.  White space will be converted to dash, illegal characters will be removed.', 'picasa-album-uploader');
		echo '<br />';
		_e('When the slug name is changed, a new button must be installed in Picasa to match the new setting.', 'picasa-album-uploader');
		echo '</p>';
	}
	
	/**
	 * Perform self test operations and emit reporting HTML
	 *   Attempt to load page using long GET request
	 *
	 * @access private
	 * @return string HTML report of self test results
	 **/
	private function selftest()
	{
		$text = '';
		
		// Run long REQUEST Variable name test
		if ($result = $this->test_long_var()) {
			$text .= 'Long Request Variable Test Failed: ' . $result . '<br>';
		} else {
			$text .= 'REQUEST long variable OK<br>';
		}
		
		return $text;
	}
	
	/**
	 * Perform HTTP request to self test page including a long request variable name
	 * This test confirms that the WP install is capable of receiving the long argument names that are sent by Picasa.
	 *
	 * @access private
	 * @return false if able to retrieve long variable name, string describing error otherwise
	 **/
	private function test_long_var()
	{
		if (!ini_get('allow_url_fopen')) {
			$result = 'Unable to complete HTTP REQUEST test; allow_url_fopen in php.ini is false.';
		} else {
			$baseurl = home_url() . '/' . $this->slug . '/selftest';
			$url = $baseurl . '?' . $this->long_var_name . '=' . $this->long_var_name;
			$contents = file_get_contents($url);
			if ($contents) {
				$result = $false;
			} else {
				$status = preg_grep("/^HTTP\/1.[01] [^3]/", $http_response_header);
				if (count($status) > 0) {
					$result = implode($status);					
				} else {
					$result = "Unrecognized failure opening $baseurl";
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * WP options callback to emit HTML to create form field used to enable/disable Debug Logging
	 *
	 * @access public
	 * @return void
	 **/
	function debug_log_enabled_html()
	{ 
		$checked = $this->debug_log_enabled ? "checked" : "" ;
		echo '<input type="checkbox" name="pau_plugin_settings[debug_log_enabled]" value="1" ' . $checked . '>';
		_e('Enable Plugin Debug Logging. When enabled, log will display below.', 'picasa-album-uploader');
		if ( $this-> debug_log_enabled ) {
			echo $this->debug_report();
		}
	}
	
	/**
	 * Generate data for debug and bug reporting
	 *
	 * @access private
	 * @return string HTML to display debug messages
	 */
	private function debug_report()
	{
		global $pau_versions;
		
		$content = '<dl class=pau-debug-log>';
		
		$content .= '<dt>Plugin Versions: ';
		foreach ($pau_versions as $line) {
			$content .= '<dd>' . esc_attr($line);
		}
		// Add some environment data
		$content .= '<dt>PHP Version:<dd>' . phpversion();
		$content .= '<dt>MySQL Server Version:<dd>' . mysql_get_server_info();
		
		$content .= '<dt>Plugin Slug: <dd>' . $this->slug;
		$content .= '<dt>Permalink Structure: <dd>' . get_option('permalink_structure');
		$content .= '<dt>Button HTML: <dd>' . esc_attr( do_shortcode( '[picasa_album_uploader_button]' ) );
		$content .= '<dt>Self Test: <dd>' . self::selftest();
		$content .= '<dt>Log:';
		foreach ($this->debug_log as $line) {
			$content .= '<dd>' . esc_attr($line);
		}
		$content .= '</dl>';
		
		return $content;
	}
	
	/**
	 * Log a debug message
	 *
	 * @access public
	 * @return void
	 **/
	function debug_log($msg)
	{
		if ( $this->debug_log_enabled )
			array_push($this->debug_log, date("Y-m-d H:i:s") . " " . $msg);
	}
	
	/**
	 * Save the error log if it's enabled.  Must be called before server code exits to preserve
	 * any log messages recorded during session.
	 *
	 * @access public
	 * @return void
	 **/
	function save_debug_log()
	{
		if ( $this->debug_log_enabled ) {
			$options = get_option('pau_plugin_settings');
			$options['debug_log'] = $this->debug_log;
			update_option('pau_plugin_settings', $options);
		}
	}
	
	/**
	 * Log errors to server log and debug log
	 *
	 * @access public
	 * @return void
	 **/
	function error_log($msg)
	{
		error_log(PAU_PLUGIN_NAME . ": " . $msg);
		$this->debug_log($msg);
	}
} // END class 
?>
