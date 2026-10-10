# WP Top Info Bar

A WordPress plugin that adds a customizable information block at the top of the site. The block can be configured from the admin panel: text, colors, margins, animations, images, and separate values for different breakpoints.

![screen](./screens/FireShot%20Capture%20067%20-%20MySite.local%20-%20[mysite.local].png)

## Installation And Updates

1. Download `wp-top-info-bar.zip` from the latest [release](https://github.com/Andrusi4ka/WP-Top-Info-Bar/releases).
2. In WordPress go to `Plugins -> Add New -> Upload Plugin`, upload the zip and activate it.
3. Open `Top Info Bar` in the admin menu.

Newer versions then appear under `Plugins` like any other update. Settings are stored in the database and are kept across updates.

To publish a version: raise `Version` and `WP_TOP_INFO_BAR_VERSION` in `wp-top-info-bar.php`, `Stable tag` and the changelog in `readme.txt`, regenerate `languages/wp-top-info-bar.pot`, commit, then push a tag `vX.Y.Z`. The release workflow builds `wp-top-info-bar.zip` and attaches it to the release.

## Purpose

The plugin is designed to display an information banner at the top of the site without manually editing the theme or templates.

It can be used for:

- promotions and discounts
- service announcements
- marketing banners
- short informational messages
- adding icons or decorative images next to the text

![screen](./screens/FireShot%20Capture%20066%20-%20Top%20Info%20Bar%20‹%20MySite.local%20—%20WordPress%20-%20[mysite.local].png)

## Key Features

### 1. Block Display Management

- a separate menu item in the WordPress admin panel: `Top Info Bar`
- the ability to enable or disable the block display on the site

### 2. Text Customization

- message text
- text color
- text size
- external `margin`
- internal `padding`
- custom CSS classes
- custom HTML `id`

### 3. Text Animations

The following options are available for text:

- no animation
- `Fade in`
- `Slide up`
- `Fade from left`
- `Fade from right`
- `Slide from page left`
- `Slide from page right`
- `Blink`
- `Scale up`

### 4. Adding Images

The plugin allows adding an unlimited number of images through the Media Library.

For each image, the following parameters are available:

- size
- `margin`
- custom CSS class
- position:
  - left of the text
  - right of the text
  - centered above the text
  - centered below the text
- animation
- animation delay

### 5. Image Animations

For each image, you can choose:

- no animation
- `Swing from top`
- `Swing from bottom`
- `Move left and right`
- `Blink`
- `Scale up`

### 6. Responsive Values for Breakpoints

Separate values are supported for:

- `desktop`
- `tablet` — `max-width: 1024px`
- `mobile` — `max-width: 767px`

Supported for:

- `Text size`
- `Padding`
- `Margin`
- `Size` for images
- `Margin` for images

## Admin Interface

The admin panel includes:

- a separate settings page
- tooltips near fields
- a block for managing images
- image selection through the standard WordPress Media Library
- visual icons for responsive settings fields

## Data Saving Mechanism

The plugin **does not create new tables** in the database.

All settings are stored in the standard WordPress table:

- `wp_options`

One option is used:

- `wp_top_info_bar_settings`

This means:

- the WordPress structure remains unchanged
- no additional tables are created
- the plugin does not interfere with the database schema

## Impact on Standard WordPress Functionality

The plugin does not alter the default behavior of WordPress at the system level.

What it does:

- adds one menu item in the admin panel
- adds one option in `wp_options`
- loads its CSS/JS files only where needed
- displays the information block on the frontend via WordPress hooks

What it **does not do**:

- does not modify the WordPress core
- does not create new tables
- does not overwrite theme templates
- does not interfere with standard post types
- does not change default roles or permissions

## Technical Implementation

The plugin is built modularly:

- `wp-top-info-bar.php` — the bootstrap file of the plugin
- `includes/class-wp-top-info-bar.php` — initialization