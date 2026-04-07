<?php
/**
 * Plugin Name: Lingua Shared
 * Plugin URI: https://github.com/pressento/lingua-shared
 * Description: Shared primitives (taxonomy and post meta) used by WP Lingua and other Lingua-family plugins.
 * Version: 1.0.0
 * Author: Jung Hyun, Nam
 * Author URI: https://github.com/rkttu
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Requires at least: 6.1
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Text Domain: lingua-shared
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LINGUA_SHARED_VERSION', '1.0.0' );
define( 'LINGUA_SHARED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Guard against double-registration when multiple Lingua-family plugins are active.
// Both classes are loaded as a single unit to avoid partial-load issues.
if ( ! defined( 'LINGUA_SHARED_LOADED' ) ) {
	define( 'LINGUA_SHARED_LOADED', true );

	require_once LINGUA_SHARED_PLUGIN_DIR . 'includes/class-taxonomy.php';
	require_once LINGUA_SHARED_PLUGIN_DIR . 'includes/class-post-meta.php';

	$lingua_shared_taxonomy = new Lingua_Taxonomy();
	$lingua_shared_taxonomy->register_hooks();

	$lingua_shared_post_meta = new Lingua_Post_Meta();
	$lingua_shared_post_meta->register_hooks();
}
