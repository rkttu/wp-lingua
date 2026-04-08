<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend language switcher and language-aware query filtering.
 *
 * - Appends a language switcher bar after post content on singular pages.
 * - Filters the main query on archives/home to show only the active language.
 * - Reads/sets the visitor's language via the ?lang= query parameter + cookie.
 */
class Lingua_Frontend {

	const QUERY_VAR = 'lang';
	const COOKIE    = 'Lingua_lang';

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_query_var' ) );
		add_filter( 'the_content', array( $this, 'append_language_switcher' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_main_query' ) );
		add_action( 'template_redirect', array( $this, 'set_language_cookie' ) );
		add_shortcode( 'Lingua_switcher', array( $this, 'shortcode_switcher' ) );
		add_shortcode( 'Lingua_global_switcher', array( $this, 'shortcode_global_switcher' ) );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ) );
	}

	/**
	 * Register 'lang' as a public query variable.
	 */
	public function register_query_var() {
		global $wp;
		$wp->add_query_var( self::QUERY_VAR );
	}

	/**
	 * Determine the currently active language.
	 *
	 * Priority: ?lang= parameter > cookie > default language.
	 *
	 * @return string
	 */
	public static function get_current_language() {
		$lang = get_query_var( self::QUERY_VAR );

		if ( $lang ) {
			$lang = sanitize_text_field( $lang );
			$available = Lingua_Languages::get_available_languages();
			if ( isset( $available[ $lang ] ) ) {
				return $lang;
			}
		}

		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$lang = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			$available = Lingua_Languages::get_available_languages();
			if ( isset( $available[ $lang ] ) ) {
				return $lang;
			}
		}

		// Detect from browser Accept-Language header.
		$browser_lang = self::detect_browser_language();
		if ( $browser_lang ) {
			return $browser_lang;
		}

		return Lingua_Languages::get_default_language();
	}

	/**
	 * Persist the chosen language in a cookie.
	 */
	public function set_language_cookie() {
		if ( is_admin() ) {
			return;
		}

		$lang = get_query_var( self::QUERY_VAR );
		if ( ! $lang ) {
			return;
		}

		$lang      = sanitize_text_field( $lang );
		$available = Lingua_Languages::get_available_languages();

		if ( isset( $available[ $lang ] ) ) {
			setcookie( self::COOKIE, $lang, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	/**
	 * Append the language switcher HTML after post content on singular views.
	 *
	 * @param string $content
	 * @return string
	 */
	public function append_language_switcher( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		$html = self::render_switcher( $post_id );
		if ( ! $html ) {
			return $content;
		}

		return $html . $content;
	}

	/**
	 * [Lingua_switcher] shortcode — render a language switcher anywhere.
	 *
	 * @return string
	 */
	public function shortcode_switcher() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		return self::render_switcher( $post_id );
	}

	/**
	 * Build the language switcher HTML for a given post.
	 *
	 * @param int $post_id
	 * @return string HTML or empty string if not multilingual.
	 */
	public static function render_switcher( $post_id ) {
		$current_lang = Pressento_Post_Meta::get_language( $post_id );
		if ( ! $current_lang ) {
			return '';
		}

		$translations = Lingua_Translation_Group::get_translations( $post_id );
		$languages    = Lingua_Languages::get_enabled_languages();

		// Only show switcher when there are multiple translations.
		if ( count( $translations ) < 2 ) {
			return '';
		}

		$items = array();
		foreach ( $translations as $lang_code => $tr_post ) {
			if ( 'publish' !== get_post_status( $tr_post->ID ) && $tr_post->ID !== $post_id ) {
				continue; // Only link to published translations (or the current post).
			}

			$label = isset( $languages[ $lang_code ] ) ? $languages[ $lang_code ] : $lang_code;

			if ( $lang_code === $current_lang ) {
				$items[] = sprintf(
					'<span class="lingua-switcher__item lingua-switcher__item--active" aria-current="true">%s</span>',
					esc_html( $label )
				);
			} else {
				$url     = get_permalink( $tr_post->ID );
				$items[] = sprintf(
					'<a href="%s" class="lingua-switcher__item" hreflang="%s">%s</a>',
					esc_url( $url ),
					esc_attr( $lang_code ),
					esc_html( $label )
				);
			}
		}

		if ( count( $items ) < 2 ) {
			return '';
		}

		$html  = '<nav class="lingua-switcher" aria-label="' . esc_attr__( 'Language', 'wp-lingua' ) . '">';
		$html .= implode( ' ', $items );
		$html .= '</nav>';

		return $html;
	}

	/**
	 * Filter the main query to only show posts in the active language.
	 *
	 * On archive, home, and search pages the query is limited to posts
	 * whose _Pressento_language meta matches the current language.
	 * Posts without any language meta are also included so that non-multilingual
	 * content is not hidden.
	 *
	 * @param WP_Query $query
	 */
	public function filter_main_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Only filter list pages, not singular.
		if ( $query->is_singular() ) {
			return;
		}

		$lang = self::get_current_language();

		$meta_query = array(
			'relation' => 'OR',
			array(
				'key'     => Pressento_Post_Meta::META_KEY,
				'value'   => $lang,
				'compare' => '=',
			),
			array(
				'key'     => Pressento_Post_Meta::META_KEY,
				'compare' => 'NOT EXISTS',
			),
		);

		$existing = $query->get( 'meta_query' );
		if ( ! empty( $existing ) ) {
			$meta_query = array(
				'relation' => 'AND',
				$existing,
				$meta_query,
			);
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * [Lingua_global_switcher] shortcode — site-wide language switcher.
	 *
	 * Usage:
	 *   [Lingua_global_switcher]              → dropdown (default)
	 *   [Lingua_global_switcher style="dropdown"]
	 *   [Lingua_global_switcher style="buttons"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_global_switcher( $atts = array() ) {
		$atts = shortcode_atts( array( 'style' => 'dropdown' ), $atts, 'Lingua_global_switcher' );

		if ( 'buttons' === $atts['style'] ) {
			return Lingua_Widget_Switcher::render_global_switcher();
		}

		return self::render_dropdown_switcher();
	}

	/**
	 * Filter the <html lang="..."> attribute to match the active language.
	 *
	 * @param string $output Existing language_attributes output.
	 * @return string
	 */
	public function filter_language_attributes( $output ) {
		if ( is_admin() ) {
			return $output;
		}

		$lang   = self::get_current_language();
		$locale = Lingua_Languages::code_to_locale( $lang );

		// Convert locale to BCP 47 format (e.g. ko_KR → ko-KR).
		$bcp47 = str_replace( '_', '-', $locale );

		$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $bcp47 ) . '"', $output );

		return $output;
	}

	/**
	 * Render a compact dropdown language switcher.
	 *
	 * @return string HTML output.
	 */
	public static function render_dropdown_switcher() {
		$languages    = Lingua_Languages::get_enabled_languages();
		$current_lang = self::get_current_language();

		// Build options with URLs.
		$translations = array();
		if ( is_singular() ) {
			$post_id   = get_queried_object_id();
			$post_lang = Pressento_Post_Meta::get_language( $post_id );
			if ( $post_lang ) {
				$current_lang = $post_lang;
				$translations = Lingua_Translation_Group::get_translations( $post_id );
			}
		}

		$options = array();
		foreach ( $languages as $code => $label ) {
			if ( ! empty( $translations[ $code ] ) && 'publish' === get_post_status( $translations[ $code ]->ID ) ) {
				$url = get_permalink( $translations[ $code ]->ID );
			} else {
				$url = add_query_arg( self::QUERY_VAR, $code, home_url( '/' ) );
			}
			$selected  = ( $code === $current_lang ) ? ' selected' : '';
			$options[] = sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( esc_url( $url ) ),
				$selected,
				esc_html( $label )
			);
		}

		$html  = '<div class="lingua-footer-select" aria-label="' . esc_attr__( 'Language', 'wp-lingua' ) . '">';
		$html .= '<span class="lingua-footer-select__icon" aria-hidden="true">&#127760;</span> ';
		$html .= '<select class="lingua-footer-select__dropdown" onchange="if(this.value)window.location.href=this.value">';
		$html .= implode( '', $options );
		$html .= '</select>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Detect language from the browser's Accept-Language header.
	 *
	 * @return string|null Matched language code or null.
	 */
	private static function detect_browser_language() {
		if ( empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return null;
		}

		$header    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );
		$available = Lingua_Languages::get_available_languages();
		$locale_map = Lingua_Languages::get_locale_map();

		// Parse Accept-Language into sorted list: e.g. "ko-KR,ko;q=0.9,en;q=0.8"
		$langs = array();
		foreach ( explode( ',', $header ) as $part ) {
			$part = trim( $part );
			if ( preg_match( '/^([a-zA-Z]{2,3}(?:-[a-zA-Z]{2,4})?)(?:;q=([\d.]+))?$/', $part, $m ) ) {
				$tag = strtolower( $m[1] );
				$q   = isset( $m[2] ) ? (float) $m[2] : 1.0;
				$langs[ $tag ] = $q;
			}
		}
		arsort( $langs );

		// Match against available languages.
		foreach ( array_keys( $langs ) as $tag ) {
			// Exact 2-letter match: "ko" → "ko"
			$short = substr( $tag, 0, 2 );
			if ( isset( $available[ $short ] ) ) {
				return $short;
			}
		}

		return null;
	}
}
