<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Pressento_group taxonomy used to link translated posts together.
 *
 * Each translation group is represented as a single term. All posts that are
 * translations of each other share the same term in this taxonomy.
 */
class Pressento_Taxonomy {

	const TAXONOMY = 'Pressento_group';

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	public function register_taxonomy() {
		$post_types = self::get_supported_post_types();

		register_taxonomy(
			self::TAXONOMY,
			$post_types,
			array(
				'labels'            => array(
					'name'          => __( 'Translation Groups', 'pressento-shared' ),
					'singular_name' => __( 'Translation Group', 'pressento-shared' ),
				),
				'public'            => false,
				'show_ui'           => false,
				'show_in_rest'      => false,
				'hierarchical'      => false,
				'show_admin_column' => false,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	/**
	 * Returns the post types that support multilingual features.
	 *
	 * @return string[]
	 */
	public static function get_supported_post_types() {
		$defaults = array( 'post', 'page' );
		return apply_filters( 'Pressento_supported_post_types', $defaults );
	}
}
