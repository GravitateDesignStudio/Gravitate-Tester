=== Gravitate Automated Tester ===
Tags: Gravitate, Automated Testing
Requires at least: 3.5
Tested up to: 4.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Run Automated PHP or JS Tests.

== Description ==

Description: This Plugin allows you to easily run Tests against our PHP or JS code. It is mainly meant for Developers, but can be used by anyone.  Like checking that you made sure that the site is indexable by Search Engines in Production and vise-versa that it is not Indexable in Dev or Staging.

= Pre-Installed Tests =
* PHP Errors - Check for PHP Errors, Warnings and Notices.
* Gravity Forms Honeypot - Check to make sure that Anti-Spam Honeypot is enabled on all forms.
* Checks Sitespeed against Google Site Speed Insights
* Checks for Sitemap Pages
* Checks for Favicon
* HTML Valid - Check that your Pages are HTML Valid (W3C)
* JS Console Logs - Check General Pages for Console Logs on Page Load
* JS Errors - Check General Pages for JS Errors on Page Load
* Plugins Updated - Make sure WordPress Plugins are the Latest Stable Version
* SEO Indexable - Allow search engines to index the site in Production
* SEO Remove Indexing - Disallow search engines to index the site in Dev and Staging
* WP Debug - Make sure WordPress Debug is set to false
* WP Head/Footer - Check for wp_head() and wp_footer()
* WP Updated - Make sure WordPress is Latest Stable Version

  More to come soon


==Requirements==

- jQuery
- WordPress 3.5 or above
- PHP 5.3+
- PHP cUrl


== Installation ==

1. Upload the `gravitate-automated-tester` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You can configure the Plugin Settings in `Settings` -> `Gravitate Automated Tester`

== Screenshots ==

1. You can run multiple Tests at once.
2. You can configure your Tests and what Environment to Run them in.
3. This is a Quick Developers Tab to provide details on how to Add and Manage your own Tasks.

== Changelog ==

= 1.3.1 =
* Bug fix on Class not found

= 1.3.0 =
* Added Authentication for Injector Methods.
* Updated Dev Tab documentation.

= 1.2.1 =
* Bug Fix - Updated PHP Errors checking
* Added Alert if running the Run All too often.
* Updated Site Speed Rating and Description Text.
* Extended wp_remote_get() timeout to 15 seconds.

= 1.2.0 =
* Added Gravity Forms Honeypot Test
* Added is_editable() method for testers
* Added can_run() method.  Defaults to true.
* Added fix_confirmation() method for the fix buttons.

= 1.1.3 =
* Bug Fix on Class prefix

= 1.1.2 =
* Changed Fix button to only allow if DISALLOW_FILE_EDIT is not true or on local environment

= 1.1.1 =
* Changed file_get_contents to wp_remote_get to support redirects

= 1.1.0 =
* Added PHP Errors Test
* Added Sitemaps Test
* Added SiteSpeed Test
* Updated JS Errors Test
* Updated JS Console Test
* Added Favicon Test

= 1.0.0 =
* Initial Creation