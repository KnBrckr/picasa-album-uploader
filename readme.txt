=== Plugin Name ===
Contributors: draca
Donate link: http://pumastudios.com/software/picasa-album-uploader-wordpress-plugin
Tags: picasa, upload, images, albums, media
Requires at least: 2.8.5
Tested up to: 2.8.5
Stable tag: trunk FIXME

Easily upload media from Google Picasa Desktop into WordPress and optionally create a post entry displaying the uploaded images using the Wordpress `[gallery]` tag.

== Description ==

Provides a button to be installed into the Google Picasa desktop to directly upload files from Picasa as WordPress media.  Once the button has been downloaded and installed in Picasa, images can be selected in Picasa and uploaded to your WordPress blog with a simple click of the button within Picasa.

If you are not logged in to your blog, you will first be directed to the login page and then return to the upload screen to select the upload options.

This plugin is based on the initial works by [clyang](http://clyang.net/blog/2009/02/06/128 "Picasa2Wordpress Blog Article") and the examples from Google for the [Picasa Button API](http://code.google.com/apis/picasa/docs/button_api.html "Picasa Button API") and [Picasa Web Uploader API](http://code.google.com/apis/picasa/docs/web_uploader.html "Picasa Web Uploader API").

This is a real plugin that lives in the `wp-content/plugins/` directory and does not require special files to be placed in either your server root or in the `wp-admin/` directory.


*   Stable tag should indicate the Subversion "tag" of the latest stable version, or "trunk," if you use `/trunk/` for
stable.


== Installation ==

1. Upload the picasa-album-uploader to the `wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure the plugin through the Admin Settings for Media
1. Use the 'Install Image Upload Button in Picasa Desktop' Link in the Admin Settings -> Media to import the button into Picasa
1. If desired, create the files header-picasa_album_uploader.php and footer-picasa_album_uploader.php in the top level of your themes directory to provide customized header and footer in the upload confirmation dialog displayed by Picasa.
1. Begin uploading photos from Picasa to your blog.

To display the button load link in a post or page, simply insert the shortcode `[picasa_album_uploader_button]` at the desired location.

== Frequently Asked Questions ==

= I changed the slug name (or other part of my WordPress URL) and my button in Picasa stopped working.  What do I do? =

The Picasa button contains a URL to your WordPress installation, including the slug name used by this plugin.  If any portion of the URL changes, the button in Picasa must be replaced so that the button is sending to the correct URL.

= Can I make the button to install the button in Picasa Desktop available in Pages and Posts? =

Yes!  Just put the shortcode `[picasa_album_uploader_button]` where you want the button to display.

== Screenshots ==

TODO Screen shots?

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the directory of the stable readme.txt, so in this case, `/tags/4.3/screenshot-1.png` (or jpg, jpeg, gif)
2. This is the second screen shot

== Theme Additions ==

There are two ways for a theme to control the output of the upload dialog displayed by Picasa Desktop.

1.  The variable `$wp_query-> is_picasa_album_slug` will be set if the page is being handled by the plugin.
2.  Three templates files can be used to configure the page.

The file `picasa_album_uploader/templates/page-picasa_album_uploader.php`, supplied by the plugin, is the default page template used to display the upload confirmation screen.  This file can be copied to the active template and modified as needed.

If they exist in the active theme, the plugin will use the template files header-picasa_album_uploader.php and footer-picasa_album_uploader.php for the header and footer respectively.  If they do not exist, the header.php and footer.php files from the active theme will be used.

When formatting the page, it is best to avoid links that will navigate away from the upload confirmation screen.  The plugin will handle redirecting to the WordPress login screen to validate the user as necessary.

== To Do ==

* Provide uninstall option to delete plugin settings from database
* Add i18n support
* Allow Picasa button icon to be replaced by theme
* Allow customization of image sizes available in upload screen

== Changelog ==

= 0.3 =
* Created Admin Settings Section on the Media page.

= 0.2 =
* Primary functions of interacting with Picasa and uploading images into WP media complete.

= 0.1 =
* Prototyped

= 0.0 =
* Plugin development initiated
