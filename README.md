# LocalDev Switcher #
**Contributors:** [thewebist](https://profiles.wordpress.org/thewebist/)  
**Tags:** development, plugins, local development, plugin management, workflow  
**Requires at least:** 6.5  
**Tested up to:** 6.9  
**Requires PHP:** 8.1  
**Stable tag:** 0.7.0  
**License:** GPL2+  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Easily switch between version-controlled plugins and local development versions by toggling in the Plugins screen.

## Description ##

LocalDev Switcher allows you to seamlessly toggle between production plugins and their local development versions.

**Usage:**

1. Place your local dev version of a plugin in:
   
   `wp-content/plugins/localdev-{plugin-slug}`

2. You should now have two directories containing the same plugin inside your `/plugins/`:
   1. `/plugins/your-plugin/` - Loaded from VCS/WordPress.org Plugins/etc.
   2. `/plugins/localdev-your-plugin/` - Your local development version
3. Use LocalDev Switcher to toggle between the version-controlled and local versions. The toggle UI appears in the plugin meta row.

LocalDev Switcher prevents double-loading and ensures only the desired version is active.

## Installation ##

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Follow the usage instructions to begin switching between plugin versions.

## Frequently Asked Questions ##

### Does this work with themes? ###

Currently, LocalDev Switcher is designed for plugins only.

### What happens if I don't have a `localdev-{plugin-slug}` version? ###

LocalDev Switcher will default to using the version-controlled plugin.

## Screenshots ##

1. Adds UI to show which plugins you can switch between VCS and Local.
2. Toggle between VCS and Local versions of your plugins in the Plugins list.

## Changelog ##

### 0.7.0 ###
* Adding `update-assets.yml` action for future `Tested up to` edits without a full deploy.

### 0.6.9 ###
* Adding proper perms for uploading ZIP to GitHub release.

### 0.6.8 ###
* Updating deploy action to follow latest conventions for `10up/action-wordpress-plugin-deploy`.
* Adding WordPress.org Plugin `/assets/` via `/.wordpress-org/`.

### 0.6.7 ###
* Updating deploy action `10up/action-wordpress-plugin-deploy` to `v1.4.0` to support our current, simple deploy workflow.

### 0.6.6 ###
* Fixing deploy action version

### 0.6.5 ###
* Releasing on [WordPress.org Plugins](https://wordpress.org/plugins/localdev-switcher/).

### 0.6.4 ###
* Updating method for finding plugins within `/plugins/`.
* Updating "Usage" documentation for clarity.

### 0.6.3 ###
* First public release. Toggle between VCS and local plugin versions via `localdev-{plugin-slug}` pattern.

## Upgrade Notice ##

### 0.6.3 ###
Initial release.

