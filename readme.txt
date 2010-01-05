=== Picsa Album Uploader ===
Contributors: draca
Donate link: http://pumastudios.com/software/picasa-album-uploader-wordpress-plugin
Tags: picasa, upload, images, albums, media
Requires at least: 2.8.5
Tested up to: 2.8.5
Stable tag: 0.3.1

Easily upload media from Google Picasa Desktop into WordPress.

== Description ==

Provides a button to be installed into the Google Picasa Desktop to directly upload files from Picasa as WordPress media.  Once the button has been downloaded and installed in Picasa, images can be selected in Picasa and uploaded to your WordPress blog with a simple click of the button within Picasa.

If you are not logged in to your blog, you will first be directed to the login page and then return to the upload screen to select the upload options.

This plugin is based on the initial works by [clyang](http://clyang.net/blog/2009/02/06/128 "Picasa2Wordpress Blog Article") and the examples from Google for the [Picasa Button API](http://code.google.com/apis/picasa/docs/button_api.html "Picasa Button API") and [Picasa Web Uploader API](http://code.google.com/apis/picasa/docs/web_uploader.html "Picasa Web Uploader API").

= What's Next? =

1.  Provide uninstall method
1.  At upload, optionally create a new post using the WP shortcode [gallery] to publish the newly uploaded files.
1.  Internationalization
1.  Refine default display of images to be uploaded.

== Installation ==

1. Upload the picasa-album-uploader to the `wp-content/plugins/` directory
1. Activate the plugin through the Admin -> Plugins Screen
1. Configure the plugin through the Admin -> Settings -> Media Screen.
1. Use the "Install Image Upload Button in Picasa Desktop" Link in the Admin Settings -> Media to import the button into Picasa
1. If desired, create the files header-picasa_album_uploader.php and footer-picasa_album_uploader.php in the top level of your themes directory to provide customized header and footer in the upload confirmation dialog displayed by Picasa.
1. Begin uploading photos from Picasa to your blog.

To display the button load link in a post or page, simply insert the shortcode `[picasa_album_uploader_button]` at the desired location.

== Frequently Asked Questions ==

= I changed the slug name (or other part of my WordPress URL) and my button in Picasa stopped working.  What do I do? =

The Picasa button contains a URL to your WordPress installation, including the slug name used by this plugin.  If any portion of the URL changes, the button in Picasa must be replaced so that the button is sending to the correct URL.

= Can I make the button to install the button in Picasa Desktop available in Pages and Posts? =

Yes!  Just put the shortcode `[picasa_album_uploader_button]` where you want the button to display.

= What's in that download that needs to be installed into Picasa? =

There is no code in the download.  It is comprised of two elements, an XML file describing the button to Picasa Desktop that includes the URL to reference when the button is clicked and a file containing the button graphic to display within the Picasa Desktop.

= Can I have buttons from multiple WordPress blogs installed at the same time? =

Yes!  The tool tip for the button will identify the name of the WordPress blog associated with the button.

= Can I change the button image? =

In the future, a theme will be allowed to override the button graphic.  Right now, the only way to change the button is by replacing the file `picasa-album-uploader/images/wordpress-logo-blue.psd` in the plugin directory with the desired content.  The layer containing the button image must be "upload-button".  The image should be no larger than 40 pixels wide by 25 pixels high with 72 dpi resolution.  The color model used must be RGB with 8 bits/channel and should use a transparent background.  Full details can be found at the [Picasa Button API](http://code.google.com/apis/picasa/docs/button_api.html "Picasa Button API") reference.

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
1. To completely remove the button from Picasa, remove the associated `picasa_album_uploader.pbz` file from the Picasa configuration.  On Mac OSX the `pbz` file can be found in the Folder `Home/Library/Application Support/Google/Picasa3/buttons`.



== Screenshots ==

1. Picasa Album Uploader Options in Media Settings Admin Screen.

== Changelog ==

= 0.3.1 =
* Fix defect in redirect URL to display results page
* Fix interaction issue with WordPress.com Stats plugin

= 0.3 =
* Created Admin Settings Section on the Media page.
* Redirect immediately to login screen if user not logged in
* Add fields to upload screen to set Title, Caption and Description for uploaded file(s).
* Initial CSS Formatting of upload screen

= 0.2 =
* Primary functions of interacting with Picasa and uploading images into WP media complete.

= 0.1 =
* Prototyped

= 0.0 =
* Plugin development initiated

== Upgrade Notice ==

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




