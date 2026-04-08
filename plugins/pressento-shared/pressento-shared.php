<?php
/**
 * Plugin Name: Pressento Shared
 * Plugin URI: https://github.com/pressento/pressento-shared
 * Description: Shared primitives (taxonomy and post meta) used by WP Lingua and other Pressento-family plugins.
 * Version: 1.0.0
 * Author: Jung Hyun, Nam
 * Author URI: https://github.com/rkttu
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Requires at least: 6.1
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Text Domain: pressento-shared
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard against double-registration when multiple Pressento-family plugins are active.
// Both classes are loaded as a single unit to avoid partial-load issues.
if ( ! defined( 'PRESSENTO_SHARED_LOADED' ) ) {
	define( 'PRESSENTO_SHARED_LOADED', true );
	define( 'PRESSENTO_SHARED_VERSION', '1.0.0' );
	define( 'PRESSENTO_SHARED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	require_once PRESSENTO_SHARED_PLUGIN_DIR . 'includes/class-taxonomy.php';
	require_once PRESSENTO_SHARED_PLUGIN_DIR . 'includes/class-post-meta.php';

	$pressento_shared_taxonomy = new Pressento_Taxonomy();
	$pressento_shared_taxonomy->register_hooks();

	$pressento_shared_post_meta = new Pressento_Post_Meta();
	$pressento_shared_post_meta->register_hooks();

	add_action(
		'init',
		function () {
			load_plugin_textdomain( 'pressento-shared', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}
	);
}
