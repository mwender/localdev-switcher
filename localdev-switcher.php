<?php
/**
 * Plugin Name: LocalDev Switcher
 * Plugin URI: https://wordpress.org/plugins/localdev-switcher/
 * Description: Toggle between VCS and local development versions of plugins using the localdev-{plugin-slug} pattern. Place local development versions in wp-content/plugins/localdev-{plugin-slug} and use this plugin to toggle between versions from the Plugins screen.
 * Version: 0.8.0
 * Author: Michael Wender
 * Author URI: https://mwender.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Tested up to: 6.9.0
 * Requires PHP: 8.1
 * Tags: development, plugins, local development, workflow
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class LocalDevSwitcher {

  /**
   * Prefix for local development versions.
   *
   * @var string
   */
  private $local_prefix = 'localdev-';

  /**
   * This plugin slug.
   *
   * @var string
   */
  private $self_slug = 'localdev-switcher';

  /**
   * Base slugs that have BOTH VCS and local plugin versions.
   *
   * @var array
   */
  private $plugin_pairs = array();

  /**
   * Base slugs that have BOTH VCS and local theme versions.
   *
   * @var array
   */
  private $theme_pairs = array();

  /**
   * Constructor.
   */
  public function __construct() {
    add_action( 'admin_init', array( $this, 'detect_pairs' ) );

    add_action( 'admin_init', array( $this, 'handle_plugin_toggle' ) );
    add_action( 'admin_init', array( $this, 'handle_theme_toggle' ) );

    add_filter( 'plugin_row_meta', array( $this, 'add_plugin_indicator' ), 10, 2 );
    add_filter( 'all_plugins', array( $this, 'filter_plugins_list' ), 20 );

    add_filter( 'wp_prepare_themes_for_js', array( $this, 'filter_themes_list' ), 20 );

    // Reliable UI: banner on Appearance > Themes (active theme only).
    add_action( 'admin_notices', array( $this, 'render_theme_toggle_notice' ) );
  }

  /**
   * Get overrides option with defaults.
   *
   * @return array
   */
  private function get_overrides() {
    return wp_parse_args(
      get_option( 'localdev_switcher_overrides', array() ),
      array(
        'plugins' => array(),
        'themes'  => array(),
      )
    );
  }

  /**
   * Save overrides option.
   *
   * @param array $overrides Overrides array.
   * @return void
   */
  private function save_overrides( $overrides ) {
    update_option( 'localdev_switcher_overrides', $overrides );
  }

  /**
   * Detect plugin + theme pairs where BOTH VCS and localdev exist.
   *
   * @return void
   */
  public function detect_pairs() {
    $this->plugin_pairs = $this->detect_plugin_pairs();
    $this->theme_pairs  = $this->detect_theme_pairs();
  }

  /**
   * Detect plugin pairs (base slug) that have BOTH a VCS and localdev version.
   *
   * @return array
   */
  private function detect_plugin_pairs() {
    $plugins   = get_plugins();
    $by_dir    = array();

    foreach ( $plugins as $plugin_file => $plugin_data ) {
      $dir = dirname( $plugin_file );

      if ( empty( $by_dir[ $dir ] ) ) {
        $by_dir[ $dir ] = array();
      }

      $by_dir[ $dir ][] = $plugin_file;
    }

    $pairs = array();

    foreach ( $by_dir as $dir => $files ) {
      if ( strpos( $dir, $this->local_prefix ) === 0 ) {
        $base = substr( $dir, strlen( $this->local_prefix ) );

        if ( isset( $by_dir[ $base ] ) ) {
          $pairs[] = $base;
        }
      }
    }

    $pairs = array_values( array_unique( array_filter( $pairs ) ) );

    return $pairs;
  }

  /**
   * Detect theme pairs (base slug) that have BOTH a VCS and localdev version.
   *
   * @return array
   */
  private function detect_theme_pairs() {
    $themes = wp_get_themes();
    $pairs  = array();

    foreach ( $themes as $stylesheet => $theme ) {
      if ( strpos( $stylesheet, $this->local_prefix ) === 0 ) {
        $base = substr( $stylesheet, strlen( $this->local_prefix ) );

        if ( isset( $themes[ $base ] ) ) {
          $pairs[] = $base;
        }
      }
    }

    $pairs = array_values( array_unique( array_filter( $pairs ) ) );

    return $pairs;
  }

  /**
   * Handle plugin toggling between VCS and localdev versions.
   *
   * @return void
   */
  public function handle_plugin_toggle() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
      return;
    }

    if ( empty( $_GET['localdev_toggle'] ) || empty( $_GET['_wpnonce'] ) ) {
      return;
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'localdev_toggle' ) ) {
      return;
    }

    $plugin_slug = sanitize_text_field( wp_unslash( $_GET['localdev_toggle'] ) );

    // Only allow toggling for known pairs.
    if ( ! in_array( $plugin_slug, $this->plugin_pairs, true ) ) {
      wp_redirect( admin_url( 'plugins.php' ) );
      exit;
    }

    $overrides      = $this->get_overrides();
    $all_plugins    = get_plugins();
    $active_plugins = get_option( 'active_plugins', array() );

    $local_slug = $this->local_prefix . $plugin_slug;

    // Find plugin files (main file) for each directory.
    $vcs_plugin_file   = '';
    $local_plugin_file = '';

    foreach ( $all_plugins as $file => $data ) {
      if ( dirname( $file ) === $plugin_slug ) {
        $vcs_plugin_file = $file;
      }
      if ( dirname( $file ) === $local_slug ) {
        $local_plugin_file = $file;
      }
    }

    if ( empty( $vcs_plugin_file ) || empty( $local_plugin_file ) ) {
      wp_redirect( admin_url( 'plugins.php' ) );
      exit;
    }

    $is_local = in_array( $plugin_slug, $overrides['plugins'], true );

    if ( $is_local ) {
      // Switch to VCS.
      $overrides['plugins'] = array_values( array_diff( $overrides['plugins'], array( $plugin_slug ) ) );

      $active_plugins = array_map(
        function ( $plugin ) use ( $local_plugin_file, $vcs_plugin_file ) {
          return ( $plugin === $local_plugin_file ) ? $vcs_plugin_file : $plugin;
        },
        $active_plugins
      );
    } else {
      // Switch to Local.
      $overrides['plugins'][] = $plugin_slug;
      $overrides['plugins']   = array_values( array_unique( $overrides['plugins'] ) );

      $active_plugins = array_map(
        function ( $plugin ) use ( $local_plugin_file, $vcs_plugin_file ) {
          return ( $plugin === $vcs_plugin_file ) ? $local_plugin_file : $plugin;
        },
        $active_plugins
      );
    }

    $this->save_overrides( $overrides );
    update_option( 'active_plugins', $active_plugins );

    wp_redirect( admin_url( 'plugins.php' ) );
    exit;
  }

  /**
   * Adds local indicator and toggle link to plugin meta rows.
   *
   * @param array  $links Existing meta links.
   * @param string $file  Plugin file.
   * @return array
   */
  public function add_plugin_indicator( $links, $file ) {
    $plugin_slug = dirname( $file );

    // Never modify this plugin row.
    if ( $plugin_slug === $this->self_slug ) {
      return $links;
    }

    // Only show UI for known pairs.
    foreach ( $this->plugin_pairs as $base_slug ) {
      $local_slug = $this->local_prefix . $base_slug;

      if ( $plugin_slug !== $base_slug && $plugin_slug !== $local_slug ) {
        continue;
      }

      $overrides = $this->get_overrides();
      $is_local  = in_array( $base_slug, $overrides['plugins'], true );

      $indicator = $is_local
        ? '<span style="padding:2px 8px; background:#00aa00; color:#fff; border-radius:10px; font-size:11px;">LOCAL ACTIVE</span> '
        : '<span style="padding:2px 8px; background:#0073aa; color:#fff; border-radius:10px; font-size:11px;">VCS ACTIVE</span> ';

      $toggle_url   = wp_nonce_url( add_query_arg( 'localdev_toggle', $base_slug ), 'localdev_toggle' );
      $toggle_label = $is_local ? 'Switch to VCS' : 'Switch to Local';

      array_unshift( $links, $indicator . '| <a href="' . esc_url( $toggle_url ) . '">' . esc_html( $toggle_label ) . '</a>' );
      break;
    }

    return $links;
  }

  /**
   * Filter the plugins list to prevent double listing.
   * Only hides when BOTH versions exist.
   *
   * @param array $plugins All plugins.
   * @return array
   */
  public function filter_plugins_list( $plugins ) {
    $overrides = $this->get_overrides();

    foreach ( $plugins as $plugin_file => $plugin_data ) {
      $slug = dirname( $plugin_file );

      if ( $slug === $this->self_slug ) {
        continue;
      }

      foreach ( $this->plugin_pairs as $base_slug ) {
        $local_slug = $this->local_prefix . $base_slug;

        // If VCS is active, hide localdev listing.
        if ( $slug === $local_slug && ! in_array( $base_slug, $overrides['plugins'], true ) ) {
          unset( $plugins[ $plugin_file ] );
          continue 2;
        }

        // If localdev is active, hide VCS listing.
        if ( $slug === $base_slug && in_array( $base_slug, $overrides['plugins'], true ) ) {
          unset( $plugins[ $plugin_file ] );
          continue 2;
        }
      }
    }

    return $plugins;
  }

  /**
   * Handle theme toggling between VCS and localdev versions.
   *
   * @return void
   */
  public function handle_theme_toggle() {
    if ( ! current_user_can( 'switch_themes' ) ) {
      return;
    }

    if ( empty( $_GET['localdev_theme_toggle'] ) || empty( $_GET['_wpnonce'] ) ) {
      return;
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'localdev_theme_toggle' ) ) {
      return;
    }

    $base_slug = sanitize_text_field( wp_unslash( $_GET['localdev_theme_toggle'] ) );

    // Only allow toggling for known pairs.
    if ( ! in_array( $base_slug, $this->theme_pairs, true ) ) {
      wp_redirect( admin_url( 'themes.php' ) );
      exit;
    }

    $themes = wp_get_themes();

    $vcs_exists   = isset( $themes[ $base_slug ] );
    $local_exists = isset( $themes[ $this->local_prefix . $base_slug ] );

    if ( ! $vcs_exists || ! $local_exists ) {
      wp_redirect( admin_url( 'themes.php' ) );
      exit;
    }

    $overrides = $this->get_overrides();
    $is_local  = in_array( $base_slug, $overrides['themes'], true );

    if ( $is_local ) {
      $overrides['themes'] = array_values( array_diff( $overrides['themes'], array( $base_slug ) ) );
      $target              = $base_slug;
    } else {
      $overrides['themes'][] = $base_slug;
      $overrides['themes']   = array_values( array_unique( $overrides['themes'] ) );
      $target                = $this->local_prefix . $base_slug;
    }

    $this->save_overrides( $overrides );
    switch_theme( $target );

    wp_redirect( admin_url( 'themes.php' ) );
    exit;
  }

  /**
   * Filter themes list to prevent double listing (hide inactive twin).
   *
   * @param array $themes Themes prepared for JS.
   * @return array
   */
  public function filter_themes_list( $themes ) {
    $overrides    = $this->get_overrides();
    $active_theme = get_stylesheet();

    foreach ( $themes as $stylesheet => $theme_data ) {
      foreach ( $this->theme_pairs as $base_slug ) {
        $local_slug = $this->local_prefix . $base_slug;

        // Hide local theme if VCS is active.
        if (
          $stylesheet === $local_slug &&
          ! in_array( $base_slug, $overrides['themes'], true ) &&
          $active_theme !== $local_slug
        ) {
          unset( $themes[ $stylesheet ] );
          continue 2;
        }

        // Hide VCS theme if local is active.
        if (
          $stylesheet === $base_slug &&
          in_array( $base_slug, $overrides['themes'], true ) &&
          $active_theme !== $base_slug
        ) {
          unset( $themes[ $stylesheet ] );
          continue 2;
        }
      }
    }

    return $themes;
  }

  /**
   * Render theme toggle UI as an admin notice on themes.php (active theme only).
   *
   * @return void
   */
  public function render_theme_toggle_notice() {
    if ( ! is_admin() ) {
      return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( empty( $screen ) || 'themes' !== $screen->id ) {
      return;
    }

    if ( ! current_user_can( 'switch_themes' ) ) {
      return;
    }

    $active = get_stylesheet();

    // Determine base slug if active is localdev- prefixed.
    $base_slug = $active;

    if ( strpos( $active, $this->local_prefix ) === 0 ) {
      $base_slug = substr( $active, strlen( $this->local_prefix ) );
    }

    // Only show notice for known pairs (active theme must be part of a pair).
    if ( ! in_array( $base_slug, $this->theme_pairs, true ) ) {
      return;
    }

    $overrides = $this->get_overrides();
    $is_local  = in_array( $base_slug, $overrides['themes'], true );

    $badge_text  = $is_local ? 'LOCAL ACTIVE' : 'VCS ACTIVE';
    $badge_bg    = $is_local ? '#00aa00' : '#0073aa';
    $toggle_text = $is_local ? 'Switch to VCS' : 'Switch to Local';

    $toggle_url = wp_nonce_url(
      add_query_arg(
        array(
          'localdev_theme_toggle' => $base_slug,
        ),
        admin_url( 'themes.php' )
      ),
      'localdev_theme_toggle'
    );

    ?>
    <div class="notice notice-info" style="display:flex; align-items:center; gap:10px;">
      <p style="margin:8px 0;">
        <strong>LocalDev Switcher:</strong>
        <span style="padding:2px 8px; background:<?php echo esc_attr( $badge_bg ); ?>; color:#fff; border-radius:10px; font-size:11px; margin-left:6px;">
          <?php echo esc_html( $badge_text ); ?>
        </span>
        <a href="<?php echo esc_url( $toggle_url ); ?>" style="margin-left:10px;">
          <?php echo esc_html( $toggle_text ); ?>
        </a>
        <span style="margin-left:10px; opacity:0.8;">
          (Active theme: <?php echo esc_html( $base_slug ); ?>)
        </span>
      </p>
    </div>
    <?php
  }
}

new LocalDevSwitcher();
