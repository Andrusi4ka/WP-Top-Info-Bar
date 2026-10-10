=== WP Top Info Bar ===
Contributors: andrusi4ka
Tags: notification bar, top bar, announcement, banner, promo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An information bar at the very top of the site: text, colours, spacing, animations and images, with separate values for tablets and phones.

== Description ==

WP Top Info Bar adds a "Top Info Bar" menu where you set up a bar shown above everything else on the site (through the theme's `wp_body_open` hook).

* Message text, background and text colour.
* Text size, padding and margin, each with its own value for desktop, tablet (up to 1024px) and phone (up to 767px).
* Text animations: fade in, slide up, fade from left or right, slide from the page edge, blink, scale up; with a start delay.
* Images left or right of the text, or above or below it, each with its own size, margin, animation, delay and CSS class.
* Custom CSS classes and HTML id for the bar.
* Animations are switched off for visitors who ask for reduced motion.
* Settings that do not apply (a delay without an animation) are greyed out and keep their values.

Bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5 (MIT).

== Installation ==

1. Download `wp-top-info-bar.zip` from the latest release on https://github.com/Andrusi4ka/WP-Top-Info-Bar/releases.
2. In WordPress go to Plugins > Add New > Upload Plugin and upload the zip, then activate it.
3. Open "Top Info Bar" in the admin menu, enter the text and tick "Enable bar".

Later versions are offered under Plugins like any other update.

== Frequently Asked Questions ==

= The bar does not show =

The theme must call `wp_body_open()` right after `<body>` (most themes do, Woodmart included), "Enable bar" must be ticked, and the bar needs a text or at least one image.

= What is removed when I delete the plugin? =

Its settings. Images chosen for the bar stay in the media library.

= Where do updates come from? =

From this plugin's GitHub releases. The plugin header's "Update URI" stops WordPress from offering a wordpress.org plugin of the same folder name.

== Changelog ==

= 3.0.0 =
* Updates from GitHub releases, shown under Plugins like any other update.
* Tablet and phone values now switch at 1024px and 767px, the same as Woodmart (tablet was up to 1200px).
* Fixed: after an update browsers could keep the old stylesheet and scripts (their version never changed).
* Fixed: a settings field sent as a list instead of text made saving fail with a PHP error.
* "Text delay" and an image's "Delay" are greyed out (keeping their values) while there is no animation.
* Admin texts can be translated (languages/wp-top-info-bar.pot).
* Header now states the required PHP (7.4) and WordPress (6.0) versions.
* Code cleanup.

= 2.0.0 =
* Separate text size, padding, margin and image size and margin for tablets and phones.
* Settings are removed when the plugin is deleted.
