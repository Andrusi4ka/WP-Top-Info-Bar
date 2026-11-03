=== WP Top Bar ===
Contributors: andrusi4ka
Tags: notification bar, top bar, announcement, banner, promo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An information bar at the very top of the site: text, colours, spacing, animations and images, with separate values for tablets and phones.

== Description ==

WP Top Bar adds a "WP Top Bar" menu where you create one or more bars shown above everything else on the site (through the theme's `wp_body_open` hook), or anywhere through a shortcode.

* Several bars, each with its own priority (lower numbers appear higher), an on/off switch in the list, and cloning.
* A layout builder with left, centre and right zones: drag texts, buttons and images between them.
* Text elements with the WordPress editor, their own size and colour.
* Buttons with a link, size, corners, style and colours.
* Images with their own size, margin, animation, delay and CSS class.
* Background, bottom border and shadow; padding and margin per side.
* Animations for the bar, for each zone and for each image, including a marquee; with a start delay.
* Desktop, tablet (up to 1024px) and phone (up to 767px) values for sizes, spacing and animations.
* A live preview in desktop, tablet and phone frames, with a developer overlay showing padding and margins.
* Custom CSS classes and HTML id for the bar.
* Animations are switched off for visitors who ask for reduced motion.

Bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5 (MIT).

== Installation ==

1. Download `wp-top-info-bar.zip` from the latest release on https://github.com/Andrusi4ka/WP-Top-Info-Bar/releases.
2. In WordPress go to Plugins > Add New > Upload Plugin and upload the zip, then activate it.
3. Open "WP Top Bar" > "Add New" in the admin menu, add a text, button or image, tick "Enable bar" and publish.

Later versions are offered under Plugins like any other update.

== Frequently Asked Questions ==

= The bar does not show =

The theme must call `wp_body_open()` right after `<body>` (most themes do, Woodmart included), the bar must be published with "Enable bar" ticked, and it needs a text, a button or an image.

= What is removed when I delete the plugin? =

Its bars and settings. Images chosen for the bar stay in the media library.

= Where do updates come from? =

From this plugin's GitHub releases. The plugin header's "Update URI" stops WordPress from offering a wordpress.org plugin of the same folder name.

== Changelog ==

= 4.0.0 =
* Several bars: each is edited with the WordPress publish box, has a priority and a shortcode, and can be switched on or off and cloned from the list.
* The existing bar is copied into the first new bar on the first admin visit (the old settings are kept as a backup).
* Layout builder with left, centre and right zones, each with its own width, background, spacing and animation.
* Texts (WordPress editor), buttons and images are elements of their own, edited in dialogs.
* Bottom border, shadow and a colour picker with transparency.
* Marquee animation; bar animations can differ on tablet and phone.
* Live preview in device frames, with a developer overlay.
* Fixed: the preview asked for a text or image while the bar had a text or button.
* Fixed: calc() with "+" in a size was broken; a value with an unclosed bracket or a comment could disable the bar's other styles.
* Faster: the site looks up the bars once per page; the admin no longer builds the bar settings on every screen.
* Deleting the plugin now removes all its bars, auto-drafts included.

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
