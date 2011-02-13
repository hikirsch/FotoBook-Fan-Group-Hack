=== Fotobook ===
Contributors: aaron_guitar
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=aaron%40freshwebs%2enet&item_name=Fotobook%20Donation&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: facebook, photos, images, gallery, fotobook, import, widget, media
Requires at least: 2.5
Tested up to: 3.0
Stable tag: 3.2.1

Fotobook is a WordPress plugin that will link to your Facebook account(s) and import all of your photo albums for use in your WordPress installation. It uses the Facebook API so importing your photos is a breeze.

== Description ==

Fotobook is a WordPress plugin that will link to your Facebook account(s) and import all of your photo albums for use in your WordPress installation. It uses the Facebook API so importing your photos is a breeze.  Requires PHP 5.
   
**Features:**

* Interfaces with Facebook's API
* Displays photo albums on a WordPress page
* Import photos from mulitple Facebook accounts
* Sidebar widgets for displaying random or recent photos & albums
* Creates an album of photos that the user's tagged in
* Insert individual photos into posts/pages
* Easy-to-use Ajax album management panel
* Frontend validates as XHTML 1.0 Strict

![Fotobook](http://www.aaronharp.com/wp-content/uploads/2007/07/fotobook_logo.png)

== Installation ==

1. Download and unzip the most recent version of Fotobook
2. Upload the entire fotobook folder to /your-wordpress-install-dir/wp-content/plugins/
3. Login to your WP Admin panel, click Plugins, and activate Fotobook
4. Go to Options and then click the Fotobook link. Follow the two steps for linking the plugin to a Facebook account.
5. Now import your albums on the Manage / Fotobook page.
6. That's it!

To upgrade simply replace the old Fotobook directory with the newest version.  Re-import all of your albums to complete the upgrade.

== Frequently Asked Questions ==

= Fotobook isn't working, what's wrong? =

Before contacting me, make sure your host is running PHP 5.  If not, contact their support to see if it can be changed or switch to a [host](http://www.dreamhost.com/r.cgi?275020/hosting.html|fotobook) that does have PHP 5.

= Can this plugin import photos from Facebook groups or pages? =

Not currently.

= How do I create a new style for Fotobook? =

To create a new style, simply duplicate and rename one of the existing styles and modify the files.

= My theme doesn't support widgets.  How do I add the Fotobook widgets manually? =

Use the following template functions:

`fb_photos_widget(count, mode, size)
fb_albums_widget(count, mode)`

*Parameters*

* $count: number of photos/albums to display (default: 4)
* $mode: method for selecting photos/albums to display.  Can either by random or recent
* $size: size of square thumbnails (default: 80)

*Examples*

`<?php fb_photos_widget(4, 'random', 80); ?>
<?php fb_albums_widget(5, 'recent'); ?>`

= I have the Lightbox style selected but the images pop up in another window. =

The [Lightbox plugin](http://wordpress.org/extend/plugins/lightbox-2-wordpress-plugin/) must be installed and activated.  Try using the Colorbox style instead. 

== Screenshots ==

Check out my [photo gallery](http://www.aaronharp.com/photo-gallery/).

== Changelog ==

= 3.2.1 =

* Fixed bug where photo order in Facebook was ignored
* Fotobook scripts and styles are only loaded on the album pages

= 3.2 =

* PHP 4 is no longer supported
* Added a new Colorbox display style which works without any additional plugins
* Fotobook plays better with other plugins using the Facebook API
* Fixed a bug where album covers were sometimes broken
* Fixed bug where clicking the widget thumbnails would occasionally go to the wrong photo
* Fixed bug where the first photo would show twice in the Lightbox
* Fixed issue when importing photos for users with more than 5,000 pictures
* Fixed bug with inserting photos into a post/page
* Converted all JavaScript to use jQuery instead of Prototype
* Optimized DB structure to avoid future problems
* Upgraded to latest version of the Facebook PHP Client (which kills support for PHP4)
* Code cleanup and optimization

= 3.1.8 =

* Fixed a bug where some accounts weren't able to import albums. Props to Aaron Ibrahim for figuring this one out!

= 3.1.7 =

* Long photo captions are no longer truncated
* Fixed bug which could cause some of the photos to not be imported
* Fixed bug with Windows systems not creating the albums table.  Manifested itself by acting like it imported the albums and then saying "There are no albums."

= 3.1.6 =

* Fixed bug where changes to album order were not saved
* Fixed other small bugs

= 3.1.5 =

* Added cron script for automatically updating albums (see settings panel)
* Readied Fotobook for WP 2.7
* Fixed duplicate key bug when importing photos of multiple users (props Darrell)

= 3.1.4 =

* Fixed bug where album pages were showing 404 errors
* Hopefully resolved some conflicts with WordBook
* Error handling for empty albums
* WP Ajax hook is now used for requests

= 3.1.3 =

* Widget can now be added manually to the sidebar.  See FAQ section
* Added confirmation when user tries to leave manage page before applying permissions
* Updated to latest version of Facebook platform
* Fixed path to SimpleXML for Facebook PHP 4 platform
* Fixed safe mode error
* Fixed management display issue in Internet Explorer 7
* Added debug info to the settings page

= 3.1.1 =

* Fixed error when user is not tagged in any photos
* Fixed some display/formatting issues

= 3.1 =

* Fotobook now creates an album of photos that the user is tagged in
* Added an option to hide albums from showing up where pages are listed in your theme
* Password protected albums are now protected
* Restyled the admin panels to look better with WP 2.5
* Fixed problem with UTF8 encoding in photo captions
* Fixed conflict with other plugins that tried to hide pages
* Fixed wp_rewrite issue when importing albums
* Fixed fatal exception in PHP 5 when the session key became invalid
* Other small tweaks and fixes

= 3.0.7 =

* Disabled output buffering for albums because it was causing too many issues.

= 3.0.6 =

* Fixed fatal error on activation with some setups.

= 3.0.5 =

* Modified feature for inserting photos into posts to work with WP 2.5's new media library.
* Really fixed fatal error bug when upgrading to version 3.
* Photo gallery can now be a sub-page.
* Improved error reporting.
* Cleaned up the code.

= 3.0.3 =

* Disabled Wordbook actions when adding and deleting album pages to prevent Wordbook from re-instantiating the Facebook API class.
* Fixed fatal 'Session key invalid or no longer valid' error when upgrading to version 3.

= 3.0.2 =

* Updated to the latest versions of the PHP4 & 5 Facebook API clients.  Huge speed boost for photo imports with PHP5.

= 3.0.1 =

* Fixed bug where albums would be imported but it would still say "There are no albums."
* Fixed issue with local scope of $table_prefix during plugin activation in WP2.5.

= 3.0 =

* Added support for linking to multiple Facebook accounts
* Major improvements on the photos widget display.  Thumbnails are now squares for clean display.
* Photo widget links go to the actual photo.
* Added module in the post/page upload iframe for inserting individual photos.
* Ajaxified the management panel so that the user is more aware of what's going on.  There is a progress bar while albums are being imported.
* Streamlined the way that the albums are requested from Facebook resulting in far fewer requests.
* Added "Embedded Width" option to fix overlapping problems with the embedded style on narrower themes.
* Added stylesheet editor to options panel.
* Removed the popup style because it's kinda old school.
* Cleaned up the code a bit.

= 2.1 =

* Photos from hidden albums are no longer shown in sidebars. And hidden albums are no longer accessible by their permalink.
* Hopefully fixed the escaped quotes problem.
* It detects changed albums/photos a little better now.
* Lightbox will go through all photos in an album, not just the current page.
* Fixed the fatal error if the isterLogger class is already present on the server.
* Sidebar thumbnails can be larger than 130px (uses full image instead of thumbnail).
* Added “Remove All” button in the manage page to get rid of all albums and photos and start over (some people have had problems with partial imports).

= 2.0.1 =

* Fixed an array_slice backwards compatibility issue with pre PHP 5.0.2 servers

= 2.0 =

* Revamped to work with Facebook’s API
* Multiple display styles are now supported
* Added widgets for displaying photos or albums in the sidebar
* Got rid of all the messy cURL and regex stuff.

= 1.3.1 =

* Fixed to work with Facebook’s new look

= 1.3 =

* IMPORTANT: You must deactivate and reactivate the Fotobook plugin after installing this version.
* Fixed the bug that caused the whole first page of photos to be imported twice. Just re-cache your albums and you should be good to go.
* Added pagination to the albums page
* Better error reporting
* Other minor changes

= 1.2.1 =

* The long-awaited WP 2.1 compatibility fix.
* Fixed bug with the ‘Prev’ button
* IMPORTANT: If you already have albums imported, you will need to re-cache them all after installing this update. If you still have problems, then try deleting them and adding them again. If it still doesn’t work, let me know =)

= 1.2 =

* Fixed bug that was causing the main album page to not load
* Fixed bug where an extra broken image was sometimes displayed
* Cleaned up the code so that it is easier to make layout changes to the albums
* New albums are now added to the top instead of bottom

= 1.1.1 =

* Small layout bug fixes

= 1.1 =

* cURL is no longer required
* Added pagination to bottom of gallery as well
* Cleaned up the code

= 1.0 =

* First release

== Support ==

Submit any problems, questions, suggestions, or compliments through the [WordPress Forums](http://wordpress.org/tags/fotobook?forum_id=10). Make sure your post has the tag "fotobook" otherwise I won't see it.  Become a fan of the [Fotobook Application](http://www.facebook.com/apps/application.php?id=2254862517) to be notified when updates are available.

== Help Out! ==

To be honest, this takes up a lot of time. I enjoy doing it, but some money for it here and there would be nice.  Here are a few ways you can help out (and encourage me to work on it more =) ):

First, you could sign up for a [DreamHost](http://www.dreamhost.com/r.cgi?275020/signup|fotobook) account and use the promo code Fotobook. This not only helps me out but also waives the $50 setup fee when you pay monthly (only $10.95/month). If you choose one of the other payment periods you’ll get $25.00 off. I’ve hosted with them for a couple years and I’ve had no problems. They have all sorts of [features](http://www.dreamhost.com/r.cgi?275020/hosting.html|fotobook) (500gb storage, unlimited domains, PHP4&5) and, best of all, they have one-click WordPress installs and upgrades.

If you are satisfied with your hosting and want to help out, you can send some cash over [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=aaron%40freshwebs.net&item_name=Fotobook%20Donation&buyer_credit_promo_code=&buyer_credit_product_category=&buyer_credit_shipping_method=&buyer_credit_user_address_change=&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP-DonationsBF&charset=UTF-8).
