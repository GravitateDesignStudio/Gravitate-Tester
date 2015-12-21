=== Gravitate Automated Tester ===
Tags: Gravitate, Automated Testing
Requires at least: 3.5
Tested up to: 4.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Run Automaged PHP or JS Tests.

== Description ==

Description: This Plugin allows you to easily run Tests against our PHP or JS code. It is mainly meant for Developers, but can be used by anyone.  Like checking that you made sure that the site is indexable by Search Engines in Production and vise-versa that it is not Indexable in Dev or Staging.

= Pre-Installed Tests =
* HTML Valid - Check that your Pages are HTML Valid (W3C)
* JS Console Logs - Check General Pages for Console Logs on Page Load
* JS Errors - Check General Pages for JS Errors on Page Load
* Plugins Updated - Make sure WordPress Plugins are the Latest Stable Version
* SEO Indexable - Allow search engines to index the site in Production
* SEO Remove Indexing - Disallow search engines to index the site in Dev and Staging
* WP Debug - Make sure WordPress Debug is set to false
* WP Head/Footer - Check for wp_head() and wp_footer()
* WP Updated - Make sure WordPress is Latest Stable Version
 - More to come soon

==Requirements==

- jQuery
- WordPress 3.5 or above
- PHP 5.3+
- PHP cUrl


== Installation ==

1. Upload the `gravitate-tester` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You can configure the Plugin Settings in `Settings` -> `Gravitate Automated Tester`


== Changelog ==

= 1.0.0 =
* Initial Creation