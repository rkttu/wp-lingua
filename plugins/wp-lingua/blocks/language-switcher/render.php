<?php
/**
 * Server-side rendering for the lingua/language-switcher block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$style = isset( $attributes['style'] ) ? $attributes['style'] : 'dropdown';

if ( 'buttons' === $style ) {
	$output = Lingua_Widget_Switcher::render_global_switcher();
} else {
	$output = Lingua_Frontend::render_dropdown_switcher();
}

$wrapper_attributes = get_block_wrapper_attributes();

printf( '<div %s>%s</div>', $wrapper_attributes, $output );
