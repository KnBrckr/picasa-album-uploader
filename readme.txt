=== Picasa Album Uploader ===
Contributors: draca
Donate link: http://pumastudios.com/software/picasa-album-uploader-wordpress-plugin
Tags: picasa, upload, images, albums, media
Requires at least: 3.0
Tested up to: 3.0.4
Stable tag: 0.5

Easily upload media from Google Picasa Desktop into WordPress.

== Description ==

Provides a button to be installed into the Google Picasa Desktop to directly upload files from Picasa as WordPress media.  Once the button has been downloaded and installed in Picasa, images can be selected in Picasa and uploaded to your WordPress blog with a simple click of the button within Picasa.

If you are not logged in to your blog, you will first be directed to the login page and then return to the upload screen to select the upload options.

This plugin is based on the initial works by [clyang](http://clyang.net/blog/2009/02/06/128 "Picasa2Wordpress Blog Article") and the examples from Google for the [Picasa Button API](http://code.google.com/apis/picasa/docs/button_api.html "Picasa Button API") and [Picasa Web Uploader API](http://code.google.com/apis/picasa/docs/web_uploader.html "Picasa Web Uploader API").

The Picasa API this plugin is based upon has been deprecated by Google.

= What's Next? =

1.  Provide uninstall method
1.  At upload, optionally create a new post using the WP shortcode [gallery] to publish the newly uploaded files.
1.  Refine default display of images to be uploaded.
1.  Improve default formatting in Picasa Mini-browser

== Installation ==

1.  Configure one of the permlink options in the Admin Settings -> Permalinks screen.  See FAQ for details.
1.  Upload the picasa-album-uploader to the `wp-content/plugins/` directory
1.  Activate the plugin in Admin -> Plugins
1.  Configure the plugin options in Admin -> Settings -> Media
1.  Use the "Install Image Upload Button in Picasa Desktop" Link in Admin -> Settings -> Media to import the upload button into Picasa
1.  If desired, create the files header-picasa_album_uploader.php and footer-picasa_album_uploader.php in the top level of your themes directory to provide customized header and footer in the upload confirmation dialog displayed by Picasa.
1.  Begin uploading photos from Picasa to your blog.

= Usage Hints =

To display the button load link in a post or page, insert the shortcode `[picasa_album_uploader_button]` at the desired location.

A log of plugin activity useful to debug failures can be obtained by selecting the plugin option 'Enable Debug Log' and saving the configuration change.  The logging might impact performance of your website so should only be enabled when debugging is required.

== Frequently Asked Questions ==

= Why are Permalinks required? =

The Picasa Desktop client is very picky about the format of the URLs that it will accept during the upload process and will only accept a simple URL consisting of a single filename.  The use of the slash (/) and question-marks (?) in the URL syntax results in no files being uploaded by Picasa to the server.  In order to satisfy this requirement, permalinks must be used.

= I changed the slug name (or other part of my WordPress URL) and my button in Picasa stopped working.  What do I do? =

The Picasa button contains a URL to your WordPress installation, including the slug name used by this plugin.  If any portion of the URL changes, the button in Picasa must be replaced so that the button is sending to the correct location in your WordPress site.

= What happens if I change my permalink settings? =

You may freely change the format of your permalinks without affecting the ability to upload files from Picasa Desktop.  You must however keep permalinks enabled as discussed above.

= The URL to download the button points to a .pbz file that does not exist in the installed material.  What's up? =

The .pbz file is dynamically created by the plugin due to the customization required in the contents.  See the question below about the contents of the download for details.

= What's in that download that needs to be installed into Picasa? =

There is no executable code in the download.  It is comprised of two elements:

* An XML file describing the button to Picasa Desktop.  This includes a URL to your blog for Picasa to use when the button is clicked.
* A file containing the button graphic to display within the Picasa Desktop.

= Can I make the link to install the button in Picasa Desktop available in Pages and Posts? =

Yes!  Just put the shortcode `[picasa_album_uploader_button]` where you want the button to display.

= Can I have buttons from multiple WordPress sites installed in Picasa at the same time? =

Yes!  The tool tip for the button will identify the name of the WordPress site associated with the button.  You might want to change the button icon to graphically differentiate the connected WordPress installs.  I have no experience with WPMU so can't comment on how this will function within that environment.

= How do I change the button icon? =

In the future, a theme will be allowed to override the button graphic.  Right now, the only way to change the button is by replacing the file `picasa-album-uploader/images/wordpress-logo-blue.psd` in the plugin directory with the desired content.  This is a photoshop file and a compatible image editor must be used.  The layer containing the button image must be named "upload-button".  The image should be no larger than 40 pixels wide by 25 pixels high with 72 dpi resolution.  The color model used must be RGB with 8 bits/channel and should use a transparent background.  Full details can be found at the [Picasa Button API](http://code.google.com/apis/picasa/docs/button_api.html "Picasa Button API") reference.

= Other Picasa Uploader plugins require files be placed in the `wp-admin` and/or the server root.  Does this plugin require the same? =

This is a real plugin that lives in the `wp-content/plugins/` directory and does not require special files to be placed in either your server root or in the `wp-admin/` directory.  Further, the plugin supports themes to customize the appearance of the upload dialog displayed by Picasa.

= How do I uninstall this plugin? =

1. Deactivate the plugin from the Admin -> Plugins Screen
1. Delete the directory `wp-content/plugins/picasa-album-uploader` in your WordPress installation
1. The plugin adds a single DB entry to the WordPress options table called "pau_plugin_settings".  Using phpMyAdmin or similar utility remove this entry from the table.

In the future, an uninstall script will be provided to delete the options entry from the options table.

= How do I remove the button from Picasa? =

1. In Picasa Select "Tools -> Configure Buttons..."
1. In the "Current Buttons" section of the Picasa Dialog, select the "WordPress" button.
1. Click the "Remove" button.
1. To completely remove the button from Picasa, remove the associated `picasa_album_uploader.pbz` file from the Picasa configuration.

= Where are the Picasa buttons stored on my computer? =

Button files end with `.pbz` and the location depends on the OS you are using:

Windows:  C:\Program Files\Google\Picasa3\buttons
XP:  C:\Documents and Settings\Username\Local Settings\Application Data\Google\Picasa3\buttons
Vista:  C:\Users\Username\AppData\Local\Google\Picasa3\buttons
OSX: ~/Library/Application Support/Google/Picasa3/buttons

== Troubleshooting section ==
= When I click the "install" button, my browser says it does not recognize the protocol. =

This message means that Picasa has not registered itself with your browser as being the application to handle links starting with `picasa://`. You could try to reinstall Picasa, which should cause it to register itself with your browser.  You must also be using at least Picasa v3.0.

= When I click the "install" button, Picasa does not launch. =

Make sure you are running at least Picasa version 3.0 and that Picasa can open on your computer.

= Everything seems OK, but I get an error page displayed by the browser that no files were uploaded by Picasa =

Review the server error log for possible clues.  One possible cause of this failure is a server that is configured to use the [Suhosin](http://www.hardened-php.net/suhosin/index.html "Suhosin Hardened PHP") PHP plugin.  Special thanks go to [rbredow](http://wordpress.org/support/profile/rbredow "rbredow Wordpress User Profile") for the assistance in diagnosing this interaction.

If Suhosin is configured, you might see an error like the following in the server error log:

`ALERT - configured request variable name length limit exceeded - dropped variable 'http://localhost:51134/b921a58ec2806ab82f5399515fba226e/image/b0f008e85a4fa153_jpg?size=1024'`.

Check the Suhosin setting values for `suhosin.post.max_name_length` and `suhosin.request.max_varname_length`.  A setting of at least 100 is recommended to allow the long variable names that are required by the Picasa engine.  You might need to increase it further depending on the length of the dropped variable name observed in the error log.

The Apache plugin mod_security can also be configured to restrict the length of the request variable name.  Again, a setting of at least 100 is recommended to allow long variables.

= Reporting Problems =

Please follow these instructions to report problems:

1. Enable debug logging in Admin -> Settings -> Media
1. Reproduce the problem
1. Provide the log results, description of problem, plugin version and WordPress version in a post to the [Support Forum](http://wordpress.org/tags/picasa-album-uploader?forum_id=10 "Picasa Album Uploader Support Forum").

== Screenshots ==

1. Picasa Album Uploader Options in Media Settings Admin Screen.

== Changelog ==

= Trunk =

* Improved message when no files to be uploaded. 

= 0.5 =

* Add i18n support
* Enhanced result page reporting
* Fixed defect in reporting of errors detected during upload resulting in silent failure.
* Documented plugin interaction with PHP Security plugin Suhosin

= 0.4.1 =

* Modified class names used by plugin and improved default formatting on plugin displayed pages
* Additional debug logging

= 0.4 =

* Address issues when permalinks are not being used on a site.  Picasa Desktop is challenged if permalinks are not enabled when processing URLs.
* Added debug logging in plugin to aid in diagnosis of reported problems

= 0.3.1 =

* Fix defect in redirect URL to display results page
* Fix interaction issue with WordPress.com Stats plugin

= 0.3 =

* Created Admin Settings Section on the Media page.
* Redirect immediately to login screen if user not logged in
* Add fields to upload screen to set Title, Caption and Description for uploaded file(s).
* Initial CSS Formatting of upload screen

== Upgrade Notice ==

= 0.5 =

* Improved results and error reporting
* Plugin internationalization.
* Fixed defect in reporting errors detected during upload resulting in silent failure

= 0.4 =

* Address issues when permalinks are not being used on a site.  Picasa Desktop is challenged if permalinks are not enabled when processing URLs.
* Improved debugging and error messages to isolate plugin problems detected by users

= 0.3.1 =

* Fixes interaction issue with WordPress.com Stats plugin - When both plugins are enabled, navigation to the picasa uploader pages will cause an execution timeout in the Stats plugin.

= 0.3 =

* The first Beta Release!

== Theme Formatting ==

When formatting the upload confirmation dialog displayed by Picasa, it is best to avoid links that will navigate away from the upload confirmation screen.  The plugin will handle redirecting to the WordPress login screen to validate the user as necessary.

There are two ways for a theme to control the output of the upload dialog displayed by Picasa Desktop.

1.  The variable `$wp_query-> is_picasa_album_slug` will be set if the page is being handled by the plugin.
2.  Three templates files can be used to configure the page:  `page-picasa_album_uploader.php`, `header-picasa_album_uploader.php`, `footer-picasa_album_uploader.php`

* `page-picasa_album_uploader.php` –
The file `picasa_album_uploader/templates/page-picasa_album_uploader.php`, supplied by the plugin, is the default page template used to display the upload confirmation dialog.  This file can be copied to the active template and modified as needed.
* `header-picasa_album_uploader.php` and `footer-picasa_album_uploader.php` –
If they exist in the active theme, the plugin will use the template files `header-picasa_album_uploader.php` and `footer-picasa_album_uploader.php` for the header and footer respectively.  If they do not exist, the `header.php` and `footer.php` files from the active theme will be used.
