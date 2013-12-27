=== Picasa Album Uploader ===
Contributors: draca
Donate link: http://pumastudios.com/software/picasa-album-uploader-wordpress-plugin
Tags: picasa, upload, images, albums, media
Requires at least: 3.1
Tested up to: 3.2.1
Stable tag: 0.7.2

Easily upload media from Google Picasa Desktop into WordPress.

== Description ==

Provides a button to be installed into the Google Picasa Desktop to directly upload files from Picasa as WordPress media.  Once the button has been downloaded and installed in Picasa, images can be selected in Picasa and uploaded to your WordPress blog with a simple click of the button within Picasa.

If you are not logged in to your blog, you will first be directed to the login page and then return to the upload screen to select the upload options.

This plugin is based on the initial works by [clyang](http://clyang.net/blog/2009/02/06/128 "Picasa2Wordpress Blog Article") and the examples from Google for the [Picasa Button API](http://code.google.com/apis/picasa/docs/button_api.html "Picasa Button API") and [Picasa Web Uploader API](http://code.google.com/apis/picasa/docs/web_uploader.html "Picasa Web Uploader API").

The Picasa API this plugin is based upon has been deprecated by Google.  A future update of Picasa could remove the API completely which will terminate the ability of this plugin to receive uploads from Picasa.

== Installation ==

This plugin requires PHP5.2

1.  Add the plugin files in your WordPress installation like any other plugin.
1.  Configure one of the permlink options in the Admin -> Settings -> Permalinks screen.  You must use permalinks for this plugin to function.  See the FAQ for details.
1.  Activate the plugin in Admin -> Plugins
1.  Configure the plugin options in Admin -> Settings -> Media
1.  Use the "Install Image Upload Button in Picasa Desktop" Link in Admin -> Settings -> Media to import the upload button into Picasa
1.  If desired, create the files header-picasa_album_uploader.php and footer-picasa_album_uploader.php in the top level of your themes directory to provide customized header and footer in the dialog displayed by Picasa.

= Usage Hints =

Once installed in Picasa Desktop, select photos in Picasa and press the WordPress button to upload the selected photos to your blog.

To display the button load link in a post or page, insert the shortcode `[picasa_album_uploader_button]` at the desired location.

A log of plugin activity useful to debug failures can be obtained by selecting the plugin option 'Enable Debug Log' and saving the configuration change.  The logging might impact performance of your website so should only be enabled when debugging is required.

= Reporting Problems =

Please follow these instructions to report problems:

1. Enable debug logging in Admin -> Settings -> Media
1. Reproduce the problem
1. Provide the log results, description of problem, plugin version and WordPress version in a post to the [Support Forum](http://wordpress.org/tags/picasa-album-uploader?forum_id=10 "Picasa Album Uploader Support Forum").

== Frequently Asked Questions ==

= The plugin reports a problem receiving long argument names. =

This is the result of a self test added in v0.7 to detect when the server is configured in a way that prevents required arguments sent by Picasa from being received by the plugin.  This test is run anytime an admin screen is displayed.  The self test results have also been added to the debug log report when the plugin debugging is enabled.

See next question regarding no files uploaded by Picasa for discussion on the symptom and possible solution.

= An error page is displayed in the browser that no files were uploaded by Picasa =

The most likely cause of this problem is software between Picasa and the plugin that is removing HTTP Request arguments with a long name.  When Picasa is sending the upload request, it uses a long argument name like `http://localhost:55995/4cb54313c490d285f54664e018d50799/image/e50b8dc1ae05b682_jpg?size=1024` in the request to the server.  In my experience, the arguments used by Picasa have been 93 characters.  Some server software like [Suhosin](http://www.hardened-php.net/suhosin/index.html "Suhosin Hardened PHP") (PHP security plugin) and [modsecurity](https://modsecurity.org/ "modsecurity - Open Source Web Application Firewall") (Apache security plugin) can be configured to remove long argument names from the HTTP Request as a security measure.  Unfortunately this has the undesirable side effect of breaking this plugin.  There may be other security applications that will have a similar affect on the incoming HTTP Request.

Review the server error logs for possible clues.  If Suhosin is configured, you might see an error like the following in the server error log:

`ALERT - configured request variable name length limit exceeded - dropped variable 'http://localhost:51134/b921a58ec2806ab82f5399515fba226e/image/b0f008e85a4fa153_jpg?size=1024'`.

Check the Suhosin setting values for `suhosin.post.max_name_length` and `suhosin.request.max_varname_length`.  A setting of at least 100 is recommended to allow the long argument names that are required by the Picasa engine.  You might need to increase it further depending on the length of the dropped argument name observed in the error log.  

The Apache plugin mod_security can also be configured to restrict the length of the argument name.  The server error log message might look something like this:

`ModSecurity: Warning. Operator GT matched 90 at ARGS_NAMES:http://localhost:55995/4cb54313c490d285f54664e018d50799/image/e50b8dc1ae05b682_jpg?size=1024. \[file "...modsecurity_crs_23_request_limits.conf"] \[line "23"] \[id "960209"] \[rev "2.2.1"] \[msg "Argument name too long"] \[severity "WARNING"] \[hostname "localhost"] \[uri "/picasa_album_uploader/selftest"] \[unique_id "Tl5bYgpABGUAAJ5HBIIAAAAK"]
ModSecurity: Access denied with code 403 (phase 2). Pattern match "(.*)" at TX:960015-PROTOCOL_VIOLATION/MISSING_HEADER-REQUEST_HEADERS. \[file "...modsecurity_crs_49_inbound_blocking.conf"] \[line "26"] \[id "981176"] \[msg "Inbound Anomaly Score Exceeded (Total Score: 6, SQLi=, XSS=): Last Matched Message: Argument name too long"] \[data "Last Matched Data: 0"] \[hostname "localhost"] \[uri "/picasa_album_uploader/selftest"] \[unique_id "Tl5bYgpABGUAAJ5HBIIAAAAK"]`

Depending on your configuration, the modsecurity configuration line affecting this size might look like the following.  A setting of at least 100 is recommended to allow the long names used by Picasa.

`SecAction "phase:1,t:none,nolog,pass,setvar:tx.arg_name_length=90"`

Special thanks go to [rbredow](http://wordpress.org/support/profile/rbredow "rbredow Wordpress User Profile") for the assistance in diagnosing this interaction with server software.

= Clicking the "install" button doesn't work, the button is not installed in Picasa. =

There are several possible failures:

1. Browser says it does not recognize the protocol - This message means that Picasa has not registered itself with your browser as being the application to handle links starting with `picasa://`. You could try to reinstall Picasa, which should cause it to register itself with your browser.  You must also be using at least Picasa v3.0.
1. Nothing happens, Picasa does not launch. - Make sure you are running at least Picasa version 3.0 and that Picasa can open on your computer.  This is a configuration issue between the browser and Picasa.  The browser should open Picasa when it attempts to open a URL that begins with "picasa://".  You could try re-installing Picasa.  Or check the Applications settings in your browser preferences for how the "picasa:" content type is handled.
1. Picasa launches but does not  show the Wordpress button to configure - Picasa will send a request to your server to download the button file.  Access to the requested page must be public, no passwords or login to your server required, for Picasa to download the button data file.  There is no opportunity in this stage of processing for Picasa to supply login credentials.  As a possible workaround, you could try manually downloading the button file and placing it in the Picasa configuration directory that is discussed further below.

= Why is the picasa_album_uploader.pbz file missing from the plugin contents? =

The .pbz file is dynamically created by the plugin due to the customization required in the contents.  See the question below about the contents of the download for details.

= How can the button file be manually downloaded? =
The URL used to generate and download the button file is generated based on the slug name assigned to the plugin.  Assuming a simple default configuration, the URL would be:  http://your-site.com/picasa_album_uploader/picasa_album_uploader.pbz

Once downloaded this file can be placed in the Picasa configuration directory and the button can be configured in Picasa the next time Picasa is launched.  The configuration directory path is detailed further below.

= What's in that download (.pbz file) that needs to be installed into Picasa? =

There is no executable code in the download.  It is comprised of two elements:

* An XML file describing the button to Picasa Desktop.  This includes a URL to your blog for Picasa to use when the button is clicked.
* A file containing the button graphic to display within the Picasa Desktop.

= Can I make the link to install the button in Picasa Desktop available in Pages and Posts? =

Yes!  Just put the shortcode `[picasa_album_uploader_button]` where you want the button to display.

= Can I have buttons from multiple WordPress sites installed in Picasa at the same time? =

Yes!  The tool tip for the button will identify the name of the WordPress site associated with the button.  You might want to change the button icon to graphically differentiate the connected WordPress installs.

The plugin also functions in a multi-site environment.

= How do I change the button icon? =

In the future, a theme will be allowed to override the button graphic.  Right now, the only way to change the button is by replacing the file `picasa-album-uploader/images/wordpress-logo-blue.psd` in the plugin directory with the desired content.  This is a photoshop file and a compatible image editor must be used.  The layer containing the button image must be named "upload-button".  The image should be no larger than 40 pixels wide by 25 pixels high with 72 dpi resolution.  The color model used must be RGB with 8 bits/channel and should use a transparent background.  Full details can be found at the [Picasa Button API](http://code.google.com/apis/picasa/docs/button_api.html "Picasa Button API") reference.

= Why are Permalinks required? =

The Picasa Desktop client is very picky about the format of the URLs that it will accept during the upload process and will only accept a simple URL consisting of a single filename.  The use of the slash (/) and question-marks (?) in the URL syntax results in no files being uploaded by Picasa to the server.  In order to satisfy this Picasa requirement, permalinks must be used.

= I changed the slug name (or other part of my WordPress URL) and my button in Picasa stopped working.  What do I do? =

The Picasa button contains a URL to your WordPress installation, including the slug name used by this plugin.  If any portion of the URL changes, the button in Picasa must be replaced so that the button is sending to the correct location in your WordPress site.

= What happens if I change my permalink settings? =

You may freely change the format of your permalinks without affecting the ability to upload files from Picasa Desktop.  You must however keep permalinks enabled as discussed above.

= Other Picasa Uploader plugins require files be placed in the `wp-admin` and/or the server root.  Does this plugin require the same? =

This is a real plugin that lives in the `wp-content/plugins/` directory and does not require special files to be placed in either your server root or in the `wp-admin/` directory.  Further, the plugin supports themes to customize the appearance of the upload dialog displayed by Picasa.

= How do I uninstall this plugin? =

This plugin can be uninstalled from the WordPress Plugin Admin screen.  An uninstall script has been provided that will perform the necessary Wordpress cleanup.

= How do I remove the button from Picasa? =

1. In Picasa Select "Tools -> Configure Buttons..."
1. In the "Current Buttons" section of the Picasa Dialog, select the "WordPress" button.
1. Click the "Remove" button.
1. To completely remove the button from Picasa, remove the associated `picasa_album_uploader.pbz` file from the Picasa configuration directory on your computer.

= Where are the Picasa buttons stored on my computer? =

Button files end with `.pbz` and the location depends on the OS you are using:

Windows:  C:\Program Files\Google\Picasa3\buttons
XP:  C:\Documents and Settings\Username\Local Settings\Application Data\Google\Picasa3\buttons
Vista:  C:\Users\Username\AppData\Local\Google\Picasa3\buttons
OSX: ~/Library/Application Support/Google/Picasa3/buttons

== Screenshots ==

1. Picasa Album Uploader Options in Media Settings Admin Screen.

== Changelog ==

= 0.7.3 =
* fix: PHP split() deprecated, change to explode()

= 0.7.2 =
* fix: Use wp_enqueue_scripts action to add javascript and styles

= 0.7.1 =

* Add debug logging related to creation of minibrowser page
* fix: Call to private method picasa_album_uploader_options::test_long_var()

= 0.7 =

* Add selftest to confirm plugin is able to receive long POST variables
* Implemented activation hook
* FIX: Enforce minimum PHP v5.2 requirement when plugin is activated.
* Implement uninstall script
* Improve formatting in mini-browser window
* FIX: Slug field too short in admin screen
* Mask hostname in debug log
* FIX: Multi-site configs expose cross-site reference that can trip mod_security rules

= 0.6.2 =

* FIX: Pluging generating wrong URLs when a site is configured with home_url() != site_url().  e.g. when Wordpress installed in a different directory from the one presented to the user.  Props to [jacob.bro](http://wordpress.org/support/profile/jakobbro) for the debug log that pointed out the flaw.

= 0.6.1 =

* FIX: Assume wp_redirect() will succeed as rest of core does.

= 0.6 =

* Supports Wordpress Multi-site
* Improved message when no files to be uploaded.
* FIX: Use plugin supplied login screen for authentication to address redirect issues when running in certain multi-site configurations.
* FIX: Hide WP Admin bar in Picasa minibrowser window

= 0.5 =

* Add i18n support
* Enhanced result page reporting
* Fixed defect in reporting of errors detected during upload resulting in silent failure.
* Documented plugin interaction with PHP Security plugin Suhosin.  Thanks go to [rbredow](http://wordpress.org/support/profile/rbredow "rbredow Wordpress User Profile") for diagnosing the interaction.

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

= 0.7 =

* Added self-test to aid in diagnosis when plugin not working
* Improve formatting of minibrowser pages

= 0.6.2 =

* Generate URLs based on plugin generated slug using home_url() vs. site_url() 

= 0.6.1 =

* Some sites failing due to [Wordpress Trac Ticket 17472](https://core.trac.wordpress.org/ticket/17472 "wp_redirect() should true on success")

= 0.6 =

* Support WP multi-site configurations

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
