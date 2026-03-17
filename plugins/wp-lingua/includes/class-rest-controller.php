<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller for managing Lingua translation groups.
 *
 * Provides endpoints to link/unlink posts as translations and query
 * translation groups without exposing the underlying taxonomy directly.
 *
 * Namespace: lingua/v1
 *
 * Endpoints:
 *   POST   /lingua/v1/link                  – Link posts into a translation group.
 *   DELETE  /lingua/v1/unlink/{post_id}      – Remove a post from its group.
 *   GET    /lingua/v1/translations/{post_id} – Get all translations for a post.
 */
class Lingua_REST_Controller {

	const NAMESPACE = 'lingua/v1';

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/link',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'link_translations' ),
				'permission_callback' => array( $this, 'edit_permission_check' ),
				'args'                => array(
					'post_ids' => array(
						'description' => __( 'Array of post IDs to link, or an object mapping language codes to post IDs.', 'wp-lingua' ),
						'required'    => true,
						'validate_callback' => array( $this, 'validate_post_ids' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/unlink/(?P<post_id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unlink_translation' ),
				'permission_callback' => array( $this, 'edit_permission_check' ),
				'args'                => array(
					'post_id' => array(
						'description'       => __( 'Post ID to remove from its translation group.', 'wp-lingua' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/translations/(?P<post_id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_translations' ),
				'permission_callback' => array( $this, 'read_permission_check' ),
				'args'                => array(
					'post_id' => array(
						'description'       => __( 'Post ID to get translations for.', 'wp-lingua' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Permission check: user must be able to edit posts.
	 */
	public function edit_permission_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check: user must be able to read posts.
	 */
	public function read_permission_check( $request ) {
		return current_user_can( 'read' );
	}

	/**
	 * Validate the post_ids parameter.
	 *
	 * Accepts either:
	 * - A plain array of post IDs: [10, 20, 30]
	 * - An object mapping language codes to post IDs: {"ko": 10, "en": 20}
	 */
	public function validate_post_ids( $value, $request, $param ) {
		if ( is_array( $value ) && ! empty( $value ) ) {
			return true;
		}
		if ( is_object( $value ) ) {
			return true;
		}
		return new WP_Error(
			'rest_invalid_param',
			__( 'post_ids must be a non-empty array of IDs or an object mapping language codes to IDs.', 'wp-lingua' )
		);
	}

	/**
	 * POST /lingua/v1/link
	 *
	 * Accepts two formats:
	 *
	 * 1) Plain array – posts must already have _Lingua_language meta set:
	 *    { "post_ids": [10, 20, 30] }
	 *
	 * 2) Language map – sets language meta automatically:
	 *    { "post_ids": { "ko": 10, "en": 20, "ja": 30 } }
	 */
	public function link_translations( $request ) {
		$raw = $request->get_param( 'post_ids' );

		// Normalise into [ lang => post_id ] map.
		$posts_map = $this->normalise_post_ids( $raw );

		if ( is_wp_error( $posts_map ) ) {
			return $posts_map;
		}

		if ( count( $posts_map ) < 2 ) {
			return new WP_Error(
				'too_few_posts',
				__( 'At least two posts are required to form a translation group.', 'wp-lingua' ),
				array( 'status' => 400 )
			);
		}

		// Validate that all posts exist and are editable.
		foreach ( $posts_map as $lang => $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post ) {
				return new WP_Error(
					'invalid_post',
					/* translators: %d: post ID */
					sprintf( __( 'Post %d not found.', 'wp-lingua' ), $post_id ),
					array( 'status' => 404 )
				);
			}
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				return new WP_Error(
					'forbidden',
					/* translators: %d: post ID */
					sprintf( __( 'You do not have permission to edit post %d.', 'wp-lingua' ), $post->ID ),
					array( 'status' => 403 )
				);
			}
		}

		// Check for duplicate languages.
		$languages = array_keys( $posts_map );
		if ( count( $languages ) !== count( array_unique( $languages ) ) ) {
			return new WP_Error(
				'duplicate_language',
				__( 'Each language code must be unique within the group.', 'wp-lingua' ),
				array( 'status' => 400 )
			);
		}

		// Determine the group: use existing group of the first post, or create one.
		$first_post_id = (int) reset( $posts_map );
		$group_term_id = Lingua_Translation_Group::get_or_create_group( $first_post_id );

		if ( is_wp_error( $group_term_id ) ) {
			return $group_term_id;
		}

		// Set language meta and add each post to the group.
		$linked = array();
		foreach ( $posts_map as $lang => $post_id ) {
			$post_id = (int) $post_id;

			Lingua_Post_Meta::set_language( $post_id, $lang );

			$result = Lingua_Translation_Group::add_to_group( $post_id, $group_term_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$linked[ $lang ] = $post_id;
		}

		return rest_ensure_response(
			array(
				'group_term_id' => $group_term_id,
				'linked'        => $linked,
			)
		);
	}

	/**
	 * DELETE /lingua/v1/unlink/{post_id}
	 */
	public function unlink_translation( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'invalid_post',
				__( 'Post not found.', 'wp-lingua' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have permission to edit this post.', 'wp-lingua' ),
				array( 'status' => 403 )
			);
		}

		Lingua_Translation_Group::remove_from_group( $post_id );

		return rest_ensure_response(
			array(
				'unlinked' => $post_id,
			)
		);
	}

	/**
	 * GET /lingua/v1/translations/{post_id}
	 */
	public function get_translations( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'invalid_post',
				__( 'Post not found.', 'wp-lingua' ),
				array( 'status' => 404 )
			);
		}

		$translations = Lingua_Translation_Group::get_translations( $post_id );

		$data = array();
		foreach ( $translations as $lang => $translated_post ) {
			$data[ $lang ] = array(
				'post_id' => $translated_post->ID,
				'title'   => $translated_post->post_title,
				'status'  => $translated_post->post_status,
				'link'    => get_permalink( $translated_post->ID ),
			);
		}

		return rest_ensure_response(
			array(
				'post_id'      => $post_id,
				'translations' => $data,
			)
		);
	}

	/**
	 * Normalise the post_ids input into an associative array [ lang => post_id ].
	 *
	 * @param mixed $raw
	 * @return array|WP_Error
	 */
	private function normalise_post_ids( $raw ) {
		// Object / associative array: { "ko": 10, "en": 20 }
		$raw = (array) $raw;

		if ( $this->is_language_map( $raw ) ) {
			$map = array();
			foreach ( $raw as $lang => $id ) {
				$lang = sanitize_text_field( $lang );
				$id   = (int) $id;
				if ( $id <= 0 ) {
					return new WP_Error(
						'invalid_post_id',
						/* translators: %s: language code */
						sprintf( __( 'Invalid post ID for language "%s".', 'wp-lingua' ), $lang ),
						array( 'status' => 400 )
					);
				}
				$map[ $lang ] = $id;
			}
			return $map;
		}

		// Sequential array: [10, 20, 30] — look up existing _Lingua_language meta.
		$map = array();
		foreach ( $raw as $id ) {
			$id   = (int) $id;
			if ( $id <= 0 ) {
				return new WP_Error(
					'invalid_post_id',
					__( 'All post_ids must be positive integers.', 'wp-lingua' ),
					array( 'status' => 400 )
				);
			}
			$lang = Lingua_Post_Meta::get_language( $id );
			if ( empty( $lang ) ) {
				return new WP_Error(
					'missing_language',
					/* translators: %d: post ID */
					sprintf( __( 'Post %d does not have a language set. Use the language-map format or set _Lingua_language meta first.', 'wp-lingua' ), $id ),
					array( 'status' => 400 )
				);
			}
			$map[ $lang ] = $id;
		}
		return $map;
	}

	/**
	 * Determine whether the array is a language map (string keys) vs sequential.
	 */
	private function is_language_map( $arr ) {
		if ( empty( $arr ) ) {
			return false;
		}
		foreach ( array_keys( $arr ) as $key ) {
			if ( ! is_int( $key ) ) {
				return true;
			}
		}
		return false;
	}
}
