<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin meta box for managing translations of a post.
 *
 * Shows the current language, existing translations, and an "Add translation"
 * action that creates a new draft in the same translation group.
 */
class Lingua_Admin {

	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_post_Lingua_create_translation', array( $this, 'handle_create_translation' ) );
		add_filter( 'manage_posts_columns', array( $this, 'add_language_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_language_column' ), 10, 2 );
		add_filter( 'manage_pages_columns', array( $this, 'add_language_column' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'render_language_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_language_filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_posts_by_language' ) );
	}

	public function add_meta_box() {
		$post_types = Pressento_Taxonomy::get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'Lingua_translations',
				__( 'Translations', 'wp-lingua' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the translations meta box.
	 *
	 * @param WP_Post $post
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'Lingua_save_language', 'Lingua_nonce' );

		$current_lang  = Pressento_Post_Meta::get_language( $post->ID );
		$languages     = Lingua_Languages::get_available_languages();
		$translations  = Lingua_Translation_Group::get_translations( $post->ID );

		// Language selector for the current post.
		echo '<p><label for="Lingua_language"><strong>' . esc_html__( 'Language', 'wp-lingua' ) . '</strong></label></p>';
		echo '<select id="Lingua_language" name="Lingua_language" style="width:100%">';
		echo '<option value="">' . esc_html__( '— Select —', 'wp-lingua' ) . '</option>';
		foreach ( $languages as $code => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $code ),
				selected( $current_lang, $code, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		// List existing translations.
		if ( count( $translations ) > 1 || ( count( $translations ) === 1 && ! isset( $translations[ $current_lang ] ) ) ) {
			echo '<hr><p><strong>' . esc_html__( 'Linked Translations', 'wp-lingua' ) . '</strong></p>';
			echo '<ul style="margin:0">';
			foreach ( $translations as $lang => $tr_post ) {
				if ( $tr_post->ID === $post->ID ) {
					continue;
				}
				$lang_label = isset( $languages[ $lang ] ) ? $languages[ $lang ] : $lang;
				$edit_link  = get_edit_post_link( $tr_post->ID );
				$status     = get_post_status( $tr_post->ID );
				printf(
					'<li><a href="%s">%s</a> <small>(%s)</small></li>',
					esc_url( $edit_link ),
					esc_html( $lang_label ),
					esc_html( $status )
				);
			}
			echo '</ul>';
		}

		// Add translation buttons.
		if ( $post->ID && $current_lang ) {
			$available = array_diff_key( $languages, $translations );
			if ( ! empty( $available ) ) {
				echo '<hr><p><strong>' . esc_html__( 'Add Translation', 'wp-lingua' ) . '</strong></p>';
				foreach ( $available as $code => $label ) {
					$url = wp_nonce_url(
						admin_url( 'admin-post.php?action=Lingua_create_translation&post_id=' . $post->ID . '&lang=' . $code ),
						'Lingua_create_' . $post->ID . '_' . $code
					);
					printf(
						'<a href="%s" class="button button-small" style="margin:2px 4px 2px 0">+ %s</a>',
						esc_url( $url ),
						esc_html( $label )
					);
				}
			}
		}
	}

	/**
	 * Save the language meta value when the post is saved.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['Lingua_nonce'] ) || ! wp_verify_nonce( $_POST['Lingua_nonce'], 'Lingua_save_language' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post_types = Pressento_Taxonomy::get_supported_post_types();
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		if ( isset( $_POST['Lingua_language'] ) ) {
			$lang = sanitize_text_field( wp_unslash( $_POST['Lingua_language'] ) );
			Pressento_Post_Meta::set_language( $post_id, $lang );

			// Auto-create translation group if language is set.
			if ( $lang ) {
				Lingua_Translation_Group::get_or_create_group( $post_id );
			}
		}
	}

	/**
	 * Handle the "create translation" admin-post action.
	 */
	public function handle_create_translation() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$lang    = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : '';

		if ( ! $post_id || ! $lang ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-lingua' ) );
		}

		check_admin_referer( 'Lingua_create_' . $post_id . '_' . $lang );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-lingua' ) );
		}

		$new_post_id = Lingua_Translation_Group::create_translation( $post_id, $lang );

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html( $new_post_id->get_error_message() ) );
		}

		wp_safe_redirect( get_edit_post_link( $new_post_id, 'raw' ) );
		exit;
	}

	/**
	 * Add a "Language" column to post list tables.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_language_column( $columns ) {
		$columns['Lingua_language'] = __( 'Lang', 'wp-lingua' );
		return $columns;
	}

	/**
	 * Render the language column value.
	 *
	 * @param string $column
	 * @param int    $post_id
	 */
	public function render_language_column( $column, $post_id ) {
		if ( 'Lingua_language' !== $column ) {
			return;
		}

		$lang = Pressento_Post_Meta::get_language( $post_id );
		if ( $lang ) {
			$languages = Lingua_Languages::get_available_languages();
			echo esc_html( isset( $languages[ $lang ] ) ? $lang : $lang );

			// Show count of translations.
			$translations = Lingua_Translation_Group::get_translations( $post_id );
			$count        = count( $translations );
			if ( $count > 1 ) {
				printf( ' <small>(%d)</small>', $count );
			}
		} else {
			echo '<span style="color:#999">—</span>';
		}
	}

	/**
	 * Render a language filter dropdown above the posts list table.
	 *
	 * @param string $post_type
	 */
	public function render_language_filter_dropdown( $post_type ) {
		$post_types = Pressento_Taxonomy::get_supported_post_types();
		if ( ! in_array( $post_type, $post_types, true ) ) {
			return;
		}

		$current   = isset( $_GET['Lingua_lang'] ) ? sanitize_text_field( wp_unslash( $_GET['Lingua_lang'] ) ) : '';
		$languages = Lingua_Languages::get_available_languages();

		echo '<select name="Lingua_lang">';
		echo '<option value="">' . esc_html__( 'All Languages', 'wp-lingua' ) . '</option>';
		foreach ( $languages as $code => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $code ),
				selected( $current, $code, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Filter the admin posts list by language when the dropdown is used.
	 *
	 * @param WP_Query $query
	 */
	public function filter_posts_by_language( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		if ( empty( $_GET['Lingua_lang'] ) ) {
			return;
		}

		$lang = sanitize_text_field( wp_unslash( $_GET['Lingua_lang'] ) );
		$available = Lingua_Languages::get_available_languages();
		if ( ! isset( $available[ $lang ] ) ) {
			return;
		}

		$meta_query = array(
			array(
				'key'   => Pressento_Post_Meta::META_KEY,
				'value' => $lang,
			),
		);

		$existing = $query->get( 'meta_query' );
		if ( ! empty( $existing ) ) {
			$meta_query = array_merge( $existing, $meta_query );
		}

		$query->set( 'meta_query', $meta_query );
	}
}
