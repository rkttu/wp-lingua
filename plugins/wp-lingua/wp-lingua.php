<?php
/**
 * Plugin Name: WP Lingua
 * Plugin URI: https://github.com/rkttu/wp-lingua
 * Description: A multilingual plugin for WordPress using custom taxonomy to group translated posts.
 * Version: 0.5.0
 * Author: Jung Hyun, Nam
 * Author URI: https://github.com/rkttu
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Requires at least: 6.1
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Text Domain: wp-lingua
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_LINGUA_VERSION', '0.5.0' );
define( 'WP_LINGUA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_LINGUA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load includes.
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-languages.php';
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-settings.php';
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-taxonomy.php';
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-post-meta.php';
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-translation-group.php';
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-frontend.php';
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-locale-switcher.php';
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-widget-switcher.php';
require_once WP_LINGUA_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Plugin initialization.
 */
function WP_LINGUA_init() {
	load_plugin_textdomain( 'wp-lingua', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Register the Gutenberg block.
	register_block_type( WP_LINGUA_PLUGIN_DIR . 'blocks/language-switcher' );
}
add_action( 'init', 'WP_LINGUA_init' );

/**
 * Enqueue frontend styles.
 */
function WP_LINGUA_enqueue_styles() {
	wp_enqueue_style(
		'lingua-switcher',
		WP_LINGUA_PLUGIN_URL . 'assets/css/switcher.css',
		array(),
		WP_LINGUA_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'WP_LINGUA_enqueue_styles' );

/**
 * Register the language switcher widget.
 */
function WP_LINGUA_register_widgets() {
	register_widget( 'Lingua_Widget_Switcher' );
}
add_action( 'widgets_init', 'WP_LINGUA_register_widgets' );

/**
 * Add a "Settings" link on the Plugins list page.
 */
function WP_LINGUA_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=lingua-settings' ) ),
		esc_html__( 'Settings', 'wp-lingua' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'WP_LINGUA_plugin_action_links' );

/**
 * Override "View details" link to open the GitHub repository in a new tab.
 */
function wp_lingua_plugin_row_meta( $plugin_meta, $plugin_file ) {
	if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
		return $plugin_meta;
	}

	foreach ( $plugin_meta as $i => $meta ) {
		if ( strpos( $meta, 'plugin-install.php?tab=plugin-information' ) !== false ) {
			$plugin_meta[ $i ] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				'https://github.com/rkttu/wp-lingua',
				esc_html__( 'View details', 'wp-lingua' )
			);
		}
	}

	return $plugin_meta;
}
add_filter( 'plugin_row_meta', 'wp_lingua_plugin_row_meta', 10, 2 );

// Bootstrap components.
$Lingua_taxonomy  = new Lingua_Taxonomy();
$Lingua_taxonomy->register_hooks();

$Lingua_post_meta = new Lingua_Post_Meta();
$Lingua_post_meta->register_hooks();

$Lingua_frontend = new Lingua_Frontend();
$Lingua_frontend->register_hooks();

$Lingua_locale_switcher = new Lingua_Locale_Switcher();
$Lingua_locale_switcher->register_hooks();

if ( is_admin() ) {
	$Lingua_admin = new Lingua_Admin();
	$Lingua_admin->register_hooks();

	$Lingua_settings = new Lingua_Settings();
	$Lingua_settings->register_hooks();
}
