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
    $this->local_plugins_dir = trailingslashit( ABSPATH ) . 'localdev/plugins/';
    
    add_action( 'admin_init', array( $this, 'detect_local_plugins' ) );
    add_filter( 'plugin_row_meta', array( $this, 'add_local_indicator' ), 10, 2 );
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

}

new LocalDevSwitcher();
