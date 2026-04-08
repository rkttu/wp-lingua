<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core logic for managing translation groups.
 *
 * A "translation group" is a single term in the Pressento_group taxonomy.
 * All posts assigned to the same term are considered translations of each other.
 */
class Lingua_Translation_Group {

	/**
	 * Get or create the translation group term for a post.
	 *
	 * If the post is not yet in any group a new term is created automatically.
	 *
	 * @param int $post_id
	 * @return int|WP_Error Term ID on success, WP_Error on failure.
	 */
	public static function get_or_create_group( $post_id ) {
		$terms = wp_get_object_terms( $post_id, Pressento_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		if ( ! empty( $terms ) ) {
			return (int) $terms[0];
		}

		// Create a new group term with a unique slug.
		$slug   = 'Lingua_' . $post_id . '_' . wp_generate_password( 6, false );
		$result = wp_insert_term( $slug, Pressento_Taxonomy::TAXONOMY, array( 'slug' => $slug ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = (int) $result['term_id'];

		wp_set_object_terms( $post_id, $term_id, Pressento_Taxonomy::TAXONOMY );

		return $term_id;
	}

	/**
	 * Add a post to an existing translation group.
	 *
	 * @param int $post_id
	 * @param int $group_term_id
	 * @return bool|WP_Error
	 */
	public static function add_to_group( $post_id, $group_term_id ) {
		$result = wp_set_object_terms( $post_id, $group_term_id, Pressento_Taxonomy::TAXONOMY );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Remove a post from its translation group.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function remove_from_group( $post_id ) {
		wp_set_object_terms( $post_id, array(), Pressento_Taxonomy::TAXONOMY );
		return true;
	}

	/**
	 * Get all translations in the same group as the given post.
	 *
	 * Returns an associative array keyed by language code.
	 * The given post itself is included in the result.
	 *
	 * @param int $post_id
	 * @return array<string, WP_Post> Language code => WP_Post.
	 */
	public static function get_translations( $post_id ) {
		$terms = wp_get_object_terms( $post_id, Pressento_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			// Return only this post if it has a language set.
			$lang = Pressento_Post_Meta::get_language( $post_id );
			if ( $lang ) {
				return array( $lang => get_post( $post_id ) );
			}
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'tax_query'      => array(
					array(
						'taxonomy' => Pressento_Taxonomy::TAXONOMY,
						'terms'    => (int) $terms[0],
					),
				),
			)
		);

		$translations = array();
		foreach ( $posts as $post ) {
			$lang = Pressento_Post_Meta::get_language( $post->ID );
			if ( $lang ) {
				$translations[ $lang ] = $post;
			}
		}

		return $translations;
	}

	/**
	 * Get the translation of a post for a specific language.
	 *
	 * @param int    $post_id
	 * @param string $language_code
	 * @return WP_Post|null
	 */
	public static function get_translation( $post_id, $language_code ) {
		$translations = self::get_translations( $post_id );
		return isset( $translations[ $language_code ] ) ? $translations[ $language_code ] : null;
	}

	/**
	 * Check whether a language already exists in the post's translation group.
	 *
	 * @param int    $post_id
	 * @param string $language_code
	 * @return bool
	 */
	public static function has_translation( $post_id, $language_code ) {
		return null !== self::get_translation( $post_id, $language_code );
	}

	/**
	 * Create a new draft post as a translation in the group.
	 *
	 * The new post inherits the original post's type and is automatically
	 * assigned to the same translation group.
	 *
	 * @param int    $source_post_id  The post to translate from.
	 * @param string $language_code   Target language code.
	 * @return int|WP_Error           New post ID on success.
	 */
	public static function create_translation( $source_post_id, $language_code ) {
		$source = get_post( $source_post_id );
		if ( ! $source ) {
			return new WP_Error( 'invalid_post', __( 'Source post not found.', 'wp-lingua' ) );
		}

		if ( self::has_translation( $source_post_id, $language_code ) ) {
			return new WP_Error( 'duplicate_language', __( 'A translation for this language already exists.', 'wp-lingua' ) );
		}

		$group_id = self::get_or_create_group( $source_post_id );
		if ( is_wp_error( $group_id ) ) {
			return $group_id;
		}

		// Ensure the source post also has its language set.
		$source_lang = Pressento_Post_Meta::get_language( $source_post_id );
		if ( ! $source_lang ) {
			$default = Lingua_Languages::get_default_language();
			Pressento_Post_Meta::set_language( $source_post_id, $default );
		}

		$new_post_id = wp_insert_post(
			array(
				'post_type'   => $source->post_type,
				'post_status' => 'draft',
				'post_title'  => sprintf(
					/* translators: 1: original title, 2: language code */
					__( '[%2$s] %1$s', 'wp-lingua' ),
					$source->post_title,
					strtoupper( $language_code )
				),
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		Pressento_Post_Meta::set_language( $new_post_id, $language_code );
		self::add_to_group( $new_post_id, $group_id );

		do_action( 'Lingua_translation_created', $new_post_id, $source_post_id, $language_code );

		return $new_post_id;
	}
}
