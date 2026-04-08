<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the _Lingua_language post meta.
 *
 * Every post that participates in a translation group stores its language code
 * (e.g. "ko", "en", "ja") in this meta field.
 */
class Lingua_Post_Meta {

	const META_KEY = '_Lingua_language';

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	public function register_meta() {
		$post_types = Lingua_Taxonomy::get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
					'show_in_rest'      => true,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Get the language code of a post.
	 *
	 * @param int $post_id
	 * @return string Language code or empty string.
	 */
	public static function get_language( $post_id ) {
		return (string) get_post_meta( $post_id, self::META_KEY, true );
	}

	/**
	 * Set the language code of a post.
	 *
	 * @param int    $post_id
	 * @param string $language_code e.g. "ko", "en", "ja".
	 * @return bool
	 */
	public static function set_language( $post_id, $language_code ) {
		return (bool) update_post_meta( $post_id, self::META_KEY, sanitize_text_field( $language_code ) );
	}
}
