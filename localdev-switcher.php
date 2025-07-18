<?php
/**
 * Plugin Name: LocalDev Switcher
 * Description: Indicates when local development versions of plugins are present in /localdev/plugins/.
 * Version: 0.1.0
 * Author: Wenmark Digital
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class LocalDevSwitcher {

  /**
   * Path to local plugins directory.
   *
   * @var string
   */
  private $local_plugins_dir;

  /**
   * Local plugin slugs.
   *
   * @var array
   */
  private $local_plugin_slugs = array();

  /**
   * Constructor.
   */
  public function __construct() {
    $this->local_plugins_dir = trailingslashit( $_SERVER['DOCUMENT_ROOT'] ) . 'localdev/plugins/';
    
    add_action( 'admin_init', array( $this, 'detect_local_plugins' ) );
    add_filter( 'plugin_row_meta', array( $this, 'add_local_indicator' ), 10, 2 );
    add_action( 'admin_notices', array( $this, 'maybe_show_missing_localdev_notice' ) );
  }

  /**
   * Scan /localdev/plugins/ for available plugin folders.
   */
  public function detect_local_plugins() {
    if ( ! is_dir( $this->local_plugins_dir ) ) {
      return;
    }

    $folders = scandir( $this->local_plugins_dir );

    foreach ( $folders as $folder ) {
      if ( $folder === '.' || $folder === '..' ) {
        continue;
      }

      if ( is_dir( $this->local_plugins_dir . $folder ) ) {
        $this->local_plugin_slugs[] = $folder;
      }
    }
  }

  /**
   * Add meta row indicator for local plugins.
   *
   * @param array  $links Existing plugin meta links.
   * @param string $file  Plugin file.
   * @return array Modified links.
   */
  public function add_local_indicator( $links, $file ) {

    // Extract the plugin slug from the file path.
    $plugin_slug = dirname( $file );

    if ( in_array( $plugin_slug, $this->local_plugin_slugs, true ) ) {

      $indicator = '<span style="padding:2px 8px; background:#0073aa; color:#fff; border-radius:10px; font-size:11px;">LOCAL</span> ';
      $path = esc_html( '/localdev/plugins/' . $plugin_slug );

      array_unshift( $links, $indicator . $path );
    }

    return $links;
  }

  /**
   * Display admin notice if /localdev/ folder does not exist.
   */
  public function maybe_show_missing_localdev_notice() {
    $screen = get_current_screen();

    if ( $screen && $screen->id === 'plugins' && ! is_dir( trailingslashit( $_SERVER['DOCUMENT_ROOT'] ) . 'localdev' ) ) {
      echo '<div class="notice notice-info"><p>';
      echo '<strong>LocalDev Switcher:</strong> No <code>/localdev/</code> folder found in your web root directory.<br>'; 
      echo 'To set up local development plugins, create the following folder structure in your project web root:<br>'; 
      echo '<code>/localdev/plugins/</code> and place your development versions of plugins inside.<br>'; 
      echo 'For example: <code>/localdev/plugins/my-plugin/</code>';
      echo '</p></div>';
    }
  }

}

new LocalDevSwitcher();
